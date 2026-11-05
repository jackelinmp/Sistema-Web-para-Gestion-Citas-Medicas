# Despliegue en Render

Guía para publicar el sistema en la nube sin Vercel. Render ejecuta el proyecto dentro de
un contenedor Docker, así que la versión de PHP y las extensiones quedan fijas y no dependen
del proveedor.

## 1. Por qué Render

| Criterio | Vercel (anterior) | Render (actual) |
|---|---|---|
| PHP | Función serverless con `vercel-php` | Contenedor Docker con PHP 8.3 y Apache |
| Archivos | Solo el front controller | Proyecto completo dentro del contenedor |
| Sesiones | Cookie cifrada | Cookie cifrada (`SESSION_DRIVER=cookie`) |
| Base de datos | Supabase | Supabase (no cambia) |
| Costo | Plan gratuito | Plan gratuito (el servicio se suspende por inactividad) |

Vercel se descartó porque su runtime de PHP es una función serverless: el proyecto no se
comporta igual que en local y el despliegue empezó a fallar. Con Docker el entorno es el mismo
en desarrollo y en producción.

## 2. Archivos que agrega el repositorio

| Archivo | Para qué sirve |
|---|---|
| `Dockerfile` | Instala PHP 8.3 + Apache, las extensiones `pdo_pgsql` y `mbstring`, y publica solo `public/` |
| `.dockerignore` | Evita subir `.git`, `.env` y archivos locales a la imagen |
| `render.yaml` | Blueprint: crea el servicio y declara las variables de entorno |

## 3. Pasos

1. Suba los cambios a GitHub (rama `dev-erika` o `main`).
2. Entre a <https://render.com> y cree una cuenta con GitHub.
3. Elija **New → Blueprint**, seleccione el repositorio del sistema y acepte `render.yaml`.
4. Complete las variables que Render solicite:

| Variable | Valor |
|---|---|
| `DB_HOST` | Host del *Session pooler* de Supabase (`aws-0-....pooler.supabase.com`) |
| `DB_PORT` | `5432` |
| `DB_NAME` | `postgres` |
| `DB_USER` | `postgres.<referencia>` |
| `DB_PASS` | Contraseña de la base |
| `DB_SSLMODE` | `require` |
| `APP_URL` | `https://<nombre-del-servicio>.onrender.com` |
| `STRIPE_SECRET_KEY` | Clave de prueba de Stripe (opcional) |
| `STRIPE_WEBHOOK_SECRET` | Secreto del webhook (opcional) |

`APP_KEY` se genera automáticamente y `SESSION_DRIVER=cookie` ya viene definida en el Blueprint.

5. Espere el primer despliegue. La URL queda como `https://<nombre-del-servicio>.onrender.com`.
6. Si usa el pago en línea, actualice el endpoint del webhook en Stripe a
   `https://<nombre-del-servicio>.onrender.com/?r=pagos/webhook`.

## 4. Base de datos

La base sigue en Supabase. Antes de probar el Ciclo 2 aplique la migración pendiente en el
SQL Editor:

```
base_datos/migraciones/01_ciclo2_estados_cita.sql
```

## 5. Verificación

1. Abra la URL y confirme que aparece la pantalla de inicio de sesión.
2. Entre con `admin@clinicavidasalud.com` / `Admin123*`.
3. Recorra un caso de uso de cada ciclo (por ejemplo CU14 agendar y CU15 confirmar).
4. Revise **Logs** en Render si alguna pantalla devuelve error 500.

## 6. Para el documento

- Sección 7 (Implementación): describir el despliegue con Docker en Render, no en Vercel.
- Sección 3.5 (Herramientas): reemplazar Vercel por Render y Docker.
- Anexos: incluir la URL del servicio y una captura del panel de Render.
