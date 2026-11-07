<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\FichaClinica;
use App\Entidades\Paciente;
use App\Entidades\ParametroSistema;
use App\Entidades\Persona;
use App\Entidades\Rol;
use App\Entidades\TokenRecuperacion;
use App\Entidades\Usuario;
use App\Nucleo\Ciclos;
use App\Nucleo\Config;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;
use App\Servicios\ServicioCorreo;

/**
 * «control» ControlIniciarSesion — CU01 Iniciar Sesión.
 * Incluye: bloqueo temporal tras 3 intentos fallidos, recuperación de contraseña por correo
 * y cambio de contraseña (obligatorio en el primer ingreso). Contraseñas cifradas con bcrypt.
 * Interfaces: IUIniciarSesion, IUPanel, IURecuperarContrasena, IURestablecerContrasena, IUCambiarContrasena,
 *             IURegistroPaciente (paciente nuevo que se registra solo, Ciclo 2).
 * Entidades: Usuario, Rol, TokenRecuperacion, ParametroSistema, Bitacora, Persona, Paciente, FichaClinica.
 * Servicio: ServicioCorreo.
 */
final class ControlIniciarSesion extends ControlBase
{
    public function mostrarFormulario(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $this->vista('Seguridad/IUIniciarSesion', ['correo' => ''], 'publica');
    }

    /** Pre-registro de paciente externo: CI + fecha de nacimiento + teléfono. Crea paciente PRE_REGISTRADO y envía OTP. */
    public function mostrarPreRegistro(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $this->vista('Seguridad/IUPreRegistroPaciente', ['d' => [], 'errores' => [], 'otpDebug' => null], 'publica');
    }

    public function preRegistrar(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $ci = $this->texto('ci');
        $fechaNac = $this->texto('fecha_nacimiento');
        $telefono = $this->texto('telefono');
        $correo = strtolower($this->texto('correo'));
        $errores = [];
        if ($ci === '') $errores['ci'] = 'Ingrese su número de carnet.';
        if ($fechaNac === '') $errores['fecha_nacimiento'] = 'Ingrese su fecha de nacimiento.';
        if ($telefono === '') $errores['telefono'] = 'Ingrese su número de celular.';
        if ($errores) {
            $this->vista('Seguridad/IUPreRegistroPaciente', ['d' => compact('ci','fechaNac','telefono','correo'), 'errores' => $errores, 'otpDebug' => null], 'publica');
            return;
        }
        // Buscar si ya existe la persona
        $persona = (new Persona())->buscarPorCi($ci);
        if ($persona && (new Usuario())->existeCorreo($correo !== '' ? $correo : 'x@x.com') === false && $persona['usuario_id']) {
            // Ya tiene cuenta: enviar a login
            Sesion::flash('info', 'Ya tiene una cuenta registrada. Inicie sesión.');
            $this->redirigir('sesion/formulario');
            return;
        }
        $personaId = $persona ? (int) $persona['id'] : null;
        if (!$personaId) {
            $personaId = (new Persona())->insertar(['ci' => $ci, 'nombres' => 'Paciente', 'apellidos' => 'Nuevo',
                'fecha_nacimiento' => $fechaNac, 'sexo' => 'M', 'telefono' => $telefono, 'direccion' => null]);
        }
        // Crear paciente pre-registrado si no existe
        $paciente = (new Paciente())->obtenerPorId($personaId);
        if (!$paciente) {
            (new Paciente())->insertar($personaId, ['contacto_emergencia_nombre' => '', 'contacto_emergencia_parentesco' => '',
                'contacto_emergencia_telefono' => '', 'aseguradora_id' => null, 'nro_poliza' => null]);
            (new FichaClinica())->crearFichaVacia($personaId);
            // marcar como pre-registrado
            Conexion::obtener()->prepare("UPDATE paciente SET estado = 'PRE_REGISTRADO', fecha_pre_registro = now() WHERE persona_id = :id")
                ->execute(['id' => $personaId]);
        }
        // Pre-registro simple: recepcion activa el expediente (sin OTP, sin servicios externos)
        Sesion::flash('ok', 'Pre-registro completado. Presentese en recepcion para validar su carnet y activar su cuenta.');
        $this->redirigir('sesion/formulario');
    }

