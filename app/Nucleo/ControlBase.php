<?php
namespace App\Nucleo;

/** Funciones comunes de todos los controles «control». */
abstract class ControlBase
{
    protected function vista(string $interfaz, array $datos = [], ?string $plantilla = 'principal'): void
    {
        Vista::mostrar($interfaz, $datos, $plantilla);
    }

    protected function redirigir(string $ruta): never
    {
        header('Location: ?r=' . $ruta);
        exit;
    }

    /** IP de origen obtenida por el servidor, nunca enviada por el usuario (RN14). */
    protected function ip(): ?string
    {
        return Red::ip();
    }

    protected function idUsuario(): ?int
    {
        return isset(Sesion::usuario()['id']) ? (int) Sesion::usuario()['id'] : null;
    }

    protected function texto(string $campo): string
    {
        return trim((string) ($_POST[$campo] ?? ''));
    }

    /** ¿El usuario en sesión tiene el rol PACIENTE? */
    protected function esPaciente(): bool
    {
        return (Sesion::usuario()['rol'] ?? '') === 'PACIENTE';
    }

    /**
     * Usuario paciente que aún no tiene expediente propio (por ejemplo, un padre que solo reserva para su hijo).
     * true = se mostró la pantalla informativa con sus dependientes menores, y el control debe terminar.
     */
    protected function mostrarSinExpediente(?int $pacienteId, string $titulo, string $ruta): bool
    {
        if (!$this->esPaciente() || $pacienteId !== (int) Sesion::usuario()['persona_id']
            || (new \App\Entidades\Paciente())->obtenerPorId($pacienteId)) {
            return false;
        }
        $menores = array_filter((new \App\Entidades\VinculoFamiliar())->listarFamiliares($pacienteId),
                                fn($f) => $this->esMenorACargo((int) $f['familiar_id']));
        $this->vista('Pacientes/IUSinExpediente', ['titulo' => $titulo, 'ruta' => $ruta, 'menores' => array_values($menores)]);
        return true;
    }

    /**
     * Ids de los pacientes que el rol PACIENTE puede gestionar: el suyo y los de su grupo familiar (CU22).
     * Para el personal devuelve null (sin restricción).
     */
    protected function pacientesACargo(): ?array
    {
        if (!$this->esPaciente()) {
            return null;
        }
        $propio = (int) Sesion::usuario()['persona_id'];
        $ids = [$propio];
        foreach ((new \App\Entidades\VinculoFamiliar())->listarFamiliares($propio) as $f) {
            $ids[] = (int) $f['familiar_id'];
        }
        return array_values(array_unique($ids));
    }

    /**
     * Acceso a los datos de un paciente (CU07 historial, CU20 pagos, CU21 ficha, CU22 familia, CU23 laboratorio).
     * Personal: necesita el permiso de consulta del módulo y devuelve el paciente pedido (null = aún no eligió).
     * Cuenta de paciente: no usa permisos de personal; la regla es de quién son los datos:
     *   - lo suyo: siempre;
     *   - de una persona a su cargo (CU22): citas y pagos (módulos CITAS y PAGOS);
     *   - información clínica (historial, ficha, laboratorio) de otra persona: solo si es un menor a su cargo
     *     (la de un adulto es privada aunque otro le saque las citas).
     * Cualquier otro intento queda en la bitácora como acceso denegado.
     */
    protected function exigirAccesoPaciente(string $modulo, ?int $pacienteId): ?int
    {
        if (!$this->esPaciente()) {
            Sesion::exigirPermiso($modulo, 'C');
            return $pacienteId;
        }
        Sesion::exigirLogin();
        $propio = (int) Sesion::usuario()['persona_id'];
        if ($pacienteId === null || $pacienteId === $propio) {
            return $propio;
        }
        $permitido = in_array($modulo, ['CITAS', 'PAGOS'], true)
            ? in_array($pacienteId, $this->pacientesACargo() ?? [], true)
            : $this->esMenorACargo($pacienteId);
        if (!$permitido) {
            Sesion::denegar('Intento de ver datos del paciente ' . $pacienteId);
        }
        return $pacienteId;
    }

    /** ¿$pacienteId es un menor de edad del que el usuario en sesión es tutor o responsable (CU22)? */
    protected function esMenorACargo(int $pacienteId): bool
    {
        $propio = (int) (Sesion::usuario()['persona_id'] ?? 0);
        $st = \App\Nucleo\Conexion::obtener()->prepare(
            "SELECT 1 FROM persona p
              WHERE p.id = :p AND p.fecha_nacimiento > current_date - interval '18 years'
                AND (p.tutor_id = :t OR EXISTS (SELECT 1 FROM vinculo_familiar v WHERE v.paciente_id = :t2 AND v.familiar_id = p.id))");
        $st->execute(['p' => $pacienteId, 't' => $propio, 't2' => $propio]);
        return (bool) $st->fetchColumn();
    }

    /** Fecha AAAA-MM-DD de un filtro GET; cadena vacía si falta o no es válida. */
    protected function fechaDeFiltro(string $campo): string
    {
        $valor = (string) ($_GET[$campo] ?? '');
        $fecha = \DateTime::createFromFormat('Y-m-d', $valor);
        return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : '';
    }

    /** Traduce los rechazos de la base (EXCLUDE, CHECK y RAISE de los triggers) a un mensaje para el usuario. */
    protected function mensajeBaseDatos(\PDOException $e): string
    {
        $m = $e->getMessage();
        if (str_contains($m, 'ex_cita_paciente')) {
            return 'El paciente ya tiene otra cita en ese horario.';
        }
        if (str_contains($m, 'ex_cita_medico')) {
            return 'El horario seleccionado ya no está disponible.';
        }
        if (preg_match('/ERROR:\s+(.+?)(\n|CONTEXT|$)/s', $m, $x)) {
            return trim($x[1]);
        }
        return 'No se pudo guardar. Intente nuevamente.';
    }
}
