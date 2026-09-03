# Guía Operativa — Importador Estandarizado de Datos

Esta guía es para quien vaya a **ejecutar una importación masiva** (Compañías, Proveedores, Empleados) usando el menú **"Importador de Datos"** del panel de administración. No requiere conocimientos técnicos, pero sí que se sigan los pasos en orden.

---

## 1. Qué es y para qué sirve

El importador toma un archivo Excel con un formato fijo (la "plantilla estandarizada") y lo procesa en dos fases separadas:

1. **Análisis (prelectura)** — lee todo el archivo y revisa que esté bien, **sin tocar la base de datos real**.
2. **Ejecución** — recién ahí, si vos confirmás, escribe los datos en el sistema.

Sirve para tres cosas: **Compañías**, **Proveedores** y **Empleados**, y para vincular **Empleados con su Proveedor** cuando ese vínculo viene en un archivo aparte.

---

## 2. El formato: la plantilla estandarizada ("excel madre")

El importador **solo** acepta archivos con la estructura exacta de `plantilla_importacion_estandarizada.xlsx`. Ese archivo tiene 5 hojas:

| Hoja | Contenido | ¿Obligatoria? |
|---|---|---|
| `LEEME_INSTRUCCIONES` | Reglas de formato, columna por columna | — (es la referencia, no se importa) |
| `companias` | Compañías | Opcional — ya existen todas las que hay hoy |
| `proveedores` | Proveedores | Sí, si vas a importar proveedores |
| `empleados` | Empleados | Sí, si vas a importar empleados |
| `relacion_empleado_proveedor` | Solo el vínculo empleado↔proveedor, sin tocar el resto de sus datos | Opcional — usar solo si el vínculo no vino ya en la hoja `empleados` |

**Reglas clave del formato** (están detalladas en la hoja `LEEME_INSTRUCCIONES` del archivo, pero las importantes para el día a día):

- **Fila 1** = encabezados (no tocar). **Fila 2** = fila de ejemplo — **hay que borrarla o reemplazarla** antes de cargar datos reales.
- Cada fila trae, si se conoce, un `external_id`: el ID que ese registro tenía en el sistema de origen. **No es obligatorio**, pero si está, el sistema lo usa para no crear duplicados si el mismo archivo (o una versión corregida) se vuelve a subir.
- Las columnas de relación (`company_external_id` / `company_identification`, `supplier_external_id` / `supplier_identification`) sirven para decir "este proveedor pertenece a esta compañía" — alcanza con completar **una de las dos**, no hace falta las dos.
- Los campos de opciones cerradas (`approval_status`, `condition`, `risk_end`) deben tener **exactamente** uno de los valores permitidos (están listados en la hoja de instrucciones). Si el valor no coincide exactamente, la fila queda marcada con error.
- Fechas: formato `AAAA-MM-DD` (ej. `2024-03-15`).
- Celdas vacías = "no sé este dato". Nunca poner `N/A`, `-`, `0` como relleno.

---

## 3. El flujo paso a paso

### Paso 1 — Subir el archivo
Menú **Importador de Datos** → **Analizar archivo**. Subís el `.xlsx`. El sistema lo lee entero y te lleva a la pantalla de revisión.

**Importante:** en este paso **no se escribe nada en la base de datos real todavía.**

### Paso 2 — Revisar

La pantalla de revisión te muestra 4 cosas:

**a) Resumen numérico**: cuántas filas en total, cuántas están OK, cuántas con advertencia, cuántas pendientes de resolver, cuántas con error bloqueante.

**b) Referencias sin resolver** (tarjetas agrupadas). Ejemplo típico: *"278 proveedores no tienen compañía identificada"*. En vez de mostrarte las 278 filas sueltas, te muestra **una tarjeta por grupo** con 2-3 nombres de ejemplo para que reconozcas de qué lote se trata. Elegís la compañía real en el selector y aplicás — resuelve todas las filas de ese grupo de una vez.

**c) Identificaciones duplicadas dentro del archivo**. Si dos filas del mismo Excel tienen la misma `identification`, se marcan acá. Podés **descargar el reporte de duplicados** (botón en esa sección) para revisarlas en Excel y decidir cuál es la correcta.

