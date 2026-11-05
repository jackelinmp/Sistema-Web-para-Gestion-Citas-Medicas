# Clínica Vida Salud — Sistema web de gestión de citas médicas

PHP 8 (MVC propio: interfaz → control → entidad) + PostgreSQL (Supabase) + Render (Docker).

## Ciclo 1 (disponible) y Ciclo 2 (en desarrollo)
Ver `docs/ciclos_y_casos_de_uso.md`: los 12 casos de uso del Ciclo 1 (CU01 a CU06, CU08, CU09, CU11, CU13, CU14 y CU19) con su pantalla, control, interfaces, entidades y permisos.

## Poner en marcha
1. Cree la base: ejecute `base_datos/base_datos_completa.sql` en el SQL Editor de Supabase (o `psql`). Ver `base_datos/LEEME.md`.
2. Copie `.env.example` como `.env` y complete los datos de conexión.
3. Servidor local, desde esta carpeta: `php -S localhost:8000 -t public public/principal.php`
4. Entre con `admin@clinicavidasalud.com` / `Admin123*` (pide cambiar la contraseña al primer ingreso).

## Despliegue en Render (Docker)
El repositorio incluye `Dockerfile`, `.dockerignore` y `render.yaml`. En Render: **New → Blueprint**, elija este repositorio y complete las variables que pida (`DB_*` del *Session pooler* de Supabase, `APP_URL` y, si se usa el pago en línea, `STRIPE_*`). `APP_KEY` se genera sola y `SESSION_DRIVER=cookie` ya viene definida, porque el disco de Render no es permanente.
La guía paso a paso está en `docs/despliegue_render.md`.

## Estructura
`app/Interfaces` (IU) · `app/Controles` (control) · `app/Entidades` (entity) · `app/Nucleo` (enrutador, sesión, RBAC, ciclos) · `config/ciclos.php` (mapa de ciclos y CU) · `public/` (css, js, principal.php) · `base_datos/` (SQL).
