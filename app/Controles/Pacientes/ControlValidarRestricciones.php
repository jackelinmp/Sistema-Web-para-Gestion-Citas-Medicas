<?php
namespace App\Controles\Pacientes;

use App\Entidades\Especialidad;

/**
 * «control» ControlValidarRestricciones — CU06 Validar Edad y Restricciones («include» de CU14).
 * No tiene pantalla ni ruta propia: lo llama ControlAgendarCita.
 * La regla se calcula en la base con fn_especialidades_permitidas (edad a la fecha de la cita y sexo)
 * y el trigger tg_validar_cita la vuelve a comprobar al guardar.
 */
final class ControlValidarRestricciones
{
    public function obtenerEspecialidadesPermitidas(int $pacienteId, string $fecha): array
    {
        return (new Especialidad())->listarPermitidas($pacienteId, $fecha);
    }
}
