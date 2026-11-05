# Prueba manual de CU15 y CU17

Guía para revisar los dos casos de uso antes de seguir con CU16. Sirve también como base
para la sección 8 (Pruebas) del documento.

## 0. Antes de empezar

1. Aplicar la migración `base_datos/migraciones/01_ciclo2_estados_cita.sql` en Supabase.
2. Tener datos: al menos un paciente activo, un médico activo, una especialidad y una jornada.
   Si la base está vacía, cargar `base_datos/OPCIONAL_datos_de_ejemplo.sql`.
3. Entrar al sistema:

   - **Local:** `php -S localhost:8000 -t public public/principal.php`
     → `http://localhost:8000`
   - **Render:** `https://<nombre-del-servicio>.onrender.com`

4. Cuentas de prueba:

   | Rol | Correo | Clave |
   |---|---|---|
   | Administrador | `admin@clinicavidasalud.com` | `Admin123*` |
   | Recepcionista | `recepcion1@clinicavidasalud.com` | `Clinica123*` |
   | Médico | `veronica.salazar@clinicavidasalud.com` | `Clinica123*` |

   Las dos últimas solo existen si se cargaron los datos de ejemplo. Si no, créelas desde
   CU02 (cuentas) y CU09 (médicos).

5. Abrir la agenda: `?r=citas/agenda`.

## 1. Preparar una cita que ya pasó

La base impide agendar citas en el pasado, así que para probar **Atendida** e **Inasistencia**
hay que mover una cita existente. Ejecutar en el SQL Editor:

```sql
-- Ver qué fechas tienen citas
SELECT fecha, count(*) FROM cita GROUP BY fecha ORDER BY fecha;

-- Mover una cita CONFIRMADA a ayer a las 23:00 (para probar "Marcar atendida")
WITH elegida AS (
    SELECT id FROM cita WHERE estado = 'CONFIRMADA' ORDER BY fecha LIMIT 1
)
UPDATE cita
   SET fecha = current_date - 1, hora_inicio = '23:00', hora_fin = '23:30'
 WHERE id = (SELECT id FROM elegida)
 RETURNING codigo, fecha, hora_inicio, estado;

-- Mover una cita PROGRAMADA a ayer a las 22:30 (para probar Inasistencia desde Programada)
WITH elegida AS (
    SELECT id FROM cita WHERE estado = 'PROGRAMADA' ORDER BY fecha LIMIT 1
)
UPDATE cita
   SET fecha = current_date - 1, hora_inicio = '22:30', hora_fin = '23:00'
 WHERE id = (SELECT id FROM elegida)
 RETURNING codigo, fecha, hora_inicio, estado;
```

Anotar los códigos devueltos. Luego abrir la agenda con la **fecha de ayer**.

> Si aparece un error de horario superpuesto (`ex_cita_medico`), cambia `23:00` por otra
> hora libre, por ejemplo `21:00`, y vuelve a ejecutar solo el `UPDATE`.

> Esto es solo para la prueba. No se debe hacer en producción.

## 2. CU15 — Gestionar Estados de Cita

| # | Paso | Resultado esperado |
|---|---|---|
| 1 | Entrar como Administrador o Recepcionista y abrir `?r=citas/agenda` | Se ve la agenda del día con fecha, médico y estado |
| 2 | Buscar una cita **Programada** y presionar **Confirmar** | Mensaje «Cita … confirmada», la insignia cambia a **Confirmada** y desaparece el botón Confirmar |
| 3 | Abrir la fecha de ayer y ubicar la cita confirmada de las 23:00 | Aparece el botón **Marcar atendida** |
| 4 | Presionar **Marcar atendida** | Mensaje «… marcada como atendida» y estado **Atendida** |
| 5 | Entrar como Médico (`veronica.salazar@…`) y abrir la agenda | No aparece el selector de médico y solo se ven sus citas |
| 6 | Entrar como Recepcionista e intentar marcar atendida una cita | El botón no aparece (solo Administrador y Médico pueden) |
| 7 | Entrar como Médico e intentar confirmar una cita Programada | El botón no aparece (solo Administrador y Recepcionista pueden) |

## 3. CU17 — Registrar Inasistencia

| # | Paso | Resultado esperado |
|---|---|---|
| 1 | Abrir la agenda en la fecha de ayer | Se ven las citas movidas en el paso 1 |
| 2 | En la cita **Programada** de las 22:30, presionar **Inasistencia** | Mensaje «Inasistencia registrada…» y estado **Inasistencia** |
| 3 | Repetir con una cita **Confirmada** cuya hora ya pasó | También cambia a **Inasistencia** |
| 4 | Intentar registrar inasistencia en una cita de una hora futura | El botón no aparece; si se fuerza el POST, el sistema responde «No se puede registrar inasistencia antes de la hora de la cita» |

## 4. Verificar lo que quedó en la base

```sql
-- Últimos cambios de estado (debe haber una fila por cada acción)
SELECT c.codigo, h.estado_anterior, h.estado_nuevo, h.fecha_hora
  FROM historial_estado_cita h
  JOIN cita c ON c.id = h.cita_id
 ORDER BY h.id DESC
 LIMIT 10;

-- Bitácora: CITA_CONFIRMADA, CITA_ATENDIDA, CITA_INASISTENCIA
SELECT accion, descripcion, fecha_hora
  FROM bitacora
 WHERE accion LIKE 'CITA_%'
 ORDER BY id DESC
 LIMIT 10;

-- Evento generado para el paciente
SELECT tipo_evento, payload, fecha_evento
  FROM evento_paciente
 ORDER BY id DESC
 LIMIT 10;

-- Estado actual de las citas usadas en la prueba
SELECT codigo, fecha, hora_inicio, estado
  FROM cita
 WHERE codigo IN ('CIT-000001', 'CIT-000002')  -- reemplazar por los códigos anotados
 ORDER BY codigo;
```

## 5. Casos negativos (seguridad)

| Prueba | Cómo | Resultado esperado |
|---|---|---|
| Sin sesión | Abrir `?r=citas/agenda` en una ventana privada | Redirige al login |
| Rol sin permiso | Entrar como Paciente y abrir `?r=citas/agenda` | «Acceso no autorizado» y el intento queda en la bitácora |
| Cita de otro médico | Entrar como Médico y forzar el POST con el id de una cita ajena | «Acceso no autorizado» |
| Transición inválida | Enviar un POST con una cita ya Atendida | «La cita no admite este cambio de estado» |

## 6. Para la sección 8 del documento

Se pueden registrar así:

| Caso de prueba | Entrada | Resultado esperado | Resultado obtenido |
|---|---|---|---|
| CP-15-01 Confirmar cita | Cita Programada + Confirmar | Estado Confirmada | |
| CP-15-02 Atender cita | Cita Confirmada con hora pasada + Marcar atendida | Estado Atendida | |
| CP-15-03 Médico ve solo su agenda | Sesión de médico | Sin selector de médico y solo sus citas | |
| CP-15-04 Rol sin permiso | Sesión de recepcionista + Marcar atendida | Acción no disponible | |
| CP-17-01 Inasistencia desde Programada | Cita Programada con hora pasada + Inasistencia | Estado Inasistencia | |
| CP-17-02 Inasistencia desde Confirmada | Cita Confirmada con hora pasada + Inasistencia | Estado Inasistencia | |
| CP-17-03 Inasistencia antes de la hora | Cita futura | Acción no disponible / mensaje de error | |
| CP-15-05 Bitácora | Cualquier cambio de estado | Fila en `historial_estado_cita` y en `bitacora` | |
