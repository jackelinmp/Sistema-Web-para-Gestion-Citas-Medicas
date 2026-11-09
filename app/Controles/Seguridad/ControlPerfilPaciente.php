<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\Notificacion;
use App\Entidades\Paciente;
use App\Entidades\Persona;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;
use App\Servicios\ServicioWhatsApp;

/**
 * «control» ControlPerfilPaciente — Mi perfil (extensión de CU01 y CU24).
 * Interfaz: IUPerfilPaciente.  Entidades: Persona, Paciente, Usuario, Bitacora.
 * Cada usuario corrige sus datos de contacto y elige cómo recibir los avisos de sus citas.
 * Nombre, CI y fecha de nacimiento no se cambian aquí: los corrige recepción con el carnet.
 */
final class ControlPerfilPaciente extends ControlBase
{
    public function mostrarPerfil(): void
    {
        Sesion::exigirLogin();
        $this->mostrarFormulario([], null);
    }

    public function guardarPerfil(): void
    {
        Sesion::exigirLogin();
        $u = Sesion::usuario();
        $personaId = (int) $u['persona_id'];
        $d = [
            'telefono'   => $this->texto('telefono'),
            'direccion'  => $this->texto('direccion'),
            'correo'     => strtolower($this->texto('correo')),
            'whatsapp'   => isset($_POST['avisos_whatsapp']),
            'ce_nombre'  => $this->texto('contacto_emergencia_nombre'),
            'ce_parent'  => $this->texto('contacto_emergencia_parentesco'),
            'ce_tel'     => $this->texto('contacto_emergencia_telefono'),
        ];
        $errores = $this->validarPerfil($d, (int) $u['id']);
        if ($errores) {
            $this->mostrarFormulario($errores, $d);
            return;
        }
        $notificacion = new Notificacion();
        $paciente = (new Paciente())->obtenerPorId($personaId);
        Conexion::transaccion(function () use ($d, $u, $personaId, $paciente, $notificacion) {
            (new Persona())->actualizarContacto($personaId, $d['telefono'] ?: null, $d['direccion'] ?: null);
            (new Usuario())->actualizarPreferencias((int) $u['id'], $d['correo'],
                $notificacion->existeColumnaWhatsapp() ? $d['whatsapp'] : null);
            if ($paciente) {
                (new Paciente())->actualizarContactoEmergencia($personaId, $d['ce_nombre'] ?: null, $d['ce_parent'] ?: null, $d['ce_tel'] ?: null);
            }
        });
        $_SESSION['usuario']['correo'] = $d['correo'];
        (new Bitacora())->registrarEvento($this->idUsuario(), 'ACTUALIZAR_PERFIL',
            'Datos de contacto actualizados' . ($d['whatsapp'] ? ' (avisos por WhatsApp activos)' : ''), $this->ip(), 'persona', $personaId);
        Sesion::flash('ok', 'Sus datos se guardaron.');
        $this->redirigir('perfil');
    }

    private function validarPerfil(array $d, int $usuarioId): array
    {
        $errores = [];
        if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Escriba un correo válido.';
        } elseif ((new Usuario())->existeCorreo($d['correo'], $usuarioId)) {
            $errores['correo'] = 'Ese correo ya lo usa otra cuenta.';
        }
        if ($d['telefono'] !== '' && !preg_match('/^\+?[0-9 ]{7,15}$/', $d['telefono'])) {
            $errores['telefono'] = 'Escriba solo números (por ejemplo 71234567).';
        }
        if ($d['whatsapp'] && ServicioWhatsApp::normalizarNumero($d['telefono']) === null) {
            $errores['telefono'] = 'Para recibir avisos por WhatsApp escriba su número de celular.';
        }
        if ($d['ce_tel'] !== '' && !preg_match('/^\+?[0-9 ]{7,15}$/', $d['ce_tel'])) {
            $errores['contacto_emergencia_telefono'] = 'Escriba solo números.';
        }
        return $errores;
    }

    /** $d = null: datos guardados; si no, lo que el usuario escribió (para no perderlo al mostrar errores). */
    private function mostrarFormulario(array $errores, ?array $d): void
    {
        $u = Sesion::usuario();
        $persona = (new Persona())->obtenerPorId((int) $u['persona_id']);
        $paciente = (new Paciente())->obtenerPorId((int) $u['persona_id']);
        $notificacion = new Notificacion();
        $whatsappDisponible = $notificacion->existeColumnaWhatsapp();
        $d ??= [
            'telefono'  => $persona['telefono'] ?? '',
            'direccion' => $persona['direccion'] ?? '',
            'correo'    => $persona['correo'] ?? '',
            'whatsapp'  => $whatsappDisponible && (new Usuario())->aceptaWhatsapp((int) $u['id']),
            'ce_nombre' => $paciente['contacto_emergencia_nombre'] ?? '',
            'ce_parent' => $paciente['contacto_emergencia_parentesco'] ?? '',
            'ce_tel'    => $paciente['contacto_emergencia_telefono'] ?? '',
        ];
        $this->vista('Seguridad/IUPerfilPaciente', [
            'persona'            => $persona,
            'esPaciente'         => (bool) $paciente,
            'd'                  => $d,
            'errores'            => $errores,
            'whatsappDisponible' => $whatsappDisponible,
        ]);
    }
}
