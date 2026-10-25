<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarBitacora — consulta de la bitácora de auditoría (solo lectura).
 * La bitácora no se modifica ni se borra (trigger fn_bitacora_inmutable). Interfaz: IUBitacora.
 */
final class ControlGestionarBitacora extends ControlBase
{
    public function consultarBitacora(): void
    {
        Sesion::exigirPermiso('SEGURIDAD', 'C');
        $f = [
            'desde'   => $this->fechaValida($_GET['desde'] ?? ''),
            'hasta'   => $this->fechaValida($_GET['hasta'] ?? ''),
            'usuario' => trim((string) ($_GET['usuario'] ?? '')),
            'accion'  => trim((string) ($_GET['accion'] ?? '')),
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $bitacora = new Bitacora();
        $resultado = $bitacora->listar($f, $pagina);
        $this->vista('Seguridad/IUBitacora', [
            'filtros' => $f, 'registros' => $resultado['filas'], 'total' => $resultado['total'],
            'pagina' => $pagina, 'paginas' => max(1, (int) ceil($resultado['total'] / 50)),
            'acciones' => $bitacora->listarAcciones(),
        ]);
    }

    private function fechaValida(string $valor): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
    }
}
