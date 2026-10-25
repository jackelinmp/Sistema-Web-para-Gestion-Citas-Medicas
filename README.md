# Clínica Vida Salud — Sistema web de gestión de citas médicas

PHP 8 (MVC propio: interfaz → control → entidad) + PostgreSQL (Supabase) + Vercel.

## Ciclo 1 (disponible) y Ciclo 2 (bloqueado)
Ver `docs/ciclos_y_casos_de_uso.md`: los 12 casos de uso del Ciclo 1 (CU01 a CU06, CU08, CU09, CU11, CU13, CU14 y CU19) con su pantalla, control, interfaces, entidades y permisos.

## Poner en marcha
1. Cree la base: ejecute `base_datos/base_datos_completa.sql` en el SQL Editor de Supabase (o `psql`). Ver `base_datos/LEEME.md`.
2. Copie `.env.example` como `.env` y complete los datos de conexión.
3. Servidor local, desde esta carpeta: `php -S localhost:8000 -t public public/principal.php`
4. Entre con `admin@clinicavidasalud.com` / `Admin123*` (pide cambiar la contraseña al primer ingreso).

## Vercel
Cargue las variables `DB_*`, `APP_DEBUG`, `SESSION_DRIVER=cookie`, `APP_KEY` y, para el pago en línea, `STRIPE_SECRET_KEY` y `STRIPE_WEBHOOK_SECRET` en **Settings → Environment Variables** y despliegue desde GitHub. `api/index.php` es la entrada (`vercel.json`).

## Estructura
`app/Interfaces` (IU) · `app/Controles` (control) · `app/Entidades` (entity) · `app/Nucleo` (enrutador, sesión, RBAC, ciclos) · `config/ciclos.php` (mapa de ciclos y CU) · `public/` (css, js, principal.php) · `base_datos/` (SQL).
