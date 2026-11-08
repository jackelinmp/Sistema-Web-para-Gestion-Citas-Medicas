<?php
namespace App\Controles\Pacientes;

use App\Entidades\Bitacora;
use App\Entidades\Paciente;
use App\Entidades\Persona;
use App\Entidades\VinculoFamiliar;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarGrupoFamiliar — CU22 Gestionar Grupo Familiar.   Ciclo 2 · P2 · Responsable: Jackeline
 * Interfaces: IUGrupoFamiliar.  Entidades: Paciente, VinculoFamiliar, Bitacora.
 * Permisos: PACIENTES/C consultar, PACIENTES/M agregar o quitar (lo hace recepción: verifica el parentesco con documentos).
 * Reglas:
 *  - Titular y familiar: pacientes ACTIVOS ya registrados (CU05) y distintos (la base: ck_vinculo_distintos).
 *  - Sin vínculos repetidos (uq_vinculo) ni el vínculo inverso (A a cargo de B y B a cargo de A).
 *  - Un familiar puede tener más de un titular (madre y padre).
 *  - Efecto: el titular (rol PACIENTE) agenda citas (CU14), paga (CU19) y ve pagos (CU20) y resultados (CU23) de sus familiares.
 */
final class ControlGestionarGrupoFamiliar extends ControlBase
{
    private const MODULO = 'PACIENTES';

    public function mostrarGrupo(): void
    {
        $titularId = $this->exigirAccesoPaciente(self::MODULO, (int) ($_GET['paciente_id'] ?? 0) ?: null);
        if (!$titularId) {                                          // paso 1: elegir paciente
            $this->mostrarBusqueda();
            return;
        }
        $this->mostrarPantalla($this->obtenerPaciente($titularId), []);
    }

    public function agregarFamiliar(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $titular = $this->obtenerPaciente((int) ($_POST['titular_id'] ?? 0));
        $ci = $this->texto('ci_familiar');
        $parentesco = $this->texto('parentesco');
        $familiar = $ci !== '' ? $this->buscarPorCi($ci) : null;
        $vinculos = new VinculoFamiliar();
        $titularId = (int) $titular['persona_id'];

        $errores = [];
        if ($titular['estado'] !== 'ACTIVO') {
            $errores['general'] = 'El titular está inactivo.';
        } elseif (!$familiar) {
            $errores['ci_familiar'] = 'No existe un paciente activo con ese CI. Regístrelo primero en Pacientes.';
        } elseif ((int) $familiar['persona_id'] === $titularId) {
            $errores['ci_familiar'] = 'El titular no puede ser su propio familiar.';
        } elseif ($vinculos->esFamiliar($titularId, (int) $familiar['persona_id'])) {
            $errores['ci_familiar'] = 'Ese paciente ya pertenece al grupo familiar.';
        } elseif ($vinculos->esFamiliar((int) $familiar['persona_id'], $titularId)) {
            $errores['ci_familiar'] = 'Ese paciente ya es titular de este paciente; no se puede crear el vínculo inverso.';
        }
        if (!isset(VinculoFamiliar::PARENTESCOS[$parentesco])) {
            $errores['parentesco'] = 'Seleccione el parentesco.';
        }
        if ($errores) {
            $this->mostrarPantalla($titular, $errores);
            return;
        }
        Conexion::transaccion(function () use ($vinculos, $titular, $familiar, $parentesco) {
            $id = $vinculos->insertar((int) $titular['persona_id'], (int) $familiar['persona_id'], $parentesco);
            // Sincroniza persona.tutor_id para que los avisos del menor lleguen a la cuenta del titular (CU24).
            (new Persona())->actualizarTutor((int) $familiar['persona_id'], (int) $titular['persona_id']);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'AGREGAR_FAMILIAR',
                                              'Familiar ' . $familiar['nro_expediente'] . ' a cargo de ' . $titular['nro_expediente'],
                                              $this->ip(), 'vinculo_familiar', $id);
        });
        Sesion::flash('ok', 'Familiar agregado al grupo.');
        $this->redirigir('grupo-familiar&paciente_id=' . $titular['persona_id']);
    }

    public function quitarFamiliar(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $vinculos = new VinculoFamiliar();
        $vinculo = $vinculos->obtenerPorId((int) ($_POST['id'] ?? 0));
        if (!$vinculo) {
            $this->redirigir('historial');
        }
        Conexion::transaccion(function () use ($vinculos, $vinculo) {
            $vinculos->eliminar((int) $vinculo['id']);
            // Recalcula el tutor del dependiente: otro titular del grupo, o ninguno.
            $restantes = $vinculos->listarTitulares((int) $vinculo['familiar_id']);
            (new Persona())->actualizarTutor((int) $vinculo['familiar_id'],
                $restantes ? (int) $restantes[0]['titular_id'] : null);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'QUITAR_FAMILIAR',
                                              'Familiar ' . $vinculo['familiar_id'] . ' quitado del titular ' . $vinculo['titular_id'],
                                              $this->ip(), 'vinculo_familiar', (int) $vinculo['id']);
        });
        Sesion::flash('ok', 'Familiar quitado del grupo.');
        $this->redirigir('grupo-familiar&paciente_id=' . $vinculo['titular_id']);
    }

    private function mostrarPantalla(array $titular, array $errores): void
    {
        $vinculos = new VinculoFamiliar();
        $this->vista('Pacientes/IUGrupoFamiliar', [
            'titular'        => $titular,
            'familiares'     => $vinculos->listarFamiliares((int) $titular['persona_id']),
            'titulares'      => $vinculos->listarTitulares((int) $titular['persona_id']),
            'errores'        => $errores,
            'puedeModificar' => Sesion::puede(self::MODULO, 'M'),
        ]);
    }

    /** Paciente activo cuyo CI coincide exactamente. */
    private function buscarPorCi(string $ci): ?array
    {
        foreach ((new Paciente())->listarPacientes($ci, 'ACTIVO') as $p) {
            if (strcasecmp($p['ci'], $ci) === 0) {
                return $p;
            }
        }
        return null;
    }

    private function mostrarBusqueda(): void
    {
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        $this->vista('Pacientes/IUBuscarPaciente', ['titulo' => 'Grupo familiar', 'rutaBusqueda' => 'grupo-familiar', 'criterio' => $criterio,
                     'pacientes' => $criterio !== '' ? (new Paciente())->listarPacientes($criterio) : []]);
    }

    private function obtenerPaciente(int $id): array
    {
        $paciente = (new Paciente())->obtenerPorId($id);
        if (!$paciente) {
            Sesion::flash('error', 'El paciente no existe.');
            $this->redirigir('grupo-familiar');
        }
        return $paciente;
    }
}
