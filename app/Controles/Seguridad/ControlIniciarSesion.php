<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
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
 *             IUCrearCuenta (cualquier adulto crea su cuenta para sacar citas, Ciclo 2).
 * Entidades: Usuario, Rol, TokenRecuperacion, ParametroSistema, Bitacora, Persona.
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

    public function iniciarSesion(): void
    {
        $correo = $this->texto('correo');
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        if ($correo === '' || $contrasena === '') {
            Sesion::flash('error', 'Ingrese su correo o CI y su contraseña.');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }
        $usuarios = new Usuario();
        $bitacora = new Bitacora();
        $cuenta = $usuarios->buscarPorIdentificador($correo);

        // 1) Cuenta bloqueada temporalmente: no se revisa la contraseña
        if ($cuenta && ($minutos = $usuarios->estaBloqueado($cuenta)) > 0) {
            $this->mostrarBloqueo($correo, $minutos);
            return;
        }

        // 2) Contraseña incorrecta: suma un intento y bloquea al llegar al máximo
        if (!$cuenta || !password_verify($contrasena, $cuenta['contrasena_hash'])) {
            $mensaje = 'Correo, CI o contraseña incorrectos.';
            if ($cuenta) {
                $parametros = new ParametroSistema();
                $max = (int) $parametros->obtenerValor('INTENTOS_MAX_LOGIN', '3');
                $minBloqueo = (int) $parametros->obtenerValor('MINUTOS_BLOQUEO', '15');
                $r = $usuarios->registrarIntentoFallido((int) $cuenta['id'], $max, $minBloqueo);
                if ($r['bloqueada']) {
                    $bitacora->registrarEvento((int) $cuenta['id'], 'BLOQUEO_CUENTA',
                        "Cuenta bloqueada $minBloqueo minutos por $max intentos fallidos", $this->ip(), 'usuario', (int) $cuenta['id']);
                    $this->mostrarBloqueo($correo, $minBloqueo);
                    return;
                }
                $quedan = $max - $r['intentos'];
                $mensaje .= ' Le queda' . ($quedan === 1 ? ' 1 intento' : "n $quedan intentos") . ' antes del bloqueo temporal.';
            }
            $bitacora->registrarEvento($cuenta ? (int) $cuenta['id'] : null, 'INICIO_SESION_FALLIDO',
                                       'Intento fallido para ' . $correo, $this->ip(), 'usuario', $cuenta['id'] ?? null);
            Sesion::flash('error', $mensaje);
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }

        // 3) Cuenta suspendida por el administrador
        if ($cuenta['estado'] !== 'HABILITADA') {
            Sesion::flash('error', 'Cuenta suspendida. Contacte al administrador');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }

        // 4) Ingreso correcto
        $usuarios->registrarAcceso((int) $cuenta['id']);
        $debeCambiar = (bool) $cuenta['debe_cambiar_contrasena'];
        unset($cuenta['contrasena_hash'], $cuenta['debe_cambiar_contrasena'], $cuenta['intentos_fallidos'], $cuenta['bloqueado_hasta']);
        Sesion::abrir($cuenta);
        $bitacora->registrarEvento((int) $cuenta['id'], 'INICIO_SESION', 'Ingreso al sistema', $this->ip(), 'usuario', (int) $cuenta['id']);
        if ($debeCambiar) {
            Sesion::flash('aviso', 'Por seguridad, cambie la contraseña inicial antes de continuar.');
            $this->redirigir('sesion/cambiar');
        }
        $this->irAPaginaPedida('panel');
    }

    /** Vuelve a la página que pidió antes de iniciar sesión (p. ej. «Reservar» del asistente), o a $porDefecto. */
    private function irAPaginaPedida(string $porDefecto): never
    {
        $pedida = (string) ($_SESSION['volver_a'] ?? '');
        unset($_SESSION['volver_a']);
        if ($pedida !== '' && str_starts_with($pedida, 'r=')) {
            header('Location: ?' . $pedida);
            exit;
        }
        $this->redirigir($porDefecto);
    }

    // ------------------------------------------------- paciente nuevo: registro en línea

    public function mostrarCrearCuenta(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $this->vista('Seguridad/IUCrearCuenta', ['d' => [], 'errores' => []], 'publica');
    }

    /**
     * Crear cuenta (extensión de CU01): cualquier adulto crea su usuario para sacar citas en línea.
     *  - CI nueva: se crea la cuenta (persona + usuario con rol PACIENTE). El expediente se abre con su primera
     *    cita «Para mí» (CU14); así quien solo saca fichas para su hijo no queda como paciente.
     *  - CI que ya está en la clínica (registrada antes por recepción): no se duplica; la cuenta se vincula a esa
     *    persona y ve de inmediato su historial y sus citas. Se exige que la fecha de nacimiento coincida.
     *  - Mayor de 18 años. La contraseña se guarda con password_hash() y se valida con password_verify().
     */
    public function crearCuenta(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $d = [];
        foreach (Persona::CAMPOS as $c) {
            $d[$c] = $this->texto($c);
        }
        $d['ci'] = strtoupper($d['ci']);
        $d['telefono'] = $d['telefono'] ?: null;
        $d['direccion'] = $d['direccion'] ?: null;
        $d['correo'] = strtolower($this->texto('correo'));
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        $errores = $this->validarRegistro($d, $contrasena, (string) ($_POST['confirmacion'] ?? ''));
        if ($errores) {
            $this->vista('Seguridad/IUCrearCuenta', ['d' => $d, 'errores' => $errores], 'publica');
            return;
        }
        $existente = (new Persona())->buscarPorCi($d['ci']);
        Conexion::transaccion(function () use ($d, $contrasena, $existente) {
            if ($existente) {                                       // ya estaba en la clínica: se vincula, no se duplica
                $id = (int) $existente['id'];
                if (!$existente['telefono'] && $d['telefono']) {
                    (new Persona())->actualizarContacto($id, $d['telefono'], $existente['direccion']);
                }
            } else {
                $id = (new Persona())->insertar($d);
            }
            $usuarioId = (new Usuario())->insertar($id, (new Rol())->obtenerIdPorNombre('PACIENTE'), $d['correo'], $contrasena, false);
            (new Bitacora())->registrarEvento($usuarioId, $existente ? 'VINCULAR_CUENTA_PACIENTE' : 'CREAR_CUENTA_EN_LINEA',
                $existente ? 'Cuenta creada y vinculada a la persona ya registrada en la clínica' : 'Cuenta creada desde el portal',
                $this->ip(), 'usuario', $usuarioId);
        });
        $cuenta = (new Usuario())->buscarPorCorreo($d['correo']);
        (new Usuario())->registrarAcceso((int) $cuenta['id']);
        unset($cuenta['contrasena_hash'], $cuenta['debe_cambiar_contrasena'], $cuenta['intentos_fallidos'], $cuenta['bloqueado_hasta']);
        Sesion::abrir($cuenta);
        if ($existente) {
            Sesion::flash('ok', 'Cuenta creada. Encontramos su registro en la clínica: su historial y sus citas ya están en su cuenta.');
            $this->redirigir('panel');
        }
        Sesion::flash('ok', 'Cuenta creada. Ya puede reservar su cita.');
        $this->irAPaginaPedida('citas/nueva');
    }

    private function validarRegistro(array $d, string $contrasena, string $confirmacion): array
    {
        $e = [];
        $obligatorios = ['ci' => 'su documento', 'nombres' => 'sus nombres', 'apellidos' => 'sus apellidos',
                         'fecha_nacimiento' => 'su fecha de nacimiento', 'telefono' => 'su celular', 'correo' => 'su correo'];
        foreach ($obligatorios as $c => $t) {
            if ((string) $d[$c] === '') {
                $e[$c] = 'Complete ' . $t . '.';
            }
        }
        if (!in_array($d['sexo'], ['F', 'M'], true)) {
            $e['sexo'] = 'Seleccione el sexo.';
        }
        $nacimiento = \DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
        if ($d['fecha_nacimiento'] !== '' && (!$nacimiento || $d['fecha_nacimiento'] <= '1900-01-01' || $d['fecha_nacimiento'] > date('Y-m-d'))) {
            $e['fecha_nacimiento'] = 'La fecha de nacimiento no es válida.';
        } elseif ($nacimiento && $nacimiento->diff(new \DateTime('today'))->y < 18) {
            $e['fecha_nacimiento'] = 'La cuenta debe ser de un mayor de edad. La cita de un menor la saca su padre, madre o tutor desde su cuenta («Para otra persona»).';
        }
        if ($d['ci'] !== '' && ($persona = (new Persona())->buscarPorCi($d['ci']))) {
            if ($persona['usuario_id']) {
                $e['ci'] = 'Ya existe una cuenta con esta CI. Inicie sesión o use «¿Olvidó su contraseña?».';
            } elseif ((string) $persona['fecha_nacimiento'] !== $d['fecha_nacimiento']) {
                $e['fecha_nacimiento'] = 'Esta CI ya está registrada en la clínica con otra fecha de nacimiento. Revísela; si es correcta, acérquese a recepción.';
            }
        }
        if ($d['correo'] !== '' && !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $e['correo'] = 'Ingrese un correo válido.';
        } elseif ($d['correo'] !== '' && (new Usuario())->existeCorreo($d['correo'])) {
            $e['correo'] = 'Ese correo ya está asociado a otra cuenta.';
        }
        if ($error = self::validarContrasena($contrasena, $confirmacion)) {
            $e['contrasena'] = $error;
        }
        return $e;
    }

    /** CU01: aviso de cuenta bloqueada con los minutos restantes; el botón «Ingresar» queda deshabilitado. */
    private function mostrarBloqueo(string $correo, int $minutos): void
    {
        Sesion::flash('aviso', 'Cuenta bloqueada temporalmente por intentos fallidos. Intente nuevamente en '
            . $minutos . ($minutos === 1 ? ' minuto' : ' minutos') . ' o recupere su contraseña.');
        $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo, 'bloqueado' => true], 'publica');
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
