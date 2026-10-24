<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Pago — tabla pago. En este ciclo solo se registra el pago PENDIENTE de la cita (CU14). */
final class Pago extends EntidadBase
{
    public function insertar(int $cita_id, string $monto, int $registrado_por): void
    {
        $this->bd->prepare('INSERT INTO pago (cita_id, monto, registrado_por) VALUES (:cita, :monto, :usuario)')
                 ->execute(['cita' => $cita_id, 'monto' => $monto, 'usuario' => $registrado_por]);
    }
}
