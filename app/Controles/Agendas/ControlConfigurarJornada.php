<?php
namespace App\Controles\Agendas;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Jornada;
use App\Entidades\Medico;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlConfigurarJornada — CU11 Configurar Jornada de Atención (RF05).   Responsable: Jackeline
 * Interfaces: IUJornadas.  Entidades: Medico, Jornada, Cita, Bitacora.
 * Permisos: módulo AGENDAS (C consultar, M modificar).
 */
final class ControlConfigurarJornada extends ControlBase
{
    private const MODULO = 'AGENDAS';
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function mostrarJornada(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $medicoId = (int) ($_GET['medico_id'] ?? 0) ?: null;
        $this->vista('Agendas/IUJornadas', [
            'medicos'  => (new Medico())->listarActivos(),
            'medicoId' => $medicoId,
            'jornada'  => $medicoId ? (new Jornada())->listarPorMedico($medicoId) : [],
            'errores'  => [],
            'pedirConfirmacion' => 0,
        ]);
    }

    public function guardarJornada(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        if (!(new Medico())->obtenerPorId($medicoId)) {
            $this->redirigir('jornadas');
        }
        [$dias, $errores] = $this->leerYValidar();
        $confirmado = ($_POST['confirmar'] ?? '') === '1';
        $fuera = 0;
        if (!$errores) {
            try {
                $fuera = Conexion::transaccion(function () use ($medicoId, $dias, $confirmado) {
                    (new Jornada())->guardar($medicoId, $dias);
                    $fuera = (new Cita())->contarFueraDeJornada($medicoId);
                    if ($fuera > 0 && !$confirmado) {
                        throw new \DomainException((string) $fuera);   // revierte y pide confirmación
                    }
                    (new Bitacora())->registrarEvento($this->idUsuario(), 'CONFIGURAR_JORNADA',
                                                      'Jornada del médico ' . $medicoId . ' (' . count($dias) . ' días)',
                                                      $this->ip(), 'jornada', $medicoId);
                    return $fuera;
                });
                Sesion::flash('ok', 'Jornada guardada correctamente.' . ($fuera ? " Hay $fuera citas fuera del nuevo horario." : ''));
                $this->redirigir('jornadas&medico_id=' . $medicoId);
            } catch (\DomainException $ex) {
                $fuera = (int) $ex->getMessage();
            } catch (\PDOException $ex) {
                $errores['general'] = 'La jornada se superpone con otra del mismo médico.';
            }
        }
        $this->vista('Agendas/IUJornadas', [
            'medicos'  => (new Medico())->listarActivos(),
            'medicoId' => $medicoId,
            'jornada'  => $dias,
            'errores'  => $errores,
            'pedirConfirmacion' => $fuera,
        ]);
    }

    /** Lee la grilla de 7 días; valida hora de salida > ingreso y bloque de 20 a 45 minutos. */
    private function leerYValidar(): array
    {
        $dias = [];
        $errores = [];
        foreach (self::DIAS as $n => $nombre) {
            if (empty($_POST['atiende'][$n])) {
                continue;
            }
            $ini = trim($_POST['hora_inicio'][$n] ?? '');
            $fin = trim($_POST['hora_fin'][$n] ?? '');
            $dur = (int) ($_POST['duracion_bloque_min'][$n] ?? 30);
            $dias[$n] = ['dia_semana' => $n, 'hora_inicio' => $ini, 'hora_fin' => $fin, 'duracion_bloque_min' => $dur];
            if (!preg_match('/^\d{2}:\d{2}/', $ini) || !preg_match('/^\d{2}:\d{2}/', $fin) || $fin <= $ini) {
                $errores[$n] = $nombre . ': La hora de salida debe ser posterior a la hora de ingreso';
            } elseif ($dur < 20 || $dur > 45) {
                $errores[$n] = $nombre . ': La duración del bloque debe estar entre 20 y 45 minutos';
            }
        }
        if (!$dias) {
            $errores['general'] = 'Marque al menos un día de atención.';
        }
        return [$dias, $errores];
    }
}
