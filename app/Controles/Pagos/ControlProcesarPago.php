<?php
namespace App\Controles\Pagos;

use App\Entidades\Bitacora;
use App\Entidades\Pago;
use App\Nucleo\Config;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Red;
use App\Nucleo\Sesion;
use App\Servicios\PdfSimple;
use App\Servicios\ServicioStripe;

/**
 * «control» ControlProcesarPago — CU19 Procesar Pago en Línea (Stripe, modo prueba). «extend» de CU14 («Pagar ahora»).
 * Actores: Paciente y Recepcionista. Interfaces: IUPagos (lista) e IUDetallePago (detalle y resultado).
 * Flujo: detalle -> iniciarPago (sesión de Stripe Checkout) -> Stripe -> retorno (verifica en el servidor)
 *        y/o webhook (firma verificada) -> en UNA transacción: pago PAGADO + cita CONFIRMADA + comprobante + bitácora.
 * Si se rechaza o se abandona, el pago sigue PENDIENTE y se puede reintentar.
 */
final class ControlProcesarPago extends ControlBase
{
    public function listarPagos(): void
    {
        Sesion::exigirPermiso('PAGOS', 'C');
        $f = ['estado' => (string) ($_GET['estado'] ?? ''), 'criterio' => trim((string) ($_GET['criterio'] ?? ''))];
        if (!in_array($f['estado'], ['', 'PENDIENTE', 'PAGADO', 'ANULADO', 'REEMBOLSADO'], true)) {
            $f['estado'] = '';
        }
        $this->vista('Pagos/IUPagos', ['pagos' => (new Pago())->listar($f['estado'], $this->soloPaciente(), $f['criterio']), 'filtros' => $f]);
    }

    public function mostrarDetalle(): void
    {
        Sesion::exigirPermiso('PAGOS', 'C');
        $pago = $this->pagoAutorizado((int) ($_GET['cita_id'] ?? 0));
        $this->vista('Pagos/IUDetallePago', [
            'pago' => $pago, 'stripe' => ServicioStripe::configurado(),
            'puedePagar' => Sesion::puede('PAGOS', 'R') && $pago['pago_estado'] === 'PENDIENTE' && $pago['cita_estado'] === 'PROGRAMADA',
            'puedeSeguro' => Sesion::puede('PAGOS', 'R') && $this->soloPaciente() === null && $pago['pago_estado'] === 'PENDIENTE'
                             && $pago['cita_estado'] === 'PROGRAMADA' && $pago['aseguradora_id'] !== null,
        ]);
    }

    /** «Pagar ahora»: crea la sesión de Stripe Checkout y redirige al formulario seguro de Stripe. */
    public function iniciarPago(): void
    {
        Sesion::exigirPermiso('PAGOS', 'R');
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $pago = $this->pagoAutorizado($citaId);
        $this->exigirPendiente($pago);
        try {
            $sesion = (new ServicioStripe())->crearSesion(
                $citaId, $this->centavos($pago), $this->moneda($pago),
                'Consulta de ' . $pago['especialidad'] . ' — cita ' . $pago['codigo'],
                Red::urlBase() . '?r=pagos/retorno&cita_id=' . $citaId . '&session_id={CHECKOUT_SESSION_ID}',
                Red::urlBase() . '?r=pagos/cancelado&cita_id=' . $citaId);
        } catch (\RuntimeException $e) {
            Sesion::flash('error', 'No se pudo iniciar el pago en línea. ' . (Config::obtener('APP_DEBUG') === 'true' ? $e->getMessage() : 'Intente nuevamente.'));
            $this->redirigir('pagos/detalle&cita_id=' . $citaId);
        }
        (new Pago())->guardarReferencia($citaId, $sesion['id']);
        header('Location: ' . $sesion['url'], true, 303);
        exit;
    }

    /** Stripe devuelve aquí al usuario: el resultado se comprueba con Stripe desde el servidor, no se confía en la URL. */
    public function retornoPago(): void
    {
        Sesion::exigirPermiso('PAGOS', 'C');
        $citaId = (int) ($_GET['cita_id'] ?? 0);
        $pago = $this->pagoAutorizado($citaId);
        $id = (string) ($_GET['session_id'] ?? '');
        try {
            $sesion = (new ServicioStripe())->obtenerSesion($id);
            if (($sesion['metadata']['cita_id'] ?? '') !== (string) $citaId) {
                throw new \DomainException('La sesión de pago no corresponde a esta cita.');
            }
            if (($sesion['payment_status'] ?? '') !== 'paid') {
                Sesion::flash('error', 'El pago fue rechazado; intente con otra tarjeta');
            } else {
                $this->finalizarPago($pago, $sesion, (int) $pago['registrado_por']);
                Sesion::flash('ok', 'Pago aprobado. La cita quedó Confirmada y su comprobante está disponible.');
            }
        } catch (\DomainException $e) {
            Sesion::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Sesion::flash('error', 'No se pudo verificar el pago con Stripe. Si fue cobrado, se confirmará automáticamente.');
        }
        $this->redirigir('pagos/detalle&cita_id=' . $citaId);
    }

