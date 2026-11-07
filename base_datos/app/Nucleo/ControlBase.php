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
     * Acceso a los datos de un paciente (CU07, CU20, CU21, CU22, CU23).
     * - Exige el permiso de consulta del módulo.
     * - Paciente: sin $pacienteId devuelve el suyo; con $pacienteId solo acepta el suyo o el de un
     *   familiar a su cargo (CU22). Cualquier otro se registra en la bitácora como acceso denegado.
     * - Personal: devuelve el $pacienteId pedido (null = todavía no eligió paciente).
     */
    protected function exigirAccesoPaciente(string $modulo, ?int $pacienteId): ?int
    {
        Sesion::exigirPermiso($modulo, 'C');
        if (!$this->esPaciente()) {
            return $pacienteId;
        }
        $propio = (int) Sesion::usuario()['persona_id'];
        if ($pacienteId === null || $pacienteId === $propio) {
            return $propio;
        }
        if (!(new \App\Entidades\VinculoFamiliar())->esFamiliar($propio, $pacienteId)) {
            Sesion::denegar('Intento de ver datos del paciente ' . $pacienteId);
        }
        return $pacienteId;
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

    /** Paciente en sesión: ¿$pacienteId es él mismo o un familiar a su cargo (CU22)? */
    protected function esMioOFamiliar(int $pacienteId): bool
    {
        $propio = (int) (Sesion::usuario()['persona_id'] ?? 0);
        return $pacienteId === $propio || (new \App\Entidades\VinculoFamiliar())->esFamiliar($propio, $pacienteId);
    }
}
