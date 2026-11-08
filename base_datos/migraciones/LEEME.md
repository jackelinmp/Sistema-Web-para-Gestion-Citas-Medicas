# Migraciones de la base de datos

Aquí se guardan los cambios de esquema del Ciclo 2. **El equipo las aplica a mano** en el
SQL Editor de Supabase; la aplicación nunca ejecuta migraciones por su cuenta.

## Orden

Se aplican en orden numérico:

| Archivo | CU | Qué cambia |
|---|---|---|
| `01_ciclo2_estados_cita.sql` | CU15, CU17 | `sp_cambiar_estado_cita` acepta `PROGRAMADA → INASISTENCIA` y agrega el permiso `CITAS/M` |
| `02_ciclo2_grupo1.sql` | CU16, CU18, CU24, CU25, CU26 | funciones `sp_reprogramar_cita`, vista de detalle, permisos de COMUNICACION y tablas de soporte |
| `03_ciclo2_copago_seguro.sql` | CU19 | copago con seguro médico y datos de póliza |
| `04_pre_registro_paciente.sql` | CU05 | estado `PRE_REGISTRADO` (ya no lo usa el código; si ya la aplicó, no pasa nada) |
| `06_tutor_dependientes.sql` | CU22 | `persona.tutor_id` para dependientes |
| `07_resultado_archivo.sql` | CU23 | archivo adjunto del resultado de laboratorio |
| `08_preguntas_frecuentes_base.sql` | CU26 | preguntas frecuentes iniciales del asistente |
| `09_catalogos_base.sql` | CU05, CU19, CU23 | aseguradoras y tipos de examen sin pantalla de alta |
| `10_permisos_ciclo2.sql` | RBAC | permisos de PAGOS, EXAMENES, REPORTES y COMUNICACION para los 4 roles |
| `11_avisos_whatsapp.sql` | CU24 | `usuario.avisos_whatsapp`: el titular elige en «Mi perfil» si recibe los avisos también por WhatsApp |
| `12_responsable_sin_expediente.sql` | CU01, CU22 | `vinculo_familiar.paciente_id` apunta a `persona`: un padre con cuenta no necesita ser paciente |

> No existe la `05`: era la verificación por OTP, que se retiró por costo. Si alguna vez
> se ejecutó, se puede borrar la tabla con `DROP TABLE IF EXISTS otp_verificacion;`.

## Cómo aplicar una migración

1. Abra el proyecto en <https://supabase.com> → **SQL Editor**.
2. Copie el contenido completo del archivo `NN_descripcion.sql`.
3. Péguelo y presione **Run**.
4. Confirme que no aparece ningún error. Todos los scripts van dentro de `BEGIN … COMMIT`
   y son idempotentes: se pueden ejecutar dos veces sin romper nada.

## Verificación

Después de la `11`:

```sql
-- Debe devolver una fila (boolean, por defecto false)
SELECT data_type, column_default FROM information_schema.columns
 WHERE table_name = 'usuario' AND column_name = 'avisos_whatsapp';
```


Después de la `10`:

```sql
-- Debe listar C y R, y también los módulos del Ciclo 2
SELECT modulo, accion FROM permiso ORDER BY modulo, accion;

-- El paciente debe tener PAGOS C/R (si no, CU14 no puede cobrar)
SELECT p.modulo, p.accion
  FROM rol r
  JOIN rol_permiso rp ON rp.rol_id = r.id
  JOIN permiso p ON p.id = rp.permiso_id
 WHERE r.nombre = 'PACIENTE'
 ORDER BY p.modulo, p.accion;
```

Después de la `01`:

```sql
-- Debe devolver C, R y M
SELECT accion FROM permiso WHERE modulo = 'CITAS' ORDER BY accion;

-- Debe listar Administrador, Recepcionista y Médico
SELECT r.nombre
  FROM rol r
  JOIN rol_permiso rp ON rp.rol_id = r.id
  JOIN permiso p ON p.id = rp.permiso_id
 WHERE p.modulo = 'CITAS' AND p.accion = 'M'
 ORDER BY r.nombre;

-- La función debe permitir PROGRAMADA -> INASISTENCIA
SELECT prosrc FROM pg_proc WHERE proname = 'sp_cambiar_estado_cita';
```

## Reglas para las próximas migraciones

1. Nombre `NN_descripcion_corta.sql`, con el número siguiente al último aplicado.
2. Solo cambios aditivos: `CREATE`, `ALTER TABLE ... ADD`, `CREATE OR REPLACE`.
   Nunca `DROP`, `RENAME` ni cambios de tipo.
3. Cada archivo debe poder ejecutarse dos veces sin error (`IF NOT EXISTS`,
   `ON CONFLICT DO NOTHING`).
4. Se registra en `docs/bitacora_cambios.md` con el CU y la sección del documento que cambia.
5. Se aplica **antes** de subir el código que la usa.

> Para una base nueva no hace falta aplicar migraciones: `base_datos/base_datos_completa.sql`
> ya incluye todos estos cambios.