**d) Errores bloqueantes** (muestra). Filas que no se pueden importar tal cual (falta un dato obligatorio, una fecha mal formateada, un valor de selección que no existe). Estas **no** se resuelven desde la pantalla — hay que corregir el archivo origen y volver a subirlo.

### Paso 3 — Ejecutar
Botón **"Ejecutar importación"**. Recién en este momento se escribe en la base de datos real. Si quedan filas pendientes de resolver y no las querés resolver ahora, hay un checkbox para **"ejecutar igual dejándolas sin asignar"** (la referencia queda vacía, se puede completar después a mano).

### Paso 4 — Historial
Cada importación queda registrada con fecha, archivo, cantidad de filas y estado. Desde ahí se puede volver a entrar a revisar una importación pasada, o **revertirla**.

---

## 4. Qué cambia realmente en el sistema (implicancias)

Esto es lo más importante para entender **antes** de tocar "Ejecutar":

| Situación de la fila | Qué hace el sistema |
|---|---|
| La `identification` (CUIT/DNI) de la fila **no existe** todavía en el sistema | **Crea** un registro nuevo |
| La `identification` de la fila **ya existe** en el sistema | **Actualiza** ese registro existente con los datos de la fila — **pisa los valores actuales** con los del archivo, campo por campo |

Es decir: importar **no es solo agregar datos nuevos**. Si el archivo trae empleados o proveedores que ya están cargados, sus datos actuales (estatus, fechas, centro de costo, etc.) se **sobrescriben** con lo que diga el Excel. Antes de ejecutar una importación grande, conviene tener claro si el archivo origen es "más nuevo y confiable" que lo que ya está cargado en el sistema.

---

## 5. Qué tiene "deshacer" (rollback) y qué NO

En el historial hay un botón **"Revertir"** para cada importación ya ejecutada. Sirve para poder probar varias veces sin dejar basura. Pero es importante entender su alcance real:

| Acción | ¿Se puede revertir? |
|---|---|
| Registros **creados** por esa importación (compañía/proveedor/empleado nuevo) | ✅ **Sí** — "Revertir" los elimina por completo |
| Registros que la importación **actualizó** (ya existían, se les pisaron datos) | ❌ **No** — no se guarda el valor que tenían antes, así que no hay forma de restaurarlo automáticamente |

**Conclusión práctica:** "Revertir" es seguro y útil cuando estás **probando el archivo por primera vez** (mayormente van a ser altas nuevas). Es **mucho más delicado** una vez que empezás a reimportar sobre datos que ya existían en el sistema — ahí un "Revertir" solo limpia lo nuevo, pero los que se actualizaron quedan con los valores pisados.

👉 **Recomendación**: antes de ejecutar una importación grande sobre datos que ya existen, pedí un respaldo/backup de esas tablas si hay dudas. El importador no lo hace automáticamente.

---

## 6. Qué resuelve el importador solo, y qué hay que hacer a mano

### Lo resuelve el importador automáticamente:
- Vincular una fila con su padre (proveedor→compañía, empleado→proveedor) si el archivo trae el `external_id` o la `identification` correcta.
- Detectar si una fila es alta nueva o actualización de una existente (por `identification`).
- Detectar filas con formato inválido (fecha, opciones no permitidas, campos obligatorios vacíos) y bloquearlas antes de que lleguen a la base de datos.
- Agrupar automáticamente las filas que comparten el mismo problema (ej. "todas estas 278 filas no tienen compañía") para que no haya que revisarlas una por una.
- Registrar en un historial permanente la relación `external_id del sistema viejo ↔ ID real acá`, para que futuras importaciones (por ejemplo, un archivo de relación que llegue más adelante) puedan enlazar automáticamente sin volver a pedir el dato.

### Hay que hacerlo a mano:
- **Decidir a qué compañía/proveedor real corresponde** un grupo de filas huérfanas (el sistema agrupa y te muestra el selector, pero la decisión de negocio es tuya).
- **Resolver identificaciones duplicadas** dentro de un mismo archivo — el sistema las señala y te deja descargar el detalle, pero no decide cuál de las dos es la correcta; hay que corregir el archivo origen.
- **Corregir errores bloqueantes** (fecha mal formateada, campo obligatorio vacío, valor de selección inválido) — se corrigen en el Excel y se vuelve a subir. El sistema no adivina ni corrige esto solo.
- **Verificar antes de ejecutar** si el archivo va a actualizar registros existentes y si eso es lo que realmente se quiere (ver sección 4).

