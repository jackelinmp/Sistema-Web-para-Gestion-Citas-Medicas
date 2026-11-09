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
 *  - Efecto: el titular (rol PACIENTE) agenda citas (CU14), paga (CU19) y ve pagos (CU20) de sus familiares;
 *    el historial y los resultados solo de los menores a su cargo (ver ControlBase::exigirAccesoPaciente).
 *  - «Para otra persona» (desde Agendar cita): el usuario saca ficha para un hijo, familiar u otra persona.
 *    Recepción completa el resto de los datos en la primera visita.
 *  - Quien saca la cita no necesita ser paciente (migración 12): puede ser un padre que no se atiende.
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
        $titular = $this->esPaciente() && !(new Paciente())->obtenerPorId($titularId)
            ? (new Persona())->obtenerPorId($titularId) + ['persona_id' => $titularId, 'nro_expediente' => null]   // responsable sin expediente
            : $this->obtenerPaciente($titularId);
        $this->mostrarPantalla($titular, []);
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
            $errores['general'] = 'El responsable está inactivo.';
        } elseif (!$familiar) {
            $errores['ci_familiar'] = 'No existe un paciente activo con ese CI. Regístrelo primero en Pacientes.';
        } elseif ((int) $familiar['persona_id'] === $titularId) {
            $errores['ci_familiar'] = 'El responsable no puede ser su propio familiar.';
        } elseif ($vinculos->esFamiliar($titularId, (int) $familiar['persona_id'])) {
            $errores['ci_familiar'] = 'Ese paciente ya pertenece al grupo familiar.';
        } elseif ($vinculos->esFamiliar((int) $familiar['persona_id'], $titularId)) {
            $errores['ci_familiar'] = 'Ese paciente ya es responsable de este paciente; no se puede crear el vínculo inverso.';
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

    // ------------------------------------------------- «Para otra persona» (cuenta del usuario, desde CU14)

    /** Formulario «Para otra persona»: datos del hijo, familiar u otra persona que irá a la consulta. */
    public function mostrarOtraPersona(): void
    {
        $this->exigirCuentaPaciente();
        $this->vista('Pacientes/IUOtraPersona', ['d' => [], 'errores' => [], 'parentescos' => VinculoFamiliar::PARENTESCOS]);
    }

    /**
     * Deja a la persona en la lista «¿Para quién es la cita?» del usuario y sigue a Agendar cita con ella.
     *  - CI nueva (o menor sin CI): se registra como paciente (expediente con datos básicos).
     *    Un menor sin CI recibe la CI de quien lo registra + sufijo (-1, -2…).
     *  - CI ya registrada en la clínica: se usa ese mismo expediente si la fecha de nacimiento coincide.
     *    Un menor que ya tiene responsable registrado se vincula en recepción.
     *  - Quien la registra recibe los avisos y paga. El historial clínico solo lo ve si es menor de edad
     *    y él figura como su responsable (persona.tutor_id).
     */
    public function registrarOtraPersona(): void
    {
        $this->exigirCuentaPaciente();
        $propio = (int) Sesion::usuario()['persona_id'];
        $d = ['nombres' => $this->texto('nombres'), 'apellidos' => $this->texto('apellidos'), 'ci' => strtoupper($this->texto('ci')),
              'fecha_nacimiento' => $this->texto('fecha_nacimiento'), 'sexo' => $this->texto('sexo'), 'parentesco' => $this->texto('parentesco')];
        $errores = $this->validarOtraPersona($d);
        $existente = (!$errores && $d['ci'] !== '') ? (new Persona())->buscarPorCi($d['ci']) : null;
        if ($existente) {
            $id = (int) $existente['id'];
            if ($id === $propio) {
                $errores['ci'] = 'Esa es su CI. Para una cita suya, elija «Para mí».';
            } elseif (in_array($id, $this->pacientesACargo() ?? [], true)) {
                $this->redirigir('citas/nueva&paciente_id=' . $id);   // ya estaba en su lista
            } elseif ((string) $existente['fecha_nacimiento'] !== $d['fecha_nacimiento'] || !(new Paciente())->obtenerPorId($id)) {
                $errores['fecha_nacimiento'] = 'Los datos no coinciden con el paciente registrado con esa CI. Verifique la fecha de nacimiento.';
            } elseif ($this->esMenor($d['fecha_nacimiento']) && $this->tieneResponsable($existente)) {
                $errores['ci'] = 'Este menor ya tiene un responsable registrado. Para agregarlo a su cuenta, acérquese a recepción.';
            } else {
                $esMenor = $this->esMenor($d['fecha_nacimiento']);
                Conexion::transaccion(fn() => $this->agregarALaCuenta($propio, $id, $d['parentesco'], $esMenor,
                                                                      'Paciente registrado agregado en línea a la cuenta'));
                Sesion::flash('ok', 'Seleccione la especialidad y el horario para ' . $existente['nombres'] . ' ' . $existente['apellidos'] . '.');
                $this->redirigir('citas/nueva&paciente_id=' . $id);
            }
        }
        if ($errores) {
            $this->vista('Pacientes/IUOtraPersona', ['d' => $d, 'errores' => $errores, 'parentescos' => VinculoFamiliar::PARENTESCOS]);
            return;
        }
        $esMenor = $this->esMenor($d['fecha_nacimiento']);
        $id = Conexion::transaccion(function () use ($d, $propio, $esMenor) {
            if ($d['ci'] === '') {                                  // menor sin CI: CI de quien lo registra + sufijo
                $base = (new Persona())->obtenerPorId($propio)['ci'];
                for ($n = 1; (new Persona())->buscarPorCi($base . '-' . $n); $n++);
                $d['ci'] = $base . '-' . $n;
            }
            $id = (new Persona())->insertar($d + ['telefono' => null, 'direccion' => null]);
            (new Paciente())->abrirExpediente($id);
            $this->agregarALaCuenta($propio, $id, $d['parentesco'], $esMenor, 'Paciente registrado en línea por quien saca la cita');
            return $id;
        });
        Sesion::flash('ok', 'Seleccione la especialidad y el horario para ' . $d['nombres'] . ' ' . $d['apellidos'] . '.');
        $this->redirigir('citas/nueva&paciente_id=' . $id);
    }

    private function esMenor(string $fechaNacimiento): bool
    {
        return (new \DateTime($fechaNacimiento))->diff(new \DateTime('today'))->y < 18;
    }

    /** ¿El menor ya tiene un padre, madre o tutor registrado (por recepción o por otra cuenta)? */
    private function tieneResponsable(array $persona): bool
    {
        return !empty($persona['tutor_id']) || (bool) (new VinculoFamiliar())->listarTitulares((int) $persona['id']);
    }

    /**
     * Agrega al paciente a la lista de la cuenta (vinculo_familiar). Si es menor y nadie más es su responsable,
     * la cuenta queda como su tutor. Se llama dentro de una transacción.
     */
    private function agregarALaCuenta(int $cuenta, int $paciente, string $parentesco, bool $esMenor, string $detalle): void
    {
        $vinculo = (new VinculoFamiliar())->insertar($cuenta, $paciente, $parentesco);
        if ($esMenor && !(new Persona())->obtenerPorId($paciente)['tutor_id']) {
            (new Persona())->actualizarTutor($paciente, $cuenta);
        }
        (new Bitacora())->registrarEvento($this->idUsuario(), 'AGREGAR_PERSONA_A_CARGO', $detalle, $this->ip(), 'vinculo_familiar', $vinculo);
    }

    private function validarOtraPersona(array $d): array
    {
        $e = [];
        foreach (['nombres' => 'el nombre', 'apellidos' => 'los apellidos'] as $c => $t) {
            if ($d[$c] === '') {
                $e[$c] = 'Complete ' . $t . '.';
            }
        }
        $nacimiento = \DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
        if (!$nacimiento || $nacimiento->format('Y-m-d') !== $d['fecha_nacimiento'] || $d['fecha_nacimiento'] > date('Y-m-d')
            || $d['fecha_nacimiento'] < '1900-01-01') {
            $e['fecha_nacimiento'] = 'Ingrese una fecha de nacimiento válida.';
        } elseif ($d['ci'] === '' && $nacimiento->diff(new \DateTime('today'))->y >= 18) {
            $e['ci'] = 'La cédula es obligatoria para mayores de edad.';
        }
        if ($d['ci'] !== '' && !preg_match('/^[0-9A-Z-]{4,15}$/', $d['ci'])) {
            $e['ci'] = 'Escriba la cédula sin espacios (números, letras o guion).';
        }
        if (!in_array($d['sexo'], ['F', 'M'], true)) {
            $e['sexo'] = 'Seleccione el sexo.';
        }
        if (!isset(VinculoFamiliar::PARENTESCOS[$d['parentesco']])) {
            $e['parentesco'] = 'Seleccione el parentesco.';
        }
        return $e;
    }

    /** «Para otra persona» es de la cuenta del usuario paciente; el personal usa «Agregar familiar». */
    private function exigirCuentaPaciente(): void
    {
        Sesion::exigirPermiso('CITAS', 'R');
        if (!$this->esPaciente()) {
            Sesion::denegar('«Para otra persona» es una opción de la cuenta del paciente');
        }
    }
}
