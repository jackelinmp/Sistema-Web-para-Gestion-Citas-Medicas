<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\Persona;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

final class ControlPerfilPaciente extends ControlBase
{
    public function mostrarPerfil(): void
    {
        Sesion::exigirLogin();
        $u = Sesion::usuario();
        $persona = (new Persona())->obtenerPorId((int) $u['persona_id']);
        $this->vista('Seguridad/IUPerfilPaciente', ['persona' => $persona, 'errores' => [], 'ok' => null], 'principal');
    }

    public function guardarPerfil(): void
    {
        Sesion::exigirLogin();
        $u = Sesion::usuario();
        $personaId = (int) $u['persona_id'];
        $telefono = $this->texto('telefono');
        $correo = strtolower($this->texto('correo'));
        $errores = [];
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Correo inválido.';
        }
        if ($errores) {
            $persona = (new Persona())->obtenerPorId($personaId);
            $this->vista('Seguridad/IUPerfilPaciente', ['persona' => $persona, 'errores' => $errores, 'ok' => null], 'principal');
            return;
        }
        $bd = Conexion::obtener();
        $bd->prepare('UPDATE persona SET telefono = :t WHERE id = :id')->execute(['t' => $telefono ?: null, 'id' => $personaId]);
        if ($correo !== '') {
            $existe = (new Usuario())->existeCorreo($correo);
            $actual = (new Usuario())->buscarPorCorreo($u['correo'] ?? '');
            if (!$existe || ($actual && $actual['id'] === $u['id'])) {
                $bd->prepare('UPDATE usuario SET correo = :c WHERE id = :id')->execute(['c' => $correo, 'id' => $u['id']]);
            } else {
                $errores['correo'] = 'El correo ya está en uso.';
            }
        }
        if ($errores) {
            $persona = (new Persona())->obtenerPorId($personaId);
            $this->vista('Seguridad/IUPerfilPaciente', ['persona' => $persona, 'errores' => $errores, 'ok' => null], 'principal');
            return;
        }
        (new Bitacora())->registrarEvento($this->idUsuario(), 'ACTUALIZAR_PERFIL', 'Perfil actualizado', $this->ip(), 'persona', $personaId);
        Sesion::flash('ok', 'Perfil actualizado.');
        $this->redirigir('perfil');
    }
}