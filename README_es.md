# Formulario de Cardiología para OpenEMR

> **Compatibilidad:** OpenEMR 8.2.0 – 8.5.0
> **formdir:** `cardiology`
> **Ubicación de instalación:** `interface/forms/cardiology/`

---

## Tabla de Contenidos

1. [Descripción general](#1-descripción-general)
2. [Arquitectura](#2-arquitectura)
3. [Mapa de archivos](#3-mapa-de-archivos)
4. [Instalación](#4-instalación)
5. [Cómo funciona](#5-cómo-funciona)
6. [Cómo agregar un nuevo módulo (card)](#6-cómo-agregar-un-nuevo-módulo-card)
7. [Convenciones de archivos para un módulo](#7-convenciones-de-archivos-para-un-módulo)
8. [Reglas de visibilidad de módulos (CardRegistry)](#8-reglas-de-visibilidad-de-módulos-cardregistry)
9. [Política de borrado lógico (soft-delete)](#9-política-de-borrado-lógico-soft-delete)
10. [Limitación del hook de eliminación de OpenEMR](#10-limitación-del-hook-de-eliminación-de-openemr)
11. [Traducción al español (lang_custom.sql)](#11-traducción-al-español-lang_customsql)
12. [Lista de verificación de pruebas manuales](#12-lista-de-verificación-de-pruebas-manuales)

---

## 1. Descripción general

Este módulo agrega un formulario de **Cardiología** al sistema de consultas de OpenEMR.  
La idea central es un **contenedor genérico** (una fila por consulta) que aloja uno o más **módulos clínicos** (*cards*) de manera dinámica. Cada *card* es un tipo de estudio cardiológico independiente:

| Módulo | Código | Tabla de datos |
|---|---|---|
| Ecocardiograma Doppler | `echo` | `form_card_echo` |
| Ergometría / Prueba de Esfuerzo | `stress_test` | `form_card_stress_test` + `form_card_stress_test_stage` |

Los módulos se habilitan o deshabilitan por clínica y por usuario desde `config.php`, **sin tocar código PHP**.  
Agregar un nuevo módulo tampoco requiere modificar el contenedor.

---

## 2. Arquitectura

```
form_cardiology          (1 fila por consulta – el "contenedor")
   │
   ├── form_card_echo           (N estudios por consulta)
   └── form_card_stress_test    (N estudios por consulta)
          └── form_card_stress_test_stage  (M etapas por estudio)
```

**Un contenedor por consulta.**  
La fila del contenedor se crea de forma *lazy* (perezosa): recién cuando el usuario guarda por primera vez los campos *Motivo* / *Notas*, o al crear el primer estudio de un módulo hijo.  
Al crearse, el contenedor se registra en la tabla `forms` de OpenEMR mediante `addForm()`.

**El contenedor es genérico.**  
Nunca tiene la lista de módulos codificada de forma rígida. La grilla de módulos se construye en tiempo de ejecución desde `card_modules`, filtrada por `card_config` y las ACL.

---

## 3. Mapa de archivos

```
interface/forms/cardiology/
├── info.txt                        Nombre del formulario en OpenEMR ("Cardiology")
├── table.sql                       DDL de todas las tablas + seeds de list_options
├── lang_custom.sql                 Traducciones al Español (Argentina) para xl*()
├── new.php                         Punto de entrada (contenedor nuevo)
├── view.php                        Reabre un contenedor existente
├── save.php                        Guarda motivo/notas; crea el contenedor de forma lazy
├── report.php                      Fragmento de resumen de la consulta
├── config.php                      Admin: habilitar/deshabilitar módulos por usuario
│
├── lib/
│   ├── CardiologyContainer.php     ensureContainer(), getContainer(), softDelete()
│   └── CardRegistry.php            getVisibleCards() con filtrado por ACL y config
│
├── css/
│   └── cardiology.css              Todos los estilos (sin estilos inline en PHP)
│
├── js/
│   └── cardiology.js               Todo el JS (sin handlers inline en PHP)
│
├── templates/
│   ├── _header.php                 Barra de encabezado (paciente + consulta)
│   ├── _reason_notes.php           Campos Motivo / Notas
│   └── _card_grid.php              Grilla de módulos (genérica, dirigida por CardRegistry)
│
└── cards/
    ├── echo/
    │   ├── README.md               Stub: archivos esperados para el módulo echo
    │   ├── new.php                 Nuevo estudio de ecocardiograma
    │   ├── view.php                Ver / editar estudio existente
    │   ├── save.php                Maneja el POST de new.php / view.php
    │   └── print.php              Informe imprimible / PDF
    └── stress_test/
        ├── README.md               Stub: archivos esperados para el módulo ergometría
        ├── new.php                 Nuevo estudio de ergometría
        ├── view.php                Ver / editar estudio existente
        ├── save.php                Maneja el POST de new.php / view.php
        └── print.php              Informe imprimible / PDF
```

---

## 4. Instalación

### 4.1 Copiar los archivos

Copiar el directorio completo a:

```
<raíz de OpenEMR>/interface/forms/cardiology/
```

### 4.2 Ejecutar el SQL

Ejecutar **en orden** los siguientes scripts en la base de datos de OpenEMR:

```bash
# 1. Crear tablas y seeds de listas
mysql -u <usuario> -p <base_de_datos> < table.sql

# 2. Cargar traducciones al español (Argentina)
mysql -u <usuario> -p <base_de_datos> < lang_custom.sql
```

> [!IMPORTANT]
> `lang_custom.sql` usa `INSERT IGNORE` y `UPDATE ... WHERE definition <> ...`  
> por lo que es **seguro re-ejecutar** sin perder traducciones personalizadas previas.

### 4.3 Registrar el formulario en OpenEMR

OpenEMR registra los formularios automáticamente a partir del archivo `info.txt` que lee el administrador de formularios. Si la instalación no lo detecta automáticamente:

1. Ir a **Administración → Formularios → Gestor de Formularios**.
2. Hacer clic en **Registrar formulario**.
3. Seleccionar `cardiology` de la lista.

### 4.4 Asignar permisos ACL

Los módulos requieren el permiso `patients / med` (lectura y escritura).  
La pantalla de configuración (`config.php`) requiere `admin / super`.

---

## 5. Cómo funciona

### Abrir el formulario

1. El menú de formularios de la consulta llama a `new.php`.
2. `new.php` verifica si ya existe un contenedor activo para `(pid, encounter)` mediante `CardiologyContainer::getContainer()`.
   - **Encontrado** → redirige a `view.php?id=<containerId>`.
   - **No encontrado** → renderiza el formulario vacío (sin fila de contenedor todavía).

### Guardar Motivo / Notas

3. El usuario completa los campos y hace clic en **Guardar**.
4. `save.php` verifica el token CSRF y las ACL.
5. Si `cardiology_id` en POST es 0 → llama a `CardiologyContainer::ensureContainer()` que:
   - Inserta la fila en `form_cardiology`.
   - Llama a `addForm()` para registrar en la tabla `forms`.
6. Si `cardiology_id > 0` → llama a `updateReasonNotes()`.
7. Redirige a `view.php?id=<containerId>`.

### Abrir un módulo hijo

8. El usuario hace clic en **Nuevo** en una tarjeta de módulo.
9. Si el contenedor aún no existe (formulario nuevo sin guardar), el botón **Nuevo** es de tipo `submit` dentro del formulario del contenedor, lo que primero ejecuta `save.php` y crea el contenedor antes de abrir el módulo.
10. Si el contenedor ya existe, el enlace **Nuevo** va directamente a `cards/<code>/new.php?pid=…&encounter=…&cardiology_id=…`.

---

## 6. Cómo agregar un nuevo módulo (card)

Agregar un nuevo módulo **no requiere cambios en el código del contenedor**.

### Pasos

1. **Registrar el módulo en la base de datos** (una sola vez, vía migración o SQL manual):

   ```sql
   INSERT IGNORE INTO card_modules
     (code, title, icon, table_name, acl_section, acl_value, sort_order)
   VALUES
     ('mi_modulo', 'Mi Módulo', 'fa-plus', 'form_card_mi_modulo', 'patients', 'med', 30);
   ```

2. **Crear la carpeta del módulo** con sus archivos PHP requeridos:

   ```
   interface/forms/cardiology/cards/mi_modulo/
   ├── new.php
   ├── view.php
   ├── save.php
   └── print.php
   ```

3. **Crear la tabla del módulo** (agregar a `table.sql` o a una migración dedicada):

   ```sql
   CREATE TABLE IF NOT EXISTS form_card_mi_modulo (
     id            BIGINT NOT NULL AUTO_INCREMENT,
     cardiology_id BIGINT NOT NULL,
     pid           BIGINT NOT NULL,
     encounter     BIGINT NOT NULL,
     study_date    DATETIME NOT NULL,
     user          VARCHAR(255) DEFAULT NULL,
     status        VARCHAR(10)  NOT NULL DEFAULT 'draft',
     finalized_at  DATETIME DEFAULT NULL,
     finalized_by  VARCHAR(255) DEFAULT NULL,
     activity      TINYINT NOT NULL DEFAULT 1,
     -- ... columnas específicas del módulo ...
     PRIMARY KEY (id),
     KEY idx_cardiology_id (cardiology_id),
     KEY idx_pid_encounter (pid, encounter)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

4. **Listo.** El contenedor detectará automáticamente la carpeta y mostrará la tarjeta del módulo. No es necesario modificar ningún archivo PHP del contenedor.

---

## 7. Convenciones de archivos para un módulo

Cada módulo vive en `cards/<code>/` y debe proveer:

| Archivo | Recibe (GET) | Debe hacer |
|---|---|---|
| `new.php` | `pid`, `encounter`, `cardiology_id` | Renderizar un formulario de estudio en blanco |
| `view.php` | `pid`, `encounter`, `cardiology_id`, `id` | Renderizar un estudio existente (sólo lectura si `status = 'final'`) |
| `save.php` | POST de new/view | Verificar CSRF; verificar ACL; verificar que el contenedor esté activo; insertar/actualizar la fila del estudio; redirigir |
| `print.php` | `pid`, `encounter`, `cardiology_id`, `id` | Renderizar un informe imprimible/PDF; sin controles de edición |

**Verificaciones obligatorias en cada `save.php` de módulo:**

```php
// 1. CSRF
if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    CsrfUtils::csrfNotVerified();
}

// 2. ACL
if (!AclMain::aclCheckCore('patients', 'med', '', 'write')) {
    die(xlt('Not authorized'));
}

// 3. El contenedor padre debe estar activo
$container = CardiologyContainer::getContainerById((int)$_POST['cardiology_id']);
if ($container === null) {
    die(xlt('The cardiology record is not available or has been deleted.'));
}
```

---

## 8. Reglas de visibilidad de módulos (CardRegistry)

`CardRegistry::getVisibleCards(int $userId)` devuelve un módulo cuando se cumplen **todas** las condiciones:

| # | Regla |
|---|---|
| 1 | `card_modules.global_active = 1` |
| 2 | La activación efectiva en `card_config` está activa (la fila del usuario gana sobre la de la clínica; si ninguna existe → activo por defecto) |
| 3 | `AclMain::aclCheckCore(acl_section, acl_value)` pasa para el usuario actual |

Los módulos se ordenan por `card_modules.sort_order ASC`.

Un módulo que pasa las tres reglas pero cuya carpeta o archivos requeridos están ausentes se muestra como una **tarjeta deshabilitada** con el tooltip *«No instalado»*.

---

## 9. Política de borrado lógico (soft-delete)

### Contenedor (`form_cardiology`)

- Establecer `activity = 0`. Nunca borrar físicamente.
- Reflejar el borrado en la tabla `forms` llamando a `formDisappear()` (ver `CardiologyContainer::softDelete()`), que establece `forms.deleted = 1` y oculta el formulario del resumen de consulta.

### Estudios hijos (`form_card_*`)

- Establecer `activity = 0`. Nunca borrar físicamente.
- Los estudios hijos **no** se desactivan automáticamente cuando se borra el contenedor. Los datos quedan intactos.
- Cada `save.php` de módulo **debe** verificar `form_cardiology.activity = 1` antes de permitir escrituras. Esto evita agregar datos a un contenedor eliminado.
- `view.php` y `print.php` pueden abrir un estudio existente aunque su contenedor esté borrado lógicamente (para auditoría / revisión).

### Filas de etapas (`form_card_stress_test_stage`)

- Las filas de etapas no tienen columna `activity`; se excluyen lógicamente mediante el join con la columna `activity` del estudio padre.
- Nunca se eliminan de forma independiente.

---

## 10. Limitación del hook de eliminación de OpenEMR

OpenEMR no provee un hook formal de borrado en cascada para formularios personalizados.  
Cuando se elimina la consulta o el paciente, la fila de `forms` se marca como `deleted = 1`, pero las filas de `form_cardiology` y `form_card_*` **no se tocan automáticamente**.

**Implicación:** Si un proceso de limpieza se basa en `forms.deleted`, también debe aplicar `activity = 0` a `form_cardiology` y, opcionalmente, a las tablas hijas. Un script de mantenimiento o listener de evento podría encargarse de esto en el futuro; mientras tanto, las filas huérfanas del contenedor son inofensivas (se filtran por `activity = 1`) pero ocupan almacenamiento.

---

## 11. Traducción al español (lang_custom.sql)

El archivo `lang_custom.sql` contiene las traducciones al **Español (Argentina)** de todos los strings que aparecen en llamadas `xl()` / `xlt()` / `xla()` / `xlj()` de los archivos PHP del módulo, más los títulos de las listas de opciones registradas en `table.sql`.

### Estructura del archivo

El script sigue el mismo patrón que otros módulos del proyecto:

1. **`INSERT IGNORE INTO lang_custom`** — inserta las traducciones (sin sobrescribir las existentes).
2. **`INSERT IGNORE INTO lang_constants`** — sincroniza los strings fuente (inglés).
3. **`INSERT IGNORE INTO lang_definitions`** — vincula las traducciones.
4. **`UPDATE lang_definitions`** — actualiza las definiciones que cambiaron desde la última ejecución.

### Código de idioma

| Campo | Valor |
|---|---|
| `lang_code` | `el` |
| `lang_description` | `Spanish (Latin American)` |

> [!NOTE]
> OpenEMR usa el código `el` para Español (Latinoamérica) en muchas instalaciones regionales.  
> Si su instalación usa un código diferente, actualice el campo `lang_code` en todas las filas del INSERT.

### Cómo agregar nuevas traducciones

1. Agregar la fila en la sección `INSERT IGNORE INTO lang_custom` de `lang_custom.sql`.
2. Re-ejecutar el script: `mysql -u <usuario> -p <base> < lang_custom.sql`
3. No es necesario ejecutar la sincronización de idiomas desde el panel de administración de OpenEMR.

---

## 12. Lista de verificación de pruebas manuales

Ejecutar estos pasos en una instalación de prueba antes de desplegar en producción.

### 12.1 Abrir el formulario (contenedor nuevo)

- [ ] Abrir la consulta de un paciente.
- [ ] Hacer clic en **Cardiología** en el menú de formularios de la consulta.
- [ ] Verificar que `new.php` se renderiza sin errores PHP.
- [ ] Verificar que ambas tarjetas de módulos aparecen marcadas como **«No instalado»** con el tooltip visible al pasar el cursor.
- [ ] Verificar que el nombre del paciente y el número de consulta aparecen en el encabezado.
- [ ] Verificar que los textos de la interfaz aparecen en español.

### 12.2 Guardar Motivo / Notas

- [ ] Escribir texto en **Motivo de Consulta** y **Notas de la Consulta**.
- [ ] Hacer clic en **Guardar**.
- [ ] Verificar que `save.php` redirige a `view.php`.
- [ ] Verificar que la fila aparece en `form_cardiology` (`activity = 1`).
- [ ] Verificar que aparece una fila en `forms` (`formdir = 'cardiology'`, `deleted = 0`).
- [ ] Recargar `view.php`; verificar que el motivo y las notas están precargados.
- [ ] Verificar que el resumen de la consulta muestra la fila de cardiología.

### 12.3 Redirección de contenedor existente

- [ ] Estando en la misma consulta, abrir el formulario nuevamente desde el menú (`new.php`).
- [ ] Verificar que se redirige a `view.php` (no a un formulario vacío nuevo).
- [ ] Verificar que no hay filas duplicadas en `form_cardiology`.

### 12.4 ACL denegada

- [ ] Iniciar sesión como un usuario cuyo rol ACL **no** incluye `patients / med`.
- [ ] Navegar directamente a `new.php` o `view.php`.
- [ ] Verificar que la página muestra `«No autorizado»` y se detiene.

### 12.5 Módulo deshabilitado por usuario

- [ ] Abrir `config.php` como administrador.
- [ ] Seleccionar **Usuario específico**, elegir el usuario de prueba y **desmarcar** el módulo `echo`.
- [ ] Iniciar sesión como ese usuario y abrir una consulta cardiológica.
- [ ] Verificar que la tarjeta `echo` **no** aparece en la grilla.
- [ ] Verificar que la tarjeta `stress_test` sigue apareciendo.

### 12.6 Módulo deshabilitado globalmente

- [ ] Abrir `config.php` como administrador.
- [ ] En `card_modules`, establecer `global_active = 0` para `echo` (SQL directo o desde la pantalla de configuración cuando esa columna esté expuesta).
- [ ] Abrir una consulta cardiológica como cualquier usuario.
- [ ] Verificar que la tarjeta `echo` **no** aparece.

### 12.7 Fallo de CSRF

- [ ] Abrir las herramientas de desarrollo del navegador, copiar el HTML del formulario y enviarlo con un valor de `csrf_token_form` modificado o ausente a `save.php`.
- [ ] Verificar que la solicitud es rechazada (página de error CSRF de OpenEMR o `die()`).
- [ ] Verificar que no se inserta ninguna fila en `form_cardiology`.

### 12.8 Guardar configuración

- [ ] Abrir `config.php` como administrador.
- [ ] Cambiar un módulo entre activo/inactivo en el ámbito de clínica y guardar.
- [ ] Verificar que se inserta/actualiza una fila en `card_config` con `user_id = 0`.
- [ ] Revertir el cambio y verificar que la fila se actualiza.

### 12.9 No administrador no puede acceder a config.php

- [ ] Iniciar sesión como un usuario sin rol administrador.
- [ ] Navegar directamente a `config.php`.
- [ ] Verificar que la página muestra `«No autorizado»` y se detiene.

### 12.10 Traducción al español

- [ ] Con el idioma de la sesión configurado en **Español (Latinoamérica)**, abrir el formulario.
- [ ] Verificar que todos los textos de la UI aparecen en español:
  - Encabezado del formulario.
  - Etiquetas de los campos Motivo / Notas.
  - Títulos de las tarjetas de módulos.
  - Botones Guardar / Cancelar / Nuevo.
  - Badges de estado (Borrador / Final).
- [ ] Verificar que los títulos de las listas de opciones (grados valvulares, protocolos de ergometría, etc.) aparecen en español en los formularios de estudios.

