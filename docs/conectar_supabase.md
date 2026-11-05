# Conectar la base de datos (Supabase)

Guía para obtener los datos que faltan y dejar el sistema funcionando. La aplicación se
conecta **directamente a PostgreSQL**; no usa las claves `anon` ni `service_role` de Supabase.

## 1. Abrir o crear el proyecto

1. Entre a <https://supabase.com/dashboard>.
2. Si el proyecto de la clínica todavía aparece, ábralo. Si fue eliminado, cree uno nuevo:
   - **New project**
   - Nombre: `clinica-vida-salud`
   - **Database Password**: escriba una contraseña y guárdela
   - Región: **East US (Ohio)** (la misma zona que Render: `ohio`)
   - Plan: Free
3. Espere a que el proyecto termine de crearse (2–3 minutos).
4. Si el proyecto estaba pausado, entre y presione **Restore**.

## 2. Copiar los seis datos

### 2.1 Abrir el proyecto

1. Entre a <https://supabase.com/dashboard> e inicie sesión.
2. En la lista de proyectos, haga clic en el de la clínica.
3. Si no aparece ningún proyecto, cree uno (**New project**) y guarde la contraseña que le pida.

### 2.2 Abrir el cuadro de conexión

1. Ya dentro del proyecto, mire la barra superior. Presione el botón **Connect**.
   También puede abrir directamente:
   `https://supabase.com/dashboard/project/_?showConnect=true&method=session`
   (si tiene varios proyectos, primero seleccione el de la clínica).
2. En la ventana que aparece, elija **Session pooler**.
   No elija *Transaction pooler* ni *Direct connection*.

### 2.3 Leer la cadena de conexión

Verá una línea parecida a esta:

```
postgresql://postgres.abcdefghijklmnop:MiClave123@aws-0-us-east-2.pooler.supabase.com:5432/postgres
```

Cada parte corresponde a una variable:

```
postgresql:// postgres.abcdefghijklmnop : MiClave123 @ aws-0-us-east-2.pooler.supabase.com : 5432 / postgres
              └──────── DB_USER ────────┘  └ DB_PASS ─┘  └────────────── DB_HOST ──────────────┘  DB_PORT  DB_NAME
```

| Variable | Parte de la cadena | Ejemplo |
|---|---|---|
| `DB_USER` | lo que está entre `//` y `:` | `postgres.abcdefghijklmnop` |
| `DB_PASS` | lo que está entre `:` y `@` | `MiClave123` |
| `DB_HOST` | lo que está entre `@` y `:5432` | `aws-0-us-east-2.pooler.supabase.com` |
| `DB_PORT` | el número después del host | `5432` |
| `DB_NAME` | lo que va después de la última `/` | `postgres` |
| `DB_SSLMODE` | no aparece en la cadena | `require` |

Si la cadena muestra `[YOUR-PASSWORD]`, reemplácelo por la contraseña real de la base.

### 2.4 Si no sabe la contraseña

1. En el proyecto: **Project Settings** (engranaje) → **Database**.
2. Busque la sección **Database password**.
3. Presione **Reset database password**, escriba una nueva y guárdela.

Esa es la contraseña de la **base de datos**, no la de su cuenta de Supabase.

> No use la sección **API Keys** ni las claves `anon` / `service_role`: esta aplicación no las
> necesita, se conecta directo a PostgreSQL.

## 3. Completar el `.env` local

Abra:

```
C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\.env
```

Y complete:

```env
APP_DEBUG=true

DB_HOST=aws-0-us-east-2.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.abcdefghijklmnop
DB_PASS=MiClaveSegura123
DB_SSLMODE=require
```

No agregue espacios ni comillas. Guarde el archivo.

## 4. Completar Render

En el servicio de Render: **Environment → Add Environment Variable**, y cargue las mismas
seis variables. Además:

| Variable | Valor |
|---|---|
| `APP_DEBUG` | `false` |
| `SESSION_DRIVER` | `cookie` |
| `APP_KEY` | La genera Render con `render.yaml`, o una cadena larga y aleatoria |
| `APP_URL` | `https://<nombre-del-servicio>.onrender.com` |

Después de guardar, Render vuelve a desplegar solo.

## 5. Cargar la base

**Si el proyecto de Supabase es nuevo (vacío):**

1. **SQL Editor → New query**.
2. Pegue todo `base_datos/base_datos_completa.sql` y presione **Run**.
3. Ese archivo ya incluye la migración del Ciclo 2; **no** hay que ejecutar `01_...sql` aparte.
4. Opcional para tener datos de prueba: pegue `base_datos/OPCIONAL_datos_de_ejemplo.sql` y **Run**.

**Si la base ya existía y solo faltan los cambios del Ciclo 2:**

1. **SQL Editor → New query**.
2. Pegue `base_datos/migraciones/01_ciclo2_estados_cita.sql` y **Run**.
3. No ejecute `base_datos_completa.sql` sobre una base con datos.

Comprobación rápida:

```sql
-- Debe dar un número parecido a 29
SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';

-- Debe devolver C, M y R
SELECT accion FROM permiso WHERE modulo = 'CITAS' ORDER BY accion;

-- Debe devolver el administrador
SELECT correo FROM usuario WHERE correo = 'admin@clinicavidasalud.com';
```

## 6. Probar la conexión

En una terminal, desde la carpeta del proyecto:

```powershell
php -S localhost:8000 -t public public/principal.php
```

Abra `http://localhost:8000` y entre con `admin@clinicavidasalud.com` / `Admin123*`.
Si entra y ve el panel, la conexión está bien.

## 7. Errores comunes

| Error | Causa | Solución |
|---|---|---|
| `tenant/user postgres.xxx not found` | Los datos son de un proyecto que ya no existe | Copiar de nuevo desde **Connect → Session pooler** |
| `password authentication failed` | Contraseña incorrecta | Resetear la contraseña de la base en Supabase |
| `could not translate host name` | Host mal escrito | Copiar el host exacto del panel |
| `connection refused` o `timeout` | Se usó *Direct connection* o un puerto incorrecto | Usar **Session pooler**, puerto `5432` |
| `Falta el archivo .env` | No existe `.env` en la raíz del proyecto | Copiar `.env.example` como `.env` |
| `relation "usuario" does not exist` | La base está vacía | Ejecutar `base_datos_completa.sql` |
| `No se puede pasar una cita de PROGRAMADA a INASISTENCIA` | Falta la migración del Ciclo 2 | Ejecutar `01_ciclo2_estados_cita.sql` |

## 8. Qué necesito yo para continuar

Con que completes el `.env` y me avises, yo verifico la conexión y sigo con las pruebas de
CU15/CU17. No necesito que me escribas la contraseña en el chat.