---

## 7. Método de trabajo con IA: de un Excel de cualquier sistema origen a la plantilla estandarizada

Cuando la información viene de otro sistema con columnas y formato distintos, el proceso NO es intentar que el importador "adivine" el mapeo. El proceso es:

### Paso A — Tener a mano el "Excel madre"
Es `plantilla_importacion_estandarizada.xlsx` (el mismo que descarga el sistema, o el archivo con las 5 hojas que ya tenemos guardado en la raíz del proyecto). Este archivo funciona como la **especificación** que la IA tiene que seguir — trae, en su hoja `LEEME_INSTRUCCIONES`, todas las reglas: qué columnas hay, cuáles son obligatorias, qué formato exacto llevan, y qué hacer en cada caso dudoso (no inventar valores, dejar vacío si no se sabe, etc.).

### Paso B — Pedirle a la IA que convierta
Le subís a una IA (Claude, ChatGPT, etc.) **dos archivos**:
1. El Excel del sistema origen (los datos "crudos").
2. El Excel madre (`plantilla_importacion_estandarizada.xlsx`).

Y el pedido es, en esencia:

> "Convertí el primer archivo a la estructura EXACTA del segundo, siguiendo al pie de la letra las reglas de su hoja LEEME_INSTRUCCIONES. No inventes ni completes ningún dato que no esté explícitamente en el archivo origen — si un dato no está, dejá la celda vacía."

### Paso C — Revisar lo que devolvió la IA (no confiar ciegamente)
Antes de subirlo al importador, conviene una revisión rápida:
- ¿Las columnas de `identification` / `external_id` se ven como texto completo (sin notación científica tipo `3E+11`, sin perder ceros a la izquierda)?
- ¿Las fechas están en formato `AAAA-MM-DD`?
- ¿Los campos de selección (`approval_status`, `condition`, `risk_end`) tienen exactamente los valores permitidos?

No hace falta revisar fila por fila — para eso está el **Paso 2 del importador** (la prelectura), que es la verdadera red de seguridad: aunque la IA se haya equivocado en algo puntual, el sistema lo va a marcar como error o pendiente antes de escribir nada en la base real.

### Paso D — Subir al importador y seguir el flujo normal
A partir de acá, es el proceso descripto en la sección 3: analizar → revisar → resolver pendientes → ejecutar.

### Resumen del método en una frase
**La IA hace la traducción de formato (de "como esté organizado en el sistema viejo" a "como lo necesita este sistema"). El importador hace la validación y la escritura segura.** Ninguna de las dos partes reemplaza a la otra: no se debe confiar en que la IA nunca se equivoca, ni se debe intentar que el importador adivine formatos no estandarizados.

---

## 8. Checklist antes de ejecutar una importación

- [ ] El archivo tiene la estructura de la plantilla (5 hojas, encabezados sin modificar).
- [ ] Se borró/reemplazó la fila 2 de ejemplo en cada hoja.
- [ ] Se revisó el resumen de la pantalla de análisis: ¿cuántas van a ser altas nuevas y cuántas actualizaciones?
- [ ] Si hay actualizaciones sobre datos existentes: ¿el archivo origen es el dato correcto y más actualizado?
- [ ] Se resolvieron (o se decidió conscientemente dejar pendientes) los grupos de referencias huérfanas.
- [ ] Se revisó el reporte de duplicados, si lo hubo.
- [ ] No quedan errores bloqueantes sin corregir.
- [ ] Si el archivo va a actualizar muchos registros existentes, se evaluó si conviene un respaldo antes de ejecutar (el "Revertir" no cubre actualizaciones).

---

## 9. Si algo sale mal

- **Antes de ejecutar**: no pasa nada, no se tocó la base de datos real. Se puede corregir el archivo y volver a subirlo, o simplemente descartar esa importación desde el historial.
- **Después de ejecutar, y eran altas nuevas**: usar "Revertir" desde el historial.
- **Después de ejecutar, y actualizó registros existentes**: el "Revertir" no va a restaurar esos valores. Hay que corregirlos a mano (o, si se hizo un respaldo antes, restaurar desde ahí).