    public function cerrarSesion(): void
    {
        if ($id = $this->idUsuario()) {
            (new Bitacora())->registrarEvento($id, 'CIERRE_SESION', 'Salida del sistema', $this->ip(), 'usuario', $id);
        }
        Sesion::cerrar();
        Sesion::iniciar();
        Sesion::flash('ok', 'Sesión cerrada correctamente.');
        $this->redirigir('sesion/formulario');
    }

    /** Inicio: módulos por ciclo (?ciclo=1|2|todos). Lo que el rol no puede usar se muestra bloqueado. */
    public function mostrarPanel(): void
    {
        Sesion::exigirLogin();
        $ciclos = Ciclos::evaluar();
        $pedido = (string) ($_GET['ciclo'] ?? '1');
        $seleccion = ($pedido === 'todos' || isset($ciclos[(int) $pedido])) ? $pedido : '1';
        $this->vista('Seguridad/IUPanel', [
            'ciclos' => $ciclos, 'seleccion' => $seleccion, 'indicadores' => $this->indicadores(),
        ]);
    }

    /** Cifras del día solo de lo que el rol puede consultar (RBAC). */
    private function indicadores(): array
    {
        $bd = Conexion::obtener();
        $cifras = [];
        if (Sesion::puede('PACIENTES', 'C')) {
            $cifras['Pacientes activos'] = $bd->query("SELECT count(*) FROM paciente WHERE estado = 'ACTIVO'")->fetchColumn();
        }
        $usuario = Sesion::usuario();
        if (($usuario['rol'] ?? '') === 'PACIENTE') {
            // Corrección: antes el paciente veía las cifras de toda la clínica; ahora solo las suyas
            $st = $bd->prepare("SELECT count(*) FILTER (WHERE fecha >= CURRENT_DATE AND estado IN ('PROGRAMADA','CONFIRMADA')) AS proximas,
                                       count(*) FILTER (WHERE estado = 'ATENDIDA') AS atendidas
                                  FROM cita WHERE paciente_id = :p");
            $st->execute(['p' => (int) $usuario['persona_id']]);
            $mias = $st->fetch();
            return ['Mis citas próximas' => $mias['proximas'], 'Mis consultas atendidas' => $mias['atendidas'],
                    'Notificaciones sin leer' => (new \App\Entidades\Notificacion())->contarNoLeidas((int) $usuario['id'])];
        }
        if (Sesion::puede('CITAS', 'C')) {
            $cifras['Citas de hoy'] = $bd->query("SELECT count(*) FROM cita WHERE fecha = CURRENT_DATE AND estado <> 'CANCELADA'")->fetchColumn();
            $cifras['Citas próximas (7 días)'] = $bd->query("SELECT count(*) FROM cita WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + 7 AND estado IN ('PROGRAMADA','CONFIRMADA')")->fetchColumn();
        }
        if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'C')) {
            $cifras['Médicos activos'] = $bd->query("SELECT count(*) FROM medico WHERE estado = 'ACTIVO'")->fetchColumn();
        }
        if (Sesion::puede('SEGURIDAD', 'C')) {
            $cifras['Cuentas habilitadas'] = $bd->query("SELECT count(*) FROM usuario WHERE estado = 'HABILITADA'")->fetchColumn();
        }
        return $cifras;
    }

    // ------------------------------------------------- recuperar contraseña por correo

    public function mostrarRecuperacion(): void
    {
        $this->vista('Seguridad/IURecuperarContrasena', ['correo' => ''], 'publica');
    }

    public function solicitarRecuperacion(): void
    {
        $correo = $this->texto('correo');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Sesion::flash('error', 'Ingrese un correo electrónico válido.');
            $this->vista('Seguridad/IURecuperarContrasena', ['correo' => $correo], 'publica');
            return;
        }
        $enlacePrueba = null;
        $cuenta = (new Usuario())->buscarPorCorreo($correo);
        if ($cuenta && $cuenta['estado'] === 'HABILITADA') {
            $minutos = (int) (new ParametroSistema())->obtenerValor('MINUTOS_ENLACE_RECUP', '30');
            $token = (new TokenRecuperacion())->generar((int) $cuenta['id'], $minutos);
            $enlace = $this->urlBase() . '?r=sesion/restablecer&token=' . $token;
            $enviado = (new ServicioCorreo())->enviarEnlaceRecuperacion($cuenta['correo'], $cuenta['nombre'], $enlace, $minutos);
            (new Bitacora())->registrarEvento((int) $cuenta['id'], 'SOLICITAR_RECUPERACION',
                'Enlace de recuperación ' . ($enviado ? 'enviado' : 'NO enviado (correo sin configurar o con error)'),
                $this->ip(), 'usuario', (int) $cuenta['id']);
            // Sin servidor de correo y en modo de pruebas, el enlace se muestra en pantalla
            if (!$enviado && Config::obtener('APP_DEBUG') === 'true') {
                $enlacePrueba = $enlace;
            }
        }
        // Mismo mensaje exista o no la cuenta: no se revela qué correos están registrados
        Sesion::flash('ok', 'Si el correo está registrado, le enviamos un enlace para restablecer su contraseña. Revise su bandeja de entrada y la carpeta de spam.');
        $this->vista('Seguridad/IURecuperarContrasena', ['correo' => '', 'enlacePrueba' => $enlacePrueba], 'publica');
    }

    public function mostrarRestablecer(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (!(new TokenRecuperacion())->buscarVigente($token)) {
            Sesion::flash('error', 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            $this->redirigir('sesion/recuperar');
        }
        $this->vista('Seguridad/IURestablecerContrasena', ['token' => $token], 'publica');
    }

    public function restablecerContrasena(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $tokens = new TokenRecuperacion();
        $vigente = $tokens->buscarVigente($token);
        if (!$vigente) {
            Sesion::flash('error', 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            $this->redirigir('sesion/recuperar');
        }
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        if ($error = self::validarContrasena($contrasena, (string) ($_POST['confirmacion'] ?? ''))) {
            Sesion::flash('error', $error);
            $this->vista('Seguridad/IURestablecerContrasena', ['token' => $token], 'publica');
            return;
        }
        (new Usuario())->cambiarContrasena((int) $vigente['usuario_id'], $contrasena);
        $tokens->marcarUsado((int) $vigente['id']);
        (new Bitacora())->registrarEvento((int) $vigente['usuario_id'], 'RESTABLECER_CONTRASENA',
            'Contraseña restablecida con enlace de recuperación', $this->ip(), 'usuario', (int) $vigente['usuario_id']);
        Sesion::flash('ok', 'Su contraseña fue actualizada. Ya puede iniciar sesión.');
        $this->redirigir('sesion/formulario');
    }

    // ------------------------------------------------- cambiar contraseña (con sesión)

    public function mostrarCambioContrasena(): void
    {
        Sesion::exigirLogin();
        $this->vista('Seguridad/IUCambiarContrasena');
    }

    public function cambiarContrasena(): void
    {
        Sesion::exigirLogin();
        $id = (int) $this->idUsuario();
        $usuarios = new Usuario();
        if (!password_verify((string) ($_POST['actual'] ?? ''), (string) $usuarios->obtenerHash($id))) {
            Sesion::flash('error', 'La contraseña actual no es correcta.');
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        $nueva = (string) ($_POST['contrasena'] ?? '');
        if ($error = self::validarContrasena($nueva, (string) ($_POST['confirmacion'] ?? ''))) {
            Sesion::flash('error', $error);
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        if (password_verify($nueva, (string) $usuarios->obtenerHash($id))) {
            Sesion::flash('error', 'La nueva contraseña debe ser distinta de la actual.');
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        $usuarios->cambiarContrasena($id, $nueva);
        (new Bitacora())->registrarEvento($id, 'CAMBIAR_CONTRASENA', 'El usuario cambió su contraseña', $this->ip(), 'usuario', $id);
        Sesion::flash('ok', 'Contraseña cambiada correctamente.');
        $this->redirigir('panel');
    }

    /** Regla de contraseña: mínimo 8 caracteres, al menos una letra y un número, y confirmación igual. */
    public static function validarContrasena(string $contrasena, string $confirmacion): ?string
    {
        if (strlen($contrasena) < 8 || !preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
            return 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
        }
        if ($contrasena !== $confirmacion) {
            return 'La confirmación no coincide con la contraseña.';
        }
        return null;
    }

    /** Dirección base del sistema para el enlace del correo (APP_URL o la del navegador). */
    private function urlBase(): string
    {
        return \App\Nucleo\Red::urlBase();
    }
}