    public function pagoCancelado(): void
    {
        Sesion::exigirPermiso('PAGOS', 'C');
        $citaId = (int) ($_GET['cita_id'] ?? 0);
        $this->pagoAutorizado($citaId);
        Sesion::flash('aviso', 'El pago no se completó. La cita sigue pendiente de pago y puede reintentarlo.');
        $this->redirigir('pagos/detalle&cita_id=' . $citaId);
    }

    /** Notificación de Stripe (sin sesión ni CSRF): se descarta si la firma o el monto no coinciden. */
    public function recibirNotificacion(): void
    {
        $cuerpo = (string) file_get_contents('php://input');
        $cabecera = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        if (!ServicioStripe::firmaValida($cuerpo, $cabecera, (string) Config::obtener('STRIPE_WEBHOOK_SECRET'))) {
            $this->auditar(null, 'PAGO_NOTIFICACION_INVALIDA', 'Notificación de Stripe con firma inválida', null);
            $this->responder(400, 'firma invalida');
        }
        $evento = json_decode($cuerpo, true);
        $tipo = $evento['type'] ?? '';
        $sesion = $evento['data']['object'] ?? [];
        if (!in_array($tipo, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $this->responder(200, 'ignorado');
        }
        $citaId = (int) ($sesion['metadata']['cita_id'] ?? 0);
        $pago = (new Pago())->obtenerPorCita($citaId);
        if (!$pago || ($sesion['payment_status'] ?? '') !== 'paid') {
            $this->responder(200, 'sin accion');
        }
        try {
            $this->finalizarPago($pago, $sesion, (int) $pago['registrado_por']);
        } catch (\DomainException $e) {
            $this->auditar((int) $pago['registrado_por'], 'PAGO_NOTIFICACION_INVALIDA', $e->getMessage(), (int) $pago['pago_id']);
            $this->responder(400, 'descartado');
        }
        $this->responder(200, 'ok');
    }

    /** Confirma por seguro médico (solo personal): se registra la referencia de la póliza. */
    public function confirmarPorSeguro(): void
    {
        Sesion::exigirPermiso('PAGOS', 'R');
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $pago = $this->pagoAutorizado($citaId);
        if ($this->soloPaciente() !== null || $pago['aseguradora_id'] === null) {
            Sesion::denegar('Confirmación por seguro no permitida (cita ' . $citaId . ')');
        }
        $this->exigirPendiente($pago);
        $referencia = trim((string) ($_POST['referencia'] ?? '')) ?: (string) $pago['nro_poliza'];
        if ($referencia === '') {
            Sesion::flash('error', 'Indique la referencia de la póliza del paciente.');
            $this->redirigir('pagos/detalle&cita_id=' . $citaId);
        }
        $usuario = (int) $this->idUsuario();
        Conexion::transaccion(function () use ($citaId, $usuario, $referencia) {
            $p = new Pago();
            $p->obtenerPorCita($citaId, true);
            $p->registrarPago($citaId, 'SEGURO', $usuario, mb_substr($referencia, 0, 100));
            $p->confirmarCita($citaId, $usuario);
        });
        Sesion::flash('ok', 'Cita confirmada por seguro médico. Comprobante generado.');
        $this->redirigir('pagos/detalle&cita_id=' . $citaId);
    }

    /** Comprobante en PDF con número correlativo (REC-AAAA-NNNNN). */
    public function descargarComprobante(): void
    {
        Sesion::exigirPermiso('PAGOS', 'C');
        $pago = $this->pagoAutorizado((int) ($_GET['cita_id'] ?? 0));
        if ($pago['pago_estado'] !== 'PAGADO' || !$pago['nro_correlativo']) {
            Sesion::flash('error', 'Esta cita no tiene un pago aprobado.');
            $this->redirigir('pagos/detalle&cita_id=' . (int) $pago['cita_id']);
        }
        $pdf = new PdfSimple();
        $pdf->texto(50, 780, 'Clínica Vida Salud', 20, true)->texto(50, 762, 'Comprobante de pago', 12)
            ->texto(380, 780, $pago['nro_correlativo'], 14, true)
            ->texto(380, 762, 'Emitido: ' . date('d/m/Y H:i', strtotime((string) $pago['fecha_emision'])), 10)
            ->linea(50, 748, 545, 748);
        $filas = [
            'Paciente' => $pago['paciente'] . ' (' . $pago['nro_expediente'] . ')',
            'Cita' => $pago['codigo'],
            'Especialidad' => $pago['especialidad'],
            'Médico' => $pago['medico'],
            'Fecha y hora' => date('d/m/Y', strtotime((string) $pago['fecha'])) . ' ' . substr((string) $pago['hora_inicio'], 0, 5),
            'Método de pago' => $pago['metodo'] === 'STRIPE' ? 'Tarjeta (Stripe)' : ucfirst(strtolower((string) $pago['metodo'])),
            'Referencia' => (string) $pago['referencia_externa'],
            'Fecha de pago' => date('d/m/Y H:i', strtotime((string) $pago['fecha_pago'])),
        ];
        $y = 715;
        foreach ($filas as $k => $v) {
            $pdf->texto(50, $y, $k, 11, true)->texto(170, $y, $v, 11);
            $y -= 24;
        }
        $pdf->linea(50, $y + 8, 545, $y + 8)
            ->texto(50, $y - 16, 'TOTAL PAGADO', 13, true)
            ->texto(170, $y - 16, strtoupper((string) $pago['moneda']) . ' ' . number_format((float) $pago['monto'], 2, '.', ','), 13, true)
            ->texto(50, 60, 'Documento generado por el sistema. Pago procesado en modo de prueba.', 9);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $pago['nro_correlativo'] . '.pdf"');
        echo $pdf->generar();
        exit;
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * Registra el pago aprobado en UNA transacción: valida monto, pago PAGADO con el id de Stripe, cita CONFIRMADA,
     * comprobante y bitácora PAGO_APROBADO. Es idempotente: si ya estaba pagado no hace nada.
     */
    private function finalizarPago(array $pago, array $sesion, int $usuario): void
    {
        if ((int) ($sesion['amount_total'] ?? -1) !== $this->centavos($pago)
            || strtolower((string) ($sesion['currency'] ?? '')) !== $this->moneda($pago)) {
            throw new \DomainException('El monto del pago no coincide con el de la cita.');
        }
        Conexion::transaccion(function () use ($pago, $sesion, $usuario) {
            $p = new Pago();
            $actual = $p->obtenerPorCita((int) $pago['cita_id'], true);
            if ($actual['pago_estado'] === 'PAGADO') {
                return;
            }
            if ($actual['pago_estado'] !== 'PENDIENTE') {
                throw new \DomainException('Esta cita no tiene pagos pendientes');
            }
            $p->registrarPago((int) $pago['cita_id'], 'STRIPE', $usuario, (string) $sesion['id']);
            if ($actual['cita_estado'] === 'PROGRAMADA') {
                $p->confirmarCita((int) $pago['cita_id'], $usuario);
            }
        });
    }

    private function pagoAutorizado(int $citaId): array
    {
        $pago = (new Pago())->obtenerPorCita($citaId);
        if (!$pago) {
            Sesion::flash('error', 'La cita indicada no tiene un pago registrado.');
            $this->redirigir('pagos');
        }
        $propio = $this->soloPaciente();
        if ($propio !== null && (int) $pago['paciente_id'] !== $propio) {
            Sesion::denegar('Intento de ver el pago de la cita ' . $citaId . ' de otro paciente');
        }
        return $pago;
    }

    private function exigirPendiente(array $pago): void
    {
        if ($pago['pago_estado'] !== 'PENDIENTE' || $pago['cita_estado'] !== 'PROGRAMADA') {
            Sesion::flash('error', 'Esta cita no tiene pagos pendientes');
            $this->redirigir('pagos/detalle&cita_id=' . (int) $pago['cita_id']);
        }
    }

    /** El rol PACIENTE solo ve y paga lo suyo: devuelve su id de persona; para el personal, null. */
    private function soloPaciente(): ?int
    {
        $u = Sesion::usuario();
        return ($u['rol'] ?? '') === 'PACIENTE' ? (int) $u['persona_id'] : null;
    }

    private function moneda(array $pago): string
    {
        return strtolower((string) Config::obtener('STRIPE_MONEDA', trim((string) $pago['moneda'])));
    }

    /** Monto en centavos en la moneda de cobro (STRIPE_TASA = unidades de pago.moneda por 1 unidad de STRIPE_MONEDA). */
    private function centavos(array $pago): int
    {
        $monto = (float) $pago['monto'];
        $tasa = (float) Config::obtener('STRIPE_TASA', '1');
        return (int) round(($tasa > 0 ? $monto / $tasa : $monto) * 100);
    }

    private function auditar(?int $usuario, string $accion, string $descripcion, ?int $pagoId): void
    {
        (new Bitacora())->registrarEvento($usuario, $accion, $descripcion, $this->ip(), $pagoId ? 'pago' : null, $pagoId);
    }

    private function responder(int $codigo, string $texto): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode(['resultado' => $texto]);
        exit;
    }
}
