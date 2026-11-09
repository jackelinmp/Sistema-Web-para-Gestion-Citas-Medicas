Clínica Vida Salud — Sistema web de gestión de citas médicas
PHP 8 (MVC propio: interfaz → control → entidad) + PostgreSQL (Supabase) + Render (Docker).
Ciclo 1 (disponible) y Ciclo 2 (en desarrollo)
Ver `docs/ciclos_y_casos_de_uso.md`: los 12 casos de uso del Ciclo 1 (CU01 a CU06, CU08, CU09, CU11, CU13, CU14 y CU19) con su pantalla, control, interfaces, entidades y permisos.
Poner en marcha
Cree la base: ejecute `base_datos/migraciones/base_datos_completa.sql` y luego las migraciones `02`, `03`, `04`, `06`, `07`, `08`, `09`, `10`, `11` y `12`
de esa misma carpeta, en ese orden (SQL Editor de Supabase o `psql`). Ver `base_datos/migraciones/LEEME.md`.
Copie `.env.example` como `.env` y complete los datos de conexión.
Servidor local, desde esta carpeta: `php -S localhost:8000 -t public public/principal.php`
Entre con `admin@clinicavidasalud.com` / `Admin123*` (pide cambiar la contraseña al primer ingreso).
Despliegue en Render (Docker)
El repositorio incluye `Dockerfile`, `.dockerignore` y `render.yaml`. En Render: New → Blueprint, elija este repositorio y complete las variables que pida (`DB_*` del Session pooler de Supabase, `APP_URL` y, si se usa el pago en línea, `STRIPE_*`). `APP_KEY` se genera sola y `SESSION_DRIVER=cookie` ya viene definida, porque el disco de Render no es permanente.
La guía paso a paso está en `docs/despliegue_render.md`.
Estructura
`app/Interfaces` (IU) · `app/Controles` (control) · `app/Entidades` (entity) · `app/Nucleo` (enrutador, sesión, RBAC, ciclos) · `config/ciclos.php` (mapa de ciclos y CU) · `public/` (css, js, principal.php) · `base_datos/` (SQL).
Usuarios, pacientes y avisos (Ciclo 2)
- **Usuario ≠ paciente.** El usuario es la cuenta web (correo o CI + contraseña, guardada con `password_hash`). El paciente es el expediente clínico. Un padre puede tener cuenta solo para manejar las citas de su hijo, sin ser paciente.
- **Crear cuenta** (`sesion/registro`): solo adultos, datos básicos. Si la CI ya está en la clínica (registrada por recepción) y la fecha de nacimiento coincide, la cuenta se vincula a esa persona: no se duplica y ve su historial y sus citas al entrar. Si la CI es nueva, el expediente se abre con su primera cita «Para mí».
- **¿Para quién es la cita?** «Para mí» abre el expediente del usuario si aún no lo tiene; «+ Para otra persona» saca ficha para un hijo, familiar u otra persona: si es nueva se registra con datos básicos (CI opcional en menores); si ya es paciente, se usa su expediente cuando la CI y la fecha de nacimiento coinciden. Los menores no tienen cuenta.
- Citas, pagos y avisos: los del usuario y sus dependientes. Historial, ficha y laboratorio: los propios y los de menores a cargo.
- Recepción también puede crearle la cuenta a un paciente (agregando su correo en «Pacientes»).
- **Avisos (CU24)**: en pantalla siempre; correo con `MAIL_*`; WhatsApp si el usuario lo activa en «Mi perfil» y se configura `WHATSAPP_*`. Recordatorio un día antes con la tarea diaria `?r=notificaciones/tarea&clave=CRON_CLAVE`.
- **Reportes (CU18, RF13)**: citas por fecha, médico, especialidad y estado; pacientes registrados; productividad, ingresos, ausentismo y carga horaria. Descarga en Excel (CSV) y PDF.
