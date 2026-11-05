# Migraciones de la base de datos

Aquí se guardan los cambios de esquema del Ciclo 2. **El equipo las aplica a mano** en el
SQL Editor de Supabase; la aplicación nunca ejecuta migraciones por su cuenta.

## Orden

Se aplican en orden numérico:

| Archivo | CU | Qué cambia |
|---|---|---|
| `01_ciclo2_estados_cita.sql` | CU15, CU17 | `sp_cambiar_estado_cita` acepta `PROGRAMADA → INASISTENCIA` y agrega el permiso `CITAS/M` |

## Cómo aplicar una migración

1. Abra el proyecto en <https://supabase.com> → **SQL Editor**.
2. Copie el contenido completo del archivo `NN_descripcion.sql`.
3. Péguelo y presione **Run**.
4. Confirme que no aparece ningún error. Todos los scripts van dentro de `BEGIN … COMMIT`
   y son idempotentes: se pueden ejecutar dos veces sin romper nada.

## Verificación

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
