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
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        // En la nube (Vercel) la petición llega por un proxy: la IP real es la primera de X-Forwarded-For
        if ((getenv('VERCEL') || Config::obtener('TRUST_PROXY') === 'true') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function idUsuario(): ?int
    {
        return isset(Sesion::usuario()['id']) ? (int) Sesion::usuario()['id'] : null;
    }

    protected function texto(string $campo): string
    {
        return trim((string) ($_POST[$campo] ?? ''));
    }

    // ===================== CICLO 2 · Jackeline =====================
    /**
     * Permiso $modulo/C y, si el usuario es PACIENTE, solo él mismo o un familiar a su cargo (CU22).
     * Devuelve el paciente a mostrar: para el personal puede ser null (aún no lo eligió); para el paciente, nunca.
     * Usado por CU07, CU20, CU21, CU22 y CU23.
     */
    protected function exigirAccesoPaciente(string $modulo, ?int $pacienteId): ?int
    {
        Sesion::exigirPermiso($modulo, 'C');
        if (!$this->esPaciente()) {
            return $pacienteId;
        }
        $propio = (int) Sesion::usuario()['persona_id'];
        if ($pacienteId === null || $pacienteId === $propio
            || (new \App\Entidades\VinculoFamiliar())->esFamiliar($propio, $pacienteId)) {
            return $pacienteId ?? $propio;
        }
        http_response_code(403);
        Vista::mostrar('plantillas/IUAccesoDenegado', [], 'principal');
        exit;
    }

    protected function esPaciente(): bool
    {
        return (Sesion::usuario()['rol'] ?? '') === 'PACIENTE';
    }

    /** Fecha aaaa-mm-dd de un filtro GET; cadena vacía si no viene o no es válida. */
    protected function fechaDeFiltro(string $campo): string
    {
        $valor = (string) ($_GET[$campo] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $valor);
        return ($d && $d->format('Y-m-d') === $valor) ? $valor : '';
    }
}
