# Formulario de Cardiología para OpenEMR

> **Compatibilidad:** OpenEMR 8.2.0 – 8.5.0
> **formdir:** `form_cardiology`
> **Ubicación de instalación:** `interface/forms/form_cardiology/`

> [!NOTE]
> **Nombre del formdir y función de reporte:** El nombre del directorio dentro de `interface/forms/` es el `formdir` registrado (`form_cardiology`). OpenEMR deriva automáticamente el nombre de la función de reporte de la consulta a partir de este formdir siguiendo el patrón `<formdir>_report()`, que en este módulo es `form_cardiology_report()`.

---

## Tabla de Contenidos

1. [Descripción general](#1-descripción-general)
2. [Arquitectura](#2-arquitectura)
3. [Mapa de archivos](#3-mapa-de-archivos)
4. [Instalación y actualización](#4-instalación-y-actualización)
5. [Cómo funciona](#5-cómo-funciona)
6. [Especificación de módulos clínicos](#6-especificación-de-módulos-clínicos)
7. [Componente de gráficos cardiológicos](#7-componente-de-gráficos-cardiológicos)
8. [Cómo agregar un nuevo módulo (card)](#8-cómo-agregar-un-nuevo-módulo-card)
9. [Convenciones de archivos para un módulo](#9-convenciones-de-archivos-para-un-módulo)
10. [Reglas de visibilidad de módulos (CardRegistry)](#10-reglas-de-visibilidad-de-módulos-cardregistry)
11. [Política de borrado lógico (soft-delete)](#11-política-de-borrado-lógico-soft-delete)
12. [Limitación del hook de eliminación de OpenEMR](#12-limitación-del-hook-de-eliminación-de-openemr)
13. [Traducción al español (lang_custom.sql)](#13-traducción-al-español-lang_customsql)
14. [Lista de verificación de pruebas manuales](#14-lista-de-verificación-de-pruebas-manuales)
15. [Arquitectura de documentos adjuntos](#15-arquitectura-de-documentos-adjuntos-card_documents)
16. [Diálogo reutilizable de subida de documentos](#16-diálogo-reutilizable-de-subida-de-documentos)

---

## 1. Descripción general

Este módulo agrega un formulario de **Cardiología** al sistema de consultas de OpenEMR.  
La idea central es un **contenedor genérico** (una fila por consulta) que aloja uno o más **módulos clínicos** (*cards*) de manera dinámica. Cada *card* es un tipo de estudio cardiológico independiente:

| Módulo | Código | Tabla de datos |
|---|---|---|
| Ecocardiograma Doppler | `echo` | `form_card_echo` |
| Ergometría / Prueba de Esfuerzo | `stress_test` | `form_card_stress_test` + `form_card_stress_test_stage` |
| Electrocardiograma de 12 derivaciones | `ecg` | `form_card_ecg` |
| Riesgo Cardiovascular Preoperatorio | `preop_risk` | `form_card_preop_risk` |
| Certificado de Aptitud Cardiológica | `fitness` | `form_card_fitness` |

Los módulos se habilitan o deshabilitan por clínica y por usuario desde `config.php`, **sin tocar código PHP**.  
Agregar un nuevo módulo tampoco requiere modificar el contenedor.

---

## 2. Arquitectura

```
form_cardiology          (1 fila por consulta – el "contenedor")
   │
   ├── form_card_echo           (N estudios por consulta: Ecocardiograma Doppler)
   ├── form_card_stress_test    (N estudios por consulta: Ergometría graduada)
   │      └── form_card_stress_test_stage  (M etapas por estudio)
   ├── form_card_ecg            (N estudios por consulta: ECG de 12 derivaciones)
   ├── form_card_preop_risk     (N estudios por consulta: Riesgo Quirúrgico Lee RCRI)
   └── form_card_fitness        (N estudios por consulta: Certificado de Aptitud Física)
```

**Un contenedor por consulta.**  
La fila del contenedor se crea de forma *lazy* (perezosa): recién cuando el usuario guarda por primera vez los campos *Motivo* / *Notas*, o al crear el primer estudio de un módulo hijo.  
Al crearse, el contenedor se registra en la tabla `forms` de OpenEMR mediante `addForm()`.

**El contenedor es genérico.**  
Nunca tiene la lista de módulos codificada de forma rígida. La grilla de módulos se construye en tiempo de ejecución desde `card_modules`, filtrada por `card_config` y las ACL.

---

## 3. Mapa de archivos

```
interface/forms/form_cardiology/
├── info.txt                        Nombre del formulario en OpenEMR ("Cardiology")
├── table.sql                       DDL de todas las tablas + seeds de list_options (instalación inicial)
├── lang_custom.sql                 Traducciones al Español (Argentina) para xl*()
├── new.php                         Punto de entrada (contenedor nuevo)
├── view.php                        Reabre un contenedor existente
├── save.php                        Guarda motivo/notas; crea el contenedor de forma lazy
├── report.php                      Resumen genérico de la consulta (form_cardiology_report)
├── config.php                      Admin: habilitar/deshabilitar módulos por usuario
│
├── sql/
│   └── upgrade_001_new_cards.sql   Migración idempotente para instalaciones existentes (ECG, Preop, Aptitud)
│
├── lib/
│   ├── CardiologyContainer.php     ensureContainer(), getContainer(), softDelete(), transacciones, sesiones
│   ├── CardRegistry.php            getVisibleCards(), canWrite() con filtrado por ACL y config
│   ├── CardInput.php               Helper de validación y sanitización estricta (decimal, int, text, dateTime, listOption, flag)
│   └── CardCharts.php              Motor reutilizable de gráficos (evolución longitudinal y etapas)
│
├── css/
│   └── cardiology.css              Todos los estilos (sin estilos inline en PHP, incluye gráficos y print)
│
├── js/
│   ├── cardiology.js               Todo el JS del contenedor (sin handlers inline en PHP)
│   └── cardiology-charts.js        Controlador de Chart.js (accesible, adaptable a temas claro/oscuro)
│
├── templates/
│   ├── _header.php                 Barra de encabezado (paciente + consulta)
│   ├── _reason_notes.php           Campos Motivo / Notas
│   ├── _card_grid.php              Grilla de módulos (genérica, dirigida por CardRegistry)
│   └── _charts.php                 Componente genérico de gráficos (colapsable en vista, estático en print)
│
└── cards/
    ├── echo/
    │   ├── README.md               Documentación del módulo ecocardiograma
    │   ├── charts.php              Configuración de gráficos de evolución (FEVI, diámetros, PSAP, aorta)
    │   ├── new.php                 Nuevo estudio de ecocardiograma
    │   ├── view.php                Ver / editar estudio existente (con gráficos de evolución)
    │   ├── save.php                Guardado robusto con transacciones, CardInput y rangos
    │   └── print.php               Informe imprimible / PDF (con gráficos de evolución estáticos)
    ├── stress_test/
    │   ├── README.md               Documentación del módulo ergometría
    │   ├── charts.php              Configuración de gráficos de evolución y etapas (FC, METs, duración, Duke, etapas)
    │   ├── new.php                 Nuevo estudio de ergometría
    │   ├── view.php                Ver / editar estudio existente y etapas (con gráficos de etapas y evolución)
    │   ├── save.php                Guardado atómico del estudio y etapas por protocolo
    │   └── print.php               Informe imprimible / PDF con gráficos de etapas y evolución
    ├── ecg/
    │   ├── README.md               Documentación del módulo ECG
    │   ├── charts.php              Configuración de gráficos de evolución (FC, QTc, intervalos PR/QRS)
    │   ├── new.php                 Nuevo estudio de ECG de 12 derivaciones
    │   ├── view.php                Ver / editar estudio existente (con gráficos de evolución)
    │   ├── save.php                Guardado robusto con cálculo de QTc (Bazett y Fridericia)
    │   ├── print.php               Informe imprimible / PDF (con gráficos de evolución estáticos)
    │   └── ecg.js                  Cálculo interactivo en tiempo real de intervalos QTc
    ├── preop_risk/
    │   ├── README.md               Documentación del módulo de riesgo preoperatorio
    │   ├── new.php                 Nueva evaluación de riesgo preoperatorio
    │   ├── view.php                Ver / editar evaluación existente
    │   ├── save.php                Guardado robusto con cálculo de puntaje y clase Lee RCRI
    │   ├── print.php               Informe imprimible / PDF con factores de riesgo
    │   └── preop_risk.js           Cálculo interactivo en tiempo real de puntaje y clase Lee
    └── fitness/
        ├── README.md               Documentación del módulo de aptitud física
        ├── new.php                 Nuevo certificado (vigencia predefinida a +12 meses)
        ├── view.php                Ver / editar certificado existente
        ├── save.php                Guardado robusto con cálculo de IMC y reglas de vigencia
        ├── print.php               Certificado imprimible con leyenda médico-legal y matrícula
        └── fitness.js              Cálculo interactivo en tiempo real de IMC y validación de UI
```

---

## 4. Instalación y actualización

### 4.1 Copiar los archivos

Copiar el directorio completo a:

```
<raíz de OpenEMR>/interface/forms/form_cardiology/
```

### 4.2 Ejecutar el SQL

#### 4.2.1 Instalación inicial (base de datos limpia)
Ejecutar **en orden** los siguientes scripts en la base de datos de OpenEMR:

```bash
# 1. Crear contenedor base y tablas de los 5 módulos con semillas de list_options
mysql -u <usuario> -p <base_de_datos> < table.sql

# 2. Cargar traducciones al español (Argentina)
mysql -u <usuario> -p <base_de_datos> < lang_custom.sql
```

#### 4.2.2 Actualización de instalación existente (`sql/upgrade_001_new_cards.sql`)
Si ya tiene instalada la versión previa del contenedor con los módulos `echo` y `stress_test`, aplique el script de actualización incremental:

```bash
# 1. Crear tablas, módulos y opciones de lista de ECG, Riesgo Preoperatorio y Aptitud Física
mysql -u <usuario> -p <base_de_datos> < sql/upgrade_001_new_cards.sql

# 2. Actualizar traducciones
mysql -u <usuario> -p <base_de_datos> < lang_custom.sql
```

> [!NOTE]
> `sql/upgrade_001_new_cards.sql` es completamente idempotente: utiliza `CREATE TABLE IF NOT EXISTS` e `INSERT IGNORE`, por lo que puede re-ejecutarse con seguridad sin alterar datos existentes ni provocar colisiones.

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

## 6. Especificación de módulos clínicos

El módulo integra 5 tarjetas clínicas diseñadas para la práctica cardiológica ambulatoria:

| Código | Título | Tabla principal | Características clave y cálculos | Requisito para Finalizar |
|---|---|---|---|---|
| `echo` | Ecocardiograma Doppler | `form_card_echo` | Dimensiones en modo M/2D, gradientes y velocidades Doppler, valvulopatías, motilidad parietal, FEVI | `conclusion` |
| `stress_test` | Ergometría / Prueba de Esfuerzo | `form_card_stress_test`, `form_card_stress_test_stage` | Protocolos de múltiples etapas (Bruce, Bruce modificado, Naughton, Ramp, etc.), METs, Duke treadmill score (para protocolo Bruce), gráficos de etapas | `conclusion` |
| `ecg` | ECG de 12 derivaciones | `form_card_ecg` | Ritmo, intervalos de conducción (PR, QRS, QT), eje eléctrico, repolarización ventricular. Fórmulas: QTc Bazett = `QT / sqrt(60/FC)`, QTc Fridericia = `QT / cbrt(60/FC)`. Cálculo en tiempo real en `ecg.js`. | `conclusion` |
| `preop_risk` | Riesgo Cardiovascular Preoperatorio | `form_card_preop_risk` | Índice de Riesgo Cardíaco Revisado (RCRI de Lee) con 6 predictores clínicos (cirugía de alto riesgo, cardiopatía isquémica, insuficiencia cardíaca, enfermedad cerebrovascular, diabetes insulino-requiriente, creatinina > 2.0 mg/dL). Calcula puntaje (0 a 6) y Clase Lee (I a IV). Evaluación neutral sin porcentajes fijos de eventos. Cálculo en tiempo real en `preop_risk.js`. | `recommendation` Y `conclusion` |
| `fitness` | Certificado de Aptitud Cardiológica | `form_card_fitness` | Antropometría (peso, talla, ASC, cálculo automático de IMC = `kg/m²`), examen cardiovascular, resúmenes de ECG y ergometría, dictamen (`fit`, `fit_restrictions`, `unfit`, `temporary_unfit`), actividades deportivas, restricciones clínicas (obligatorias si `fit_restrictions`), fecha de vigencia `valid_until` (predefinida +12 meses, debe superar `study_date`). Impresión de certificado oficial con declaración médico-legal neutral: *"En base al examen realizado el <fecha>, el paciente se considera <dictamen>."* y número de matrícula médica (`state_license_number`). Cálculo en tiempo real en `fitness.js`. | `determination`, `valid_until`, `conclusion`, y `restrictions` (si `fit_restrictions`) |

---

## 7. Componente de gráficos cardiológicos

El módulo cuenta con un motor genérico de gráficos basado en **Chart.js** (versión 4.5.1, suministrada de fábrica en OpenEMR bajo el paquete de activos `'chart'`, cargado mediante `Header::setupHeader('chart')` / `Header::setupAssets(['chart'])`).

### 7.1 Capacidades
- **Evolución longitudinal:** Grafica la evolución temporal de los estudios previos finalizados (más el estudio abierto actual, distinguiendo si es borrador) para el paciente en sesión (hasta 60 estudios ordenados cronológicamente).
- **Tendencias de etapas intra-estudio:** En pruebas graduadas (ergometría), grafica las variables continuas (FC, PA, nivel ST) en función del tiempo transcurrido en formato `mm:ss`.
- **Interfaz y Modo Impresión PDF:** En `view.php` se visualizan dentro de un panel acordeón colapsable ("Evolución"), con paleta accesible para personas con daltonismo y adaptación a temas claro/oscuro de OpenEMR. En `print.php` se imprimen de forma estática y nítida con animaciones desactivadas y sincronización mediante `CardiologyCharts.onReady()`.

### 7.2 Formato de configuración (`charts.php`)
Cada módulo define sus métricas creando un archivo `charts.php` dentro de su carpeta:

```php
<?php

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id'    => 'lvef',
            'title' => 'Left ventricular ejection fraction',
            'unit'  => '%',
            'series' => [
                ['field' => 'lvef_pct', 'label' => 'LVEF'],
            ],
            // Opcional: 'y_min' => 0, 'y_max' => 100
        ],
        [
            'id'    => 'lv_diameters',
            'title' => 'Left ventricular diameters',
            'unit'  => 'mm',
            'series' => [
                ['field' => 'lvedd_mm', 'label' => 'LVEDD'],
                ['field' => 'lvesd_mm', 'label' => 'LVESD'],
            ],
        ],
    ],
    // Opcional para etapas intra-estudio (ergometría):
    'study' => [
        'stage_table' => 'form_card_stress_test_stage',
        'foreign_key' => 'stress_test_id',
        'x_field'     => 'elapsed_s',
        'charts'      => [
            [
                'id'    => 'stage_hr',
                'title' => 'Heart rate during the test',
                'unit'  => 'bpm',
                'series' => [
                    ['field' => 'hr_bpm', 'label' => 'HR'],
                ],
            ],
        ],
    ],
];
```

### 7.3 Cómo agregar gráficos a un nuevo módulo en 3 pasos
1. **Crear `cards/<code>/charts.php`:** Definir las métricas longitudinales y los campos de las series.
2. **Incluir en `cards/<code>/view.php`:** Agregar `Header::setupHeader('chart');` en el encabezado `<head>`, y justo después de `_form_fields.php`:
   ```php
   <?php
   $cardCode  = 'mi_modulo';
   $chartMode = 'view';
   require __DIR__ . '/../../templates/_charts.php';
   ?>
   ```
3. **Incluir en `cards/<code>/print.php`:** Agregar `\OpenEMR\Core\Header::setupAssets(['chart'])` en `<head>`, y justo antes de las conclusiones:
   ```php
   <?php
   $cardCode  = 'mi_modulo';
   $chartMode = 'print';
   require __DIR__ . '/../../templates/_charts.php';
   ?>
   ```

---

## 8. Cómo agregar un nuevo módulo (card)

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
   interface/forms/form_cardiology/cards/mi_modulo/
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

## 9. Convenciones de archivos para un módulo

Cada módulo vive en `cards/<code>/` y debe proveer:

| Archivo | Recibe (GET) | Debe hacer |
|---|---|---|
| `new.php` | `pid`, `encounter`, `cardiology_id` | Renderizar un formulario de estudio en blanco |
| `view.php` | `pid`, `encounter`, `cardiology_id`, `id` | Renderizar un estudio existente (sólo lectura si `status = 'final'`) |
| `save.php` | POST de new/view | Verificar CSRF; verificar ACL; verificar que el contenedor esté activo; insertar/actualizar la fila del estudio; redirigir |
| `print.php` | `pid`, `encounter`, `cardiology_id`, `id` | Renderizar un informe imprimible/PDF; sin controles de edición |
| `charts.php` | (Arreglo config) | Devolver métricas de evolución longitudinal y etapas |

**Verificaciones obligatorias en cada `save.php` de módulo:**

1. **Protección CSRF:** Verificación de token compatible mediante `CardiologyContainer::verifyCsrfToken()`.
2. **Permisos de escritura por módulo:** Verificación de permisos y estado del módulo con `(new CardRegistry())->canWrite($code, $userId)`.
3. **Estado y pertenencia del contenedor:** Verificación de que el contenedor existe, está activo (`activity = 1`), y coincide estrictamente con el `$pid` y `$encounter` enviados.
4. **Coherencia de sesión:** Comprobación de `$pid > 0`, `$encounter > 0` y concordancia con `CardiologyContainer::getSessionPid()` y `getSessionEncounter()`.
5. **Transacción de base de datos:** Todas las operaciones atómicas envueltas en `CardiologyContainer::startTransaction()` / `commitTransaction()` / `rollbackTransaction()`.
6. **Bloqueo de concurrencia y validación de estado:** En actualización, consultar con `SELECT id, status FROM <table> WHERE id = ? AND pid = ? AND cardiology_id = ? AND activity = 1 FOR UPDATE`. Si `status === 'final'`, abortar.
7. **Sanitización estricta de entradas:** Validación mediante métodos estáticos de `CardInput` y rangos de plausibilidad `RANGES`. Ante error, lanzar `\InvalidArgumentException` con el nombre del campo.
8. **Validación al finalizar:** Si `$action === 'save_final'`, exigir campo `conclusion` no vacío.
9. **Arreglo único `$data`:** Usar un único array asociativo para INSERT. Para UPDATE, excluir columnas de identidad (`cardiology_id`, `pid`, `encounter`, `user`, `activity`, `created_at`) y añadir cláusula `WHERE id = ? AND pid = ? AND cardiology_id = ? AND status <> 'final'`.
10. **Validación multirregistro (etapas):** Límite máximo de 100 etapas, fase permitida (`rest`, `exercise`, `recovery`), y ámbito estrictamente restringido al `stress_test_id`.

```php
// 1. CSRF
if (!CardiologyContainer::verifyCsrfToken()) {
    CsrfUtils::csrfNotVerified();
}

// 2. ACL y activación del módulo
$userId = (int)($_SESSION['authUserID'] ?? 0);
if (!(new CardRegistry())->canWrite('echo', $userId)) {
    die(xlt('Not authorized'));
}

// 3. Pertenencia del contenedor y concordancia de sesión
$container = CardiologyContainer::getContainerById((int)$_POST['cardiology_id']);
if ($container === null || (int)$container['pid'] !== $pid || (int)$container['encounter'] !== $encounter) {
    die(xlt('The container does not match the patient or encounter.'));
}
if ($pid <= 0 || $pid !== CardiologyContainer::getSessionPid() || $encounter !== CardiologyContainer::getSessionEncounter()) {
    die(xlt('Session mismatch. Please reload and try again.'));
}
```

---

## 9.1 Helper de validación de entradas (`CardInput`)

Ubicado en `lib/CardInput.php` (namespace `OpenEMR\Forms\Cardiology`, `declare(strict_types=1)`).  
Todos los métodos lanzan `\InvalidArgumentException` incluyendo el nombre del campo en el mensaje si la validación falla.

| Método | Comportamiento |
|---|---|
| `decimal(mixed $v, string $field, ?float $min, ?float $max): ?string` | Aplica trim; reemplaza comas por puntos (`55,5` → `55.5`); valida con `is_numeric()`; comprueba límites de plausibilidad; retorna `number_format($f, 3, '.', '')`. Retorna `null` si está vacío. |
| `int(mixed $v, string $field, ?int $min, ?int $max): ?int` | Aplica trim; valida con `filter_var(FILTER_VALIDATE_INT)`; comprueba rango mín/máx. Retorna `null` si está vacío. |
| `text(mixed $v): ?string` | Aplica trim; retorna `null` si está vacío. |
| `dateTime(mixed $v, string $field): string` | Valida formatos `Y-m-d\TH:i`, `Y-m-d H:i:s` y `Y-m-d H:i`; retorna `Y-m-d H:i:s`. Lanza excepción si está vacío o no es válido. |
| `listOption(string $listId, mixed $v, string $field): ?string` | Verifica que la opción exista en `list_options` para esa lista y con `activity = 1`. Retorna `null` si está vacío; lanza excepción si no existe. |
| `flag(mixed $v): int` | Convierte checkbox / booleano a `1` o `0`. |

---

## 10. Reglas de visibilidad de módulos (CardRegistry)

`CardRegistry::getVisibleCards(int $userId)` devuelve un módulo cuando se cumplen **todas** las condiciones:

| # | Regla |
|---|---|
| 1 | `card_modules.global_active = 1` |
| 2 | La activación efectiva en `card_config` está activa (la fila del usuario gana sobre la de la clínica; si ninguna existe → activo por defecto) |
| 3 | `AclMain::aclCheckCore(acl_section, acl_value)` pasa para el usuario actual |

Los módulos se ordenan por `card_modules.sort_order ASC`.

Un módulo que pasa las tres reglas pero cuya carpeta o archivos requeridos están ausentes se muestra como una **tarjeta deshabilitada** con el tooltip *«No instalado»*.

---

## 11. Política de borrado lógico (soft-delete)

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

## 12. Limitación del hook de eliminación de OpenEMR

OpenEMR no provee un hook formal de borrado en cascada para formularios personalizados.  
Cuando se elimina la consulta o el paciente, la fila de `forms` se marca como `deleted = 1`, pero las filas de `form_cardiology` y `form_card_*` **no se tocan automáticamente**.

**Implicación:** Si un proceso de limpieza se basa en `forms.deleted`, también debe aplicar `activity = 0` a `form_cardiology` y, opcionalmente, a las tablas hijas. Un script de mantenimiento o listener de evento podría encargarse de esto en el futuro; mientras tanto, las filas huérfanas del contenedor son inofensivas (se filtran por `activity = 1`) pero ocupan almacenamiento.

---

## 13. Traducción al español (lang_custom.sql)

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

## 14. Lista de verificación de pruebas manuales

Ejecutar estos pasos en una instalación de prueba antes de desplegar en producción.

### 14.1 Abrir el formulario (contenedor nuevo)

- [ ] Abrir la consulta de un paciente.
- [ ] Hacer clic en **Cardiología** en el menú de formularios de la consulta.
- [ ] Verificar que `new.php` se renderiza sin errores PHP.
- [ ] Verificar que ambas tarjetas de módulos aparecen marcadas como **«No instalado»** con el tooltip visible al pasar el cursor si faltan archivos.
- [ ] Verificar que el nombre del paciente y el número de consulta aparecen en el encabezado.
- [ ] Verificar que los textos de la interfaz aparecen en español.

### 14.2 Guardar Motivo / Notas

- [ ] Escribir texto en **Motivo de Consulta** y **Notas de la Consulta**.
- [ ] Hacer clic en **Guardar**.
- [ ] Verificar que `save.php` redirige a `view.php`.
- [ ] Verificar que la fila aparece en `form_cardiology` (`activity = 1`).
- [ ] Verificar que aparece una fila en `forms` (`formdir = 'form_cardiology'`, `deleted = 0`).
- [ ] Recargar `view.php`; verificar que el motivo y las notas están precargados.
- [ ] Verificar que el resumen de la consulta muestra la fila de cardiología.

### 14.3 Redirección de contenedor existente

- [ ] Estando en la misma consulta, abrir el formulario nuevamente desde el menú (`new.php`).
- [ ] Verificar que se redirige a `view.php` (no a un formulario vacío nuevo).
- [ ] Verificar que no hay filas duplicadas en `form_cardiology`.

### 14.4 ACL denegada

- [ ] Iniciar sesión como un usuario cuyo rol ACL **no** incluye `patients / med`.
- [ ] Navegar directamente a `new.php` o `view.php`.
- [ ] Verificar que la página muestra `«No autorizado»` y se detiene.

### 14.5 Módulo deshabilitado por usuario

- [ ] Abrir `config.php` como administrador.
- [ ] Seleccionar **Usuario específico**, elegir el usuario de prueba y **desmarcar** el módulo `echo`.
- [ ] Iniciar sesión como ese usuario y abrir una consulta cardiológica.
- [ ] Verificar que la tarjeta `echo` **no** aparece en la grilla.
- [ ] Verificar que la tarjeta `stress_test` sigue apareciendo.

### 14.6 Módulo deshabilitado globalmente

- [ ] Abrir `config.php` como administrador.
- [ ] En `card_modules`, establecer `global_active = 0` para `echo` (SQL directo o desde la pantalla de configuración cuando esa columna esté expuesta).
- [ ] Abrir una consulta cardiológica como cualquier usuario.
- [ ] Verificar que la tarjeta `echo` **no** aparece.

### 14.7 Fallo de CSRF

- [ ] Abrir las herramientas de desarrollo del navegador, copiar el HTML del formulario y enviarlo con un valor de `csrf_token_form` modificado o ausente a `save.php`.
- [ ] Verificar que la solicitud es rechazada (página de error CSRF de OpenEMR o `die()`).
- [ ] Verificar que no se inserta ninguna fila en `form_cardiology`.

### 14.8 Guardar configuración

- [ ] Abrir `config.php` como administrador.
- [ ] Cambiar un módulo entre activo/inactivo en el ámbito de clínica y guardar.
- [ ] Verificar que se inserta/actualiza una fila en `card_config` con `user_id = 0`.
- [ ] Revertir el cambio y verificar que la fila se actualiza.

### 14.9 No administrador no puede acceder a config.php

- [ ] Iniciar sesión como un usuario sin rol administrador.
- [ ] Navegar directamente a `config.php`.
- [ ] Verificar que la página muestra `«No autorizado»` y se detiene.

### 14.10 Traducción al español

- [ ] Con el idioma de la sesión configurado en **Español (Latinoamérica)**, abrir el formulario.
- [ ] Verificar que todos los textos de la UI aparecen en español:
  - Encabezado del formulario.
  - Etiquetas de los campos Motivo / Notas.
  - Títulos de las tarjetas de módulos.
  - Botones Guardar / Cancelar / Nuevo.
  - Badges de estado (Borrador / Final).
- [ ] Verificar que los títulos de las listas de opciones (grados valvulares, protocolos de ergometría, etc.) aparecen en español en los formularios de estudios.

### 14.11 Aceptación de coma decimal
- [ ] En un estudio de ecocardiograma o ergometría, ingresar un número decimal con coma (ej. `55,5` en `height_cm` o `lvedd_mm`).
- [ ] Guardar el estudio.
- [ ] Verificar que el valor se guarda y procesa como decimal numérico (`55.5` o `55.500`).

### 14.12 Rechazo de entradas no numéricas
- [ ] Ingresar texto no numérico (ej. `abc` o `12x`) en un campo numérico (ej. `weight_kg`).
- [ ] Enviar el formulario.
- [ ] Verificar que la transacción se cancela (rollback) y se muestra una pantalla limpia de `Error de Validación` identificando el campo y con un botón Volver funcional.

### 14.13 Rechazo de valores fuera de los límites de plausibilidad
- [ ] Ingresar un valor que sobrepase los límites de plausibilidad (ej. `height_cm = 300` o `lvef_pct = 95`).
- [ ] Enviar el formulario.
- [ ] Verificar que se rechaza la operación informando el límite permitido.

### 14.14 Rechazo de opción de lista inválida
- [ ] Mediante herramientas del navegador o cURL, enviar un POST con una opción inexistente para una lista (ej. `protocol = 'protocolo_inventado'`).
- [ ] Verificar que se lanza `\InvalidArgumentException` y se muestra la pantalla de error de validación.

### 14.15 Rechazo de edición sobre estudio finalizado
- [ ] Finalizar un estudio (`action = 'save_final'`).
- [ ] Enviar una petición POST intentando modificar dicho estudio (`study_id = <idFinalizado>`).
- [ ] Verificar que se bloquea la operación (`Este estudio está finalizado y no puede editarse.`).

### 14.16 Rechazo por discrepancia de paciente / consulta en el contenedor
- [ ] Enviar un POST donde `cardiology_id` pertenezca al paciente A, pero el `pid` pertenezca al paciente B.
- [ ] Verificar que se rechaza con `El contenedor no coincide con el paciente o la consulta.`.

### 14.17 Rechazo de POST directo a módulo deshabilitado
- [ ] En `config.php`, deshabilitar el módulo `echo` para el usuario actual.
- [ ] Intentar un POST directo a `cards/echo/save.php` con ese usuario.
- [ ] Verificar que se bloquea la solicitud con `No autorizado` mediante `CardRegistry::canWrite()`.

### 14.18 Rechazo de finalización sin conclusión
- [ ] Dejar el campo **Conclusión** vacío y presionar **Finalizar** (`save_final`).
- [ ] Verificar que se detiene el proceso y se muestra el mensaje `Se requiere una conclusión para finalizar el estudio.`.

### 14.19 Dependencia del cálculo de Duke según el protocolo
- [ ] En una ergometría con protocolo `Bruce` y duración `600` segundos, verificar que el Duke score se calcula y almacena.
- [ ] Modificar el protocolo a `Naughton` o `Ramp` y guardar.
- [ ] Verificar que el campo `duke_score` se almacena como `NULL`.

### 14.20 Validación de etapas de ergometría
- [ ] Enviar una fila de etapa con una fase inválida (distinta de `rest`, `exercise`, o `recovery`). Verificar que se rechaza.
- [ ] Intentar enviar más de 100 etapas en la misma solicitud. Verificar rechazo con `Se superó el límite máximo de 100 registros de etapas.`.

### 14.21 ECG: Borrador y finalización
- [ ] En una consulta activa, abrir el módulo de **ECG de 12 derivaciones** (`ecg`).
- [ ] Ingresar frecuencia cardíaca `75`, PR `160`, QRS `90`, QT `380`.
- [ ] Verificar que la vista previa en tiempo real calcula QTc Bazett `425 ms` y QTc Fridericia `409 ms`.
- [ ] Presionar **Guardar borrador**. Verificar que el estudio se guarda con `status = 'draft'` y es editable.
- [ ] Presionar **Finalizar** sin completar la conclusión. Verificar que se rechaza informando que la conclusión es obligatoria.
- [ ] Completar la conclusión y presionar **Finalizar**. Verificar que el estudio pasa a `status = 'final'` con fecha y usuario de finalización, bloqueando los campos.
- [ ] Intentar un POST directo modificando el estudio finalizado. Verificar que se bloquea con `Este estudio está finalizado y no puede editarse.`.
- [ ] Presionar **Imprimir**. Verificar que la vista imprimible/PDF muestra correctamente el encabezado, hallazgos, valores de QTc calculados y conclusión.

### 14.22 Riesgo Preoperatorio: Cálculo de puntaje RCRI y reglas de finalización
- [ ] Abrir el módulo de **Riesgo Cardiovascular Preoperatorio** (`preop_risk`).
- [ ] Marcar las casillas `Cirugía de alto riesgo` y `Cardiopatía isquémica`.
- [ ] Verificar que la interfaz calcula en tiempo real Puntaje Lee RCRI `2` y Clase `III`.
- [ ] Presionar **Guardar borrador**. Verificar que el estudio se guarda como borrador conservando puntaje y clase.
- [ ] Presionar **Finalizar** con `conclusion` completo pero `recommendation` vacío. Verificar que se rechaza exigiendo ambos campos.
- [ ] Completar la recomendación y finalizar. Verificar que el estudio se bloquea en modo solo lectura.
- [ ] Abrir la vista imprimible. Verificar que lista el procedimiento quirúrgico, los 6 predictores con sus indicadores, puntaje y clase Lee (sin porcentajes fijos de eventos), recomendaciones y conclusiones.

### 14.23 Certificado de Aptitud: Vigencia, restricciones e impresión del certificado
- [ ] Abrir el módulo de **Certificado de Aptitud Cardiológica** (`fitness`).
- [ ] Verificar que `valid_until` aparece precargado con la fecha de hoy más 12 meses.
- [ ] Ingresar talla `175` cm y peso `70` kg. Verificar cálculo en tiempo real de IMC `22.86`.
- [ ] Seleccionar dictamen `fit_restrictions` y dejar `restrictions` vacío. Presionar **Finalizar**.
- [ ] Verificar que se rechaza la operación exigiendo las restricciones clínicas específicas.
- [ ] Configurar `valid_until` con una fecha anterior a `study_date`. Presionar **Finalizar**.
- [ ] Verificar que se rechaza informando que la fecha de vigencia debe ser posterior a la fecha del estudio.
- [ ] Ingresar restricciones válidas y fecha de vigencia futura. Presionar **Finalizar**.
- [ ] Presionar **Imprimir**. Verificar que se genera el certificado oficial con la leyenda médico-legal neutral: *"En base al examen realizado el <fecha>, el paciente se considera <dictamen>."*, las actividades permitidas, vigencia y la matrícula del profesional evaluador (`state_license_number`).

### 14.24 Gráficos: Orden cronológico entre estudios del paciente
- [ ] Abrir un paciente con múltiples estudios previos finalizados de ecocardiograma o ECG.
- [ ] En `view.php`, expandir la sección **Evolución**.
- [ ] Verificar que los puntos temporales se ordenan cronológicamente de izquierda a derecha según la fecha del estudio.

### 14.25 Gráficos: Discontinuidad ante valores nulos / ausentes
- [ ] Registrar un estudio donde una métrica (ej. `pasp_mmhg` o `duke_score`) no tenga valor (NULL).
- [ ] Abrir el gráfico de evolución correspondiente.
- [ ] Verificar que la línea muestra una discontinuidad (hueco) en esa fecha en lugar de descender a cero.

### 14.26 Gráficos: Estilos de estudio en borrador y abierto actualmente
- [ ] Guardar un estudio de `ecg` o `echo` en estado **Borrador**.
- [ ] Expandir el panel de **Evolución**.
- [ ] Verificar que el punto del estudio actual se muestra con un marcador agrandado y con relleno blanco (hueco).
- [ ] Posicionar el cursor sobre el punto y comprobar que el tooltip indica `(Borrador) [Estudio actual]`.

### 14.27 Gráficos: Estado sin datos y privacidad entre pacientes
- [ ] Abrir un paciente nuevo sin estudios cardiológicos previos.
- [ ] En `view.php`, expandir **Evolución**.
- [ ] Verificar que se muestra el mensaje limpio traducido `"Sin datos para graficar"` en lugar de lienzos vacíos.
- [ ] Cambiar a otro paciente y verificar que en ningún momento se filtran puntos del paciente anterior.

### 14.28 Gráficos: Tendencias de etapas en ergometría
- [ ] Abrir un estudio de ergometría con múltiples etapas cargadas.
- [ ] En `view.php`, verificar que las tendencias por etapas aparecen primero, seguidas de los gráficos longitudinales.
- [ ] Comprobar que el eje X refleja el tiempo de esfuerzo en formato `mm:ss` y que los tooltips detallan la fase (`Reposo`, `Esfuerzo`, `Recuperación`) y el número de etapa.

### 14.29 Gráficos: Impresión en PDF y adaptación a temas visuales
- [ ] Presionar **Imprimir PDF** en un estudio de ecocardiograma, ergometría o ECG.
- [ ] Verificar que el reporte incluye los gráficos de forma estática y nítida (sin animaciones).
- [ ] Presionar el botón **Imprimir** y constatar que el cuadro de diálogo del navegador renderiza los gráficos correctamente.
- [ ] En la vista de consulta, cambiar entre tema claro y oscuro de OpenEMR y comprobar que las fuentes y líneas de cuadrícula se adaptan con legibilidad.

### 14.30 Documentos: Subida de PDF a un estudio en borrador
- [ ] En `view.php` de un ecocardiograma en borrador, hacer clic en **Subir Documentos** dentro de Documentos Adjuntos.
- [ ] Se abre el diálogo reutilizable. Arrastrar un informe PDF a la zona de carga (o hacer clic y elegir el archivo).
- [ ] Verificar que la cola muestra nombre, tamaño y tipo, y que **Quitar** funciona antes de subir.
- [ ] Presionar **Subir** y comprobar el progreso por archivo y el resultado exitoso.
- [ ] Verificar que la tabla de adjuntos y el contador se actualizan sin recargar la página.
- [ ] En la pestaña general de **Documentos** del paciente en OpenEMR, constatar que el archivo aparece archivado bajo la rama `Cardiología > Ecocardiograma`.

### 14.31 Documentos: Vincular documento existente del paciente
- [ ] En un estudio en borrador o finalizado, hacer clic en **Adjuntar Existente**.
- [ ] Seleccionar un documento del paciente no vinculado previamente y presionar **Adjuntar**.
- [ ] Verificar que el archivo queda enlazado al estudio y se muestra en la tabla.

### 14.32 Documentos: Rechazo de vinculación entre distintos pacientes
- [ ] Enviar una petición POST manipulada intentando vincular un documento del paciente B a un estudio del paciente A.
- [ ] Verificar que la API responde con error HTTP 400 (`El documento no pertenece al paciente seleccionado. La vinculación entre distintos pacientes está prohibida.`).

### 14.33 Documentos: Reglas de estado (borrador vs final)
- [ ] En un estudio en borrador, presionar **Desvincular** en un adjunto y confirmar. Verificar que se desvincula.
- [ ] Finalizar el estudio.
- [ ] Constatar que los botones de Desvincular ya no se renderizan en la interfaz.
- [ ] Enviar un POST manipulado intentando desvincular un documento del estudio finalizado. Verificar que la API lo rechaza con HTTP 422 (`No se pueden desvincular documentos de un estudio finalizado. Los registros finalizados son de solo lectura.`).
- [ ] Adjuntar un nuevo archivo o documento existente al estudio finalizado. Verificar que se permite y muestra la etiqueta `Agregado posterior a la finalización`.

### 14.34 Documentos: Verificación de permisos de acceso (ACL)
- [ ] Iniciar sesión con un usuario sin permisos sobre `patients/docs`.
- [ ] Abrir `view.php` de un estudio. Verificar que la sección muestra un aviso indicando falta de permisos y no expone botones de subida ni vinculación.
- [ ] Intentar un POST directo a `documents_api.php`. Verificar que es rechazado.

### 14.35 Documentos: Rechazo de extensiones y verificación de MIME
- [ ] Intentar subir un archivo `.exe` o `.txt`. Constatar el rechazo inmediato.
- [ ] Renombrar un ejecutable con extensión `.pdf`. Constatar que la verificación del tipo MIME real en el servidor rechaza la subida (`El tipo de contenido ... no coincide con la extensión permitida`).
- [ ] Intentar subir un archivo que exceda el límite del servidor (ej. > 50 MB). Constatar el rechazo.

### 14.36 Documentos: Protección CSRF y manejo de duplicados
- [ ] Enviar una solicitud a la API sin token CSRF o con token inválido. Verificar el rechazo HTTP 403.
- [ ] Intentar vincular dos veces el mismo documento al mismo estudio. Verificar que se devuelve un mensaje amigable: `Este documento ya se encuentra adjunto a este estudio.`.

### 14.37 Documentos: Categoría por defecto y error por categoría faltante
- [ ] Asignar `card_modules.doc_category_id = NULL` para un módulo. Adjuntar un documento y verificar que se clasifica bajo `22007 Otros Documentos de Cardiología`.
- [ ] Si tanto la categoría del módulo como la 22007 no existen en la base de datos, verificar que se muestra un error claro instruyendo al administrador a ejecutar el script de actualización y reconstruir el árbol.

### 14.38 Documentos: Registro de auditoría
- [ ] Subir un archivo, vincular un documento existente y desvincular un adjunto.
- [ ] Revisar el registro de eventos de OpenEMR. Constatar que se asientan eventos para `doc_upload`, `doc_attach_existing` y `doc_unlink` con usuario, código de módulo, ID de estudio, ID de documento e ID de categoría (sin registrar datos clínicos ni nombres de pacientes en el comentario).

### 14.39 Documentos: Reporte imprimible en PDF
- [ ] En un estudio con documentos adjuntos, presionar **Imprimir PDF**.
- [ ] Constatar que se incluye la sección **Documentos Adjuntos** con Título, Categoría y Fecha (sin volcar el contenido físico de los archivos).
- [ ] En un estudio sin documentos adjuntos, verificar que la sección no se imprime.

### 14.40 Documentos: Contador en las tarjetas del contenedor
- [ ] Volver a la consulta cardiológica (`view.php`).
- [ ] Verificar que cada estudio en la grilla muestra un badge con ícono de clip y la cantidad de documentos adjuntos junto al badge de estado, calculado en una única consulta agrupada (sin N+1).

### 14.41 Documentos: Reconstrucción del árbol de categorías
- [ ] Como superadministrador (`admin/super`), ingresar a `tools/rebuild_category_tree.php`.
- [ ] Confirmar la ejecución.
- [ ] Constatar que todas las categorías preexistentes conservan sus identificadores, padres y enlaces a documentos, y la nueva rama de Cardiología queda navegable en el árbol de Documentos.

### 14.42 Documentos: Diálogo de subida reutilizable y matriz de seguridad
- [ ] **PDF único:** abrir el diálogo desde un estudio en borrador, seleccionar un PDF, subirlo y verificar que la tabla se actualiza y la fila queda vinculada.
- [ ] **Mezcla permitidos/no permitidos:** encolar un PDF, un JPG y un `.exe`. Verificar que el `.exe` se rechaza en el diálogo y que los permitidos se pueden subir igualmente.
- [ ] **Arrastrar y soltar:** arrastrar varios archivos permitidos a la zona de carga. Verificar que se encolan, se pueden quitar antes de subir y se suben en forma secuencial.
- [ ] **Cancelar:** iniciar una subida de varios archivos y presionar **Cancelar subida** (o Escape). Verificar que la solicitud activa se detiene y el diálogo queda abierto con el resumen de cancelación.
- [ ] **Reintentar:** forzar el fallo de un archivo y luego presionar **Reintentar archivos fallidos** o el ícono de reintento de la fila. Verificar que sólo se reintentan los fallidos y no se duplican los exitosos.
- [ ] **Tamaño excedido:** intentar un archivo mayor al límite informado por el diálogo. Verificar el rechazo en el cliente y también en el servidor al saltar la validación del navegador.
- [ ] **Extensión falsificada:** renombrar un ejecutable como `.pdf` y subirlo. Verificar que la comprobación MIME real del servidor lo rechaza.
- [ ] **PID incorrecto:** enviar un POST con `pid` distinto al de la sesión activa. Verificar HTTP 403 y que no se crea ningún documento.
- [ ] **Categoría ajena:** enviar un POST con un `category_id` válido cuyo `aco_spec` el usuario no puede acceder. Verificar el rechazo y que no se crea ningún documento.
- [ ] **Estudio de otro paciente:** enviar un POST con el `study_id` de otro paciente. Verificar el rechazo y que no se crea ningún vínculo.
- [ ] **Sin `patients/docs`:** iniciar sesión sin `patients/docs`. Verificar que la sección muestra el aviso de permisos, que no aparece el botón de subida y que los POST directos se rechazan.
- [ ] **Fallo CSRF:** enviar el POST de subida sin token o con token inválido. Verificar HTTP 403 y que no se almacena ningún archivo.
- [ ] **Estudio finalizado:** subir un documento a un estudio finalizado. Verificar que la subida se permite, el vínculo se crea y la fila muestra **Agregado posterior a la finalización** al refrescar la tabla.
- [ ] **Refresco de lista:** subir dos archivos y verificar que sólo se refrescan la tabla de adjuntos y el contador (sin recargar la página), mostrando los totales traducidos.
- [ ] **Diálogo y fallback Bootstrap:** abrir el diálogo con `dlgopen()` y verificar la validación de origen y fuente. Luego quitar temporalmente `dlgopen` y verificar que el modal iframe Bootstrap 4/5 abre, recibe el resultado y se cierra.
- [ ] **OpenEMR 8.2.0 y 8.5.0:** repetir las pruebas de PDF único, resultado parcial, cancelación y Escape en ambas versiones. Verificar que no se usan comprobaciones por número de versión y que el mismo código funciona.
- [ ] **Escape y Cerrar:** presionar Escape antes de subir (el diálogo se cierra), durante la subida (la subida se cancela), y usar tanto la X del encabezado como el botón **Cerrar** inferior. Verificar que el opener siempre elimina su listener de `message`.

---

## 15. Arquitectura de documentos adjuntos (`card_documents`)

El módulo integra los estudios clínicos con el repositorio de documentos de OpenEMR mediante una tabla de unión y una jerarquía de categorías dedicada.

### 15.1 Esquema de base de datos
- **Tabla de unión:** `card_documents`
  - `id BIGINT AUTO_INCREMENT PRIMARY KEY`
  - `card_code VARCHAR(32)`: Código del módulo (ej. `echo`, `stress_test`, `ecg`, `preop_risk`, `fitness`).
  - `study_id BIGINT`: ID del estudio en la tabla correspondiente `form_card_*`.
  - `pid BIGINT`: ID del paciente (`patient_data.pid`), validado contra la sesión activa.
  - `document_id BIGINT`: ID en la tabla `documents` de OpenEMR.
  - `category_id BIGINT`: ID de categoría en `categories` asignada al vincular.
  - `created_by VARCHAR(255)`: Usuario que realizó la vinculación o subida.
  - `created_at DATETIME`: Fecha y hora de vinculación.
  - `activity TINYINT`: Borrado lógico (`1` = activo, `0` = desvinculado).
  - Índices: `UNIQUE KEY (card_code, study_id, document_id)`, `KEY (pid, card_code, study_id)`, `KEY (document_id)`.

### 15.2 Jerarquía de categorías de cardiología
Categorías sembradas bajo la raíz principal de documentos (ID `1`):
| ID Categoría | Nombre de Categoría | ID Padre | Descripción |
|---|---|---|---|
| **22000** | Cardiology | 1 | Rama raíz para toda la documentación cardiológica |
| **22001** | Echocardiogram | 22000 | Ecocardiogramas transtorácicos, transesofágicos y Doppler |
| **22002** | Stress Test | 22000 | Informes de ergometría y pruebas de esfuerzo |
| **22003** | ECG | 22000 | Electrocardiogramas de 12 derivaciones en reposo |
| **22004** | Holter / ABPM | 22000 | Monitoreos Holter de ritmo y presurometrías (MAPA) |
| **22005** | Preoperative Evaluation | 22000 | Evaluaciones y certificados de riesgo quirúrgico cardiovascular |
| **22006** | Fitness Certificates | 22000 | Certificados de aptitud física deportiva, laboral y académica |
| **22007** | Other Cardiology Documents | 22000 | Categoría por defecto para módulos adicionales o documentos varios |

### 15.3 Orden de actualización recomendado
1. **Respaldo de Base de Datos:** Realizar un respaldo de las tablas `categories`, `categories_seq` y `categories_to_documents`.
2. **Ejecutar Script SQL:** Correr `sql/upgrade_003_documents.sql` en MariaDB o MySQL.
3. **Reconstruir Árbol:** Ingresar con rol superadministrador (`admin/super`) a `tools/rebuild_category_tree.php` y ejecutar la reconstrucción para calcular los límites de recorrido preorden (`lft`, `rght`).

---

## 16. Diálogo reutilizable de subida de documentos

La sección de Documentos Adjuntos ya no contiene un formulario de subida
embebido. Las cinco vistas de estudio incluyen `templates/_documents.php`, que
abre el diálogo reutilizable de `documents_upload.php` mediante la API pública
`CardUpload` de JavaScript.

```
templates/_documents.php  →  js/cardiology-upload.js  →  documents_upload.php
                                        ↑                        │
                                        └── resultado postMessage ┘
```

### 16.1 Archivos

| Archivo | Responsabilidad |
|---|---|
| `documents_upload.php` | GET renderiza el diálogo; POST valida y almacena archivos y devuelve el JSON documentado. |
| `js/cardiology-upload.js` | API pública `CardUpload.open()`, alojamiento con `dlgopen()` o modal Bootstrap, validación de origen/fuente, limpieza del listener, `onDone`/`onClose`/`callBack`. |
| `js/cardiology-upload-dialog.js` | Arrastrar y soltar, selector de archivos, cola, quitar antes de subir, progreso XHR por archivo, reintento, cancelación, resumen, Escape/Cerrar. |
| `lib/CardDocuments.php` | `resolveUploadContext()`, `validateCategoryId()` y el único camino de almacenamiento `storeUploadedFile()`. |
| `documents_api.php` | Sólo acciones que no suben archivos: `list`, `attach_existing`, `unlink`, `list_candidates`. |

### 16.2 Diferencias deliberadas respecto del odontograma

| Comportamiento del odontograma | Diálogo de Cardiología | Motivo |
|---|---|---|
| Popup `window.open()` de 1000×600 | Modal `dlgopen()` o modal iframe Bootstrap 4/5 | Conserva el marco nativo de OpenEMR, el apilado, el redimensionado y el tema. |
| Un único input fijo `odon_doc` | Cola con varios archivos (límite del servidor, máximo 10) | Los adjuntos suelen llegar en varios archivos. |
| POST de formulario que recarga el opener | `XMLHttpRequest` + JSON + `postMessage` | Evita perder el estado de la página y permite progreso, reintento y resultados parciales. |
| Sin progreso por archivo | Barra de progreso y estado por archivo | Necesario para PDF grandes y redes clínicas lentas. |
| Sin reintento | Reintento sólo de archivos fallidos | Nunca se suben dos veces los archivos exitosos. |
| Sin control de cancelación | Cancelar aborta la solicitud activa | Evita subidas prolongadas si el usuario cambia de opinión. |
| Árbol de categorías libre en el diálogo | Parámetro `category_id` o categoría del módulo/fallback | La API valida categoría y ACL en el servidor, no en el navegador. |
| Sin token CSRF | CSRF en cada POST | Obligatorio para solicitudes que cambian estado. |
| Validación sólo en la página | Lista de extensiones, MIME real, doble extensión, tamaño y validación de paciente/categoría/estudio | El navegador sólo aconseja; el servidor es la autoridad. |
| `Document::createDocument()` y `UPDATE documents` directos | Un único método `CardDocuments::storeUploadedFile()` | Misma lógica para subidas sueltas, en borrador y en finalizado. |
| Alerta con recarga de página | `postMessage` validado por origen/fuente y `callBack` opcional | Contrato de integración documentado y reutilizable. |

### 16.3 Contrato HTTP

`GET documents_upload.php`

| Parámetro | Obligatorio | Validación |
|---|---|---|
| `pid` | Sí | Debe coincidir con `CardiologyContainer::getSessionPid()`. |
| `category_id` | No | Debe existir en `categories`; su `aco_spec` se valida con ACL del núcleo. Si se omite, se usa la categoría del módulo o la 22007. |
| `card_code` | En par | Debe enviarse junto con `study_id`. |
| `study_id` | En par | Debe pertenecer a `pid`, estar activo y pasar `CardRegistry::canWrite()`. |
| `multiple` | No | `1` (predeterminado) o `0`. |

`GET` devuelve el HTML del diálogo. `POST` acepta `multipart/form-data`:

| Campo | Obligatorio | Notas |
|---|---|---|
| `csrf_token_form` | Sí | También se acepta `X-CSRF-Token`. |
| `pid` | Sí | Misma validación de sesión que en GET. |
| `files[]` | Sí | Uno o más archivos, máximo `CardDocuments::MAX_FILES_PER_REQUEST` (10). |
| `category_id`, `card_code`, `study_id`, `multiple` | No | Misma validación que en GET. |

La respuesta del POST es siempre JSON:

```json
{
  "source": "cardiology-upload",
  "status": "ok",
  "documents": [
    {
      "id": 1234,
      "name": "informe-ecocardiograma.pdf",
      "category_id": 22001,
      "linked": true
    }
  ],
  "errors": []
}
```

`status` puede ser:

- `ok` — todos los archivos se almacenaron (y vincularon cuando se enviaron `card_code` + `study_id`).
- `partial` — al menos un archivo se almacenó y al menos uno falló.
- `error` — ningún archivo se almacenó; revisar `errors`.

Cada error tiene la forma `{ "name": "archivo.pdf", "message": "mensaje traducido" }`.

Códigos HTTP: `200` para resultados `ok`/`partial`/`error` producidos tras inspeccionar archivos, `400` para parámetros inválidos o ausencia de archivos, `403` para fallos de CSRF, sesión o ACL, `422` para fallos del servicio y `500` para errores inesperados. El diálogo muestra el mensaje de `errors` sin importar el código.

No se acepta del request ningún nombre de función callback, fragmento de JavaScript, nombre de tabla, nombre de columna ni identificador de referencia externa.

### 16.4 Contrato `postMessage`

Tras completar un lote, el diálogo envía exactamente (automáticamente si todos los archivos se subieron correctamente, o cuando el usuario cierra el diálogo tras un resultado parcial o con errores):

```js
window.parent.postMessage({
  source: 'cardiology-upload',
  status: 'ok' | 'partial' | 'error',
  documents: [{ id, name, category_id, linked }],
  errors: [{ name, message }]
}, window.location.origin);
```

El receptor debe validar `event.origin` (igual a `window.location.origin`) y
`event.source` (la ventana del iframe del diálogo). `CardUpload.open()` lo hace
y además soporta un callback del lado del opener:

```js
function alCambiar(payload) {
  if (!payload || !payload.documents) return;
  console.log(payload.documents.length, payload.errors.length);
}

CardUpload.open({
  pid: 42,
  cardCode: 'echo',
  studyId: 7,
  multiple: 1,
  callBack: 'alCambiar',
  onDone: alCambiar,
  onClose: function () { /* se ejecuta al quitar el diálogo */ }
});
```

Si `postMessage` no está disponible, el diálogo mantiene visible el resumen
final y ofrece un botón **Cerrar** en lugar de fallar en silencio.

### 16.5 Asset de Header y ejemplo de integración

`dlgopen()` proviene del asset `dialog` de OpenEMR. Al integrar el cargador en
otro script, incluirlo junto con jQuery y Bootstrap:

```php
<?php
use OpenEMR\Core\Header;

require_once __DIR__ . '/lib/CardiologyContainer.php';

$uploadUrl = \OpenEMR\Forms\Cardiology\CardiologyContainer::getFormRootUrl() . '/documents_upload.php';
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['dialog', 'jquery', 'bootstrap']); ?>
</head>
<body>
    <button type="button" id="abrir-subida" data-pid="<?php echo attr((string)$pid); ?>">
        <?php echo xlt('Upload documents'); ?>
    </button>

    <script src="<?php echo attr($GLOBALS['webroot']); ?>/interface/forms/form_cardiology/js/cardiology-upload.js"></script>
    <script>
    document.getElementById('abrir-subida').addEventListener('click', function () {
        window.CardUpload.open({
            pid: this.getAttribute('data-pid'),
            categoryId: 22001,
            cardCode: 'echo',
            studyId: <?php echo (int)$studyId; ?>,
            url: <?php echo json_encode($uploadUrl); ?>,
            multiple: 1,
            onDone: function (payload) {
                if (!payload || !payload.documents) return;
                console.log('Almacenados:', payload.documents.length);
                console.log('Fallidos:', payload.errors.length);
            }
        });
    });
    </script>
</body>
</html>
```

El diálogo obtiene sus límites de `documents_upload.php`; no codificar a mano el
tamaño máximo ni la cantidad de archivos en la página que lo integra. Los tipos
permitidos, el tamaño máximo y la cantidad máxima por solicitud se generan en el
servidor.

### 16.6 Notas de seguridad del servidor

- Cada POST verifica CSRF mediante `CardiologyContainer::verifyCsrfToken()`.
- `pid` debe coincidir con la sesión activa del paciente; no se pueden vincular estudios de otros pacientes.
- Se exige `patients/docs`; las subidas vinculadas además exigen `CardRegistry::canWrite()` para el módulo.
- Un `category_id` enviado debe existir y su `aco_spec` debe pasar la ACL del núcleo.
- Sólo se aceptan `pdf`, `jpg`, `jpeg`, `png`, `tif` y `tiff`.
- Primero se valida la extensión y luego se detecta el tipo de contenido real con fileinfo. Se rechazan las dobles extensiones que ocultan ejecutables.
- El límite efectivo es el menor entre el techo del módulo (50 MB), `upload_max_filesize` y `post_max_size`; la solicitud se limita a 10 archivos. El diálogo muestra el límite efectivo informado por el servidor.
- Los nombres visibles se sanitizan (componentes de ruta, caracteres de control y longitud excesiva) antes de llegar a `Document::createDocument()`.
- Los documentos se crean únicamente mediante la clase `Document` del núcleo bajo la categoría validada. Todo el SQL usa sentencias preparadas y ningún nombre de tabla o columna proviene del request.
- Cada archivo almacenado o vinculado se audita (`doc_upload`) con módulo, estudio, documento, categoría y tamaño, nunca con contenido clínico.
- La respuesta se codifica en JSON y toda la salida del diálogo se escapa.

### 16.7 Compatibilidad con OpenEMR 8.2.0 – 8.5.0

Se usa detección de características; no hay comprobaciones por número de versión.

- **`dlgopen`** existe en 8.2.0 y 8.5.0. 8.5.0 quita `resolvePromiseOn` de sus valores predeterminados, por lo que este módulo nunca lo pasa ni depende de él. Si `dlgopen` no existe, se usa el modal iframe Bootstrap.
- **`Header::setupHeader()`** acepta claves de assets en ambas versiones. `dialog`, `jquery`, `bootstrap` y `utility` se cargan automáticamente en los layouts que usa este formulario. Al integrar en otro script, pedir `dialog` explícitamente.
- **Bootstrap / jQuery:** 8.2.0 y 8.5.0 incluyen Bootstrap 4.6.2 y jQuery 3.7.1; el marcado también tolera clases de Bootstrap 5.
- **`Document::createDocument()`** tiene la misma firma en ambas versiones, incluidos `foreign_reference_id` y `foreign_reference_table`, y `get_id()` existe en ambas.
- **`CsrfUtils`** requiere un argumento de sesión en ambas versiones. El módulo centraliza las llamadas en `CardiologyContainer`, que refleja la firma del método para aceptar ambos órdenes de argumentos.
- **`AclMain`** expone los mismos métodos ACL; 8.5.0 agrega principalmente tipos de retorno.
- **Accesores de sesión** se resuelven con `CardiologyContainer::getSessionPid()`, que usa las utilidades del núcleo cuando existen y cae a `$_SESSION` / `$GLOBALS`.
- **URL de recuperación de documentos** se mantiene igual en ambas versiones: `<webroot>/controller.php?document&retrieve&patient_id=…&as_file=false&document_id=…`.

### 16.8 Precaución sobre reutilización entre repositorios

El diálogo es reutilizable dentro de este módulo, pero no es una biblioteca
estable para compartir a largo plazo entre repositorios no relacionados. Su
formdir, IDs de categoría, expectativas de ACL, URL raíz y esquema
`card_documents` pertenecen a `form_cardiology`. Si otro módulo necesita la misma
experiencia, conviene copiar el contrato y adaptar las rutas y la resolución de
categorías allí, en lugar de apuntar dos repositorios a un archivo que cambia.


