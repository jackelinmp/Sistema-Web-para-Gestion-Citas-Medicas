<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» OtpVerificacion — tabla otp_verificacion (CU05 pre-registro). */
final class OtpVerificacion extends EntidadBase
{
    public function crear(int $pacienteId, string $telefono, string $codigo, int $minutos = 5): void
    {
        $this->bd->prepare("INSERT INTO otp_verificacion (paciente_id, telefono, codigo, fecha_expiracion)
                            VALUES (:p, :t, :c, now() + (:m || ' minutes')::interval)")
                 ->execute(['p' => $pacienteId, 't' => $telefono, 'c' => $codigo, 'm' => $minutos]);
    }

    /** Último OTP no verificado del paciente. */
    public function ultimo(int $pacienteId): ?array
    {
        $st = $this->bd->prepare("SELECT id, codigo, telefono, fecha_expiracion, verificado, intentos
                                    FROM otp_verificacion
                                   WHERE paciente_id = :p
                                   ORDER BY fecha_creacion DESC LIMIT 1");
        $st->execute(['p' => $pacienteId]);
        return $st->fetch() ?: null;
    }

    public function marcarVerificado(int $id): void
    {
        $this->bd->prepare('UPDATE otp_verificacion SET verificado = TRUE WHERE id = :id')->execute(['id' => $id]);
    }

    public function sumarIntento(int $id): void
    {
        $this->bd->prepare('UPDATE otp_verificacion SET intentos = intentos + 1 WHERE id = :id')->execute(['id' => $id]);
    }
}