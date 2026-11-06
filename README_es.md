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
│   ├── upgrade_001_new_cards.sql   Migración idempotente para instalaciones existentes (ECG, Preop, Aptitud)
│   └── upgrade_004_cards_batch2.sql Migración idempotente para anticoag, hf_followup y holter
│
├── lib/
│   ├── CardiologyContainer.php     ensureContainer(), getContainer(), softDelete(), transacciones, sesiones
│   ├── CardRegistry.php            getVisibleCards(), canWrite() con filtrado por ACL y config
│   ├── CardInput.php               Helper de validación y sanitización estricta (decimal, int, text, dateTime, listOption, flag)
│   ├── CardAnticoag.php            Cálculo de tiempo en rango terapéutico (TTR) por método de Rosendaal
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
    ├── fitness/
    │   ├── README.md               Documentación del módulo de aptitud física
    │   ├── new.php                 Nuevo certificado (vigencia predefinida a +12 meses)
    │   ├── view.php                Ver / editar certificado existente
    │   ├── save.php                Guardado robusto con cálculo de IMC y reglas de vigencia
    │   ├── print.php               Certificado imprimible con leyenda médico-legal y matrícula
    │   └── fitness.js              Cálculo interactivo en tiempo real de IMC y validación de UI
    ├── anticoag/
    │   ├── charts.php              Gráficos de evolución de INR/rango objetivo y dosis semanal
    │   ├── new.php                 Nuevo control de anticoagulación (precargado del último estudio final)
    │   ├── view.php                Ver / editar estudio con panel TTR de Rosendaal
    │   ├── save.php                Guardado robusto con cálculo de in_range y dose_change_pct
    │   ├── print.php               Informe imprimible / PDF con TTR y documentos adjuntos
    │   └── _form_fields.php        Campos de formulario compartidos
    ├── hf_followup/
    │   ├── charts.php              Gráficos de peso, NYHA, péptidos natriuréticos, creatinina y potasio
    │   ├── new.php                 Nuevo seguimiento de insuficiencia cardíaca (precargado del último estudio final)
    │   ├── view.php                Ver / editar estudio con gráficos de evolución
    │   ├── save.php                Guardado robusto con cálculo de nyha_numeric y weight_change_kg
    │   ├── print.php               Informe imprimible / PDF con documentos adjuntos
    │   └── _form_fields.php        Campos de formulario compartidos
    └── holter/
        ├── charts.php              Gráficos de frecuencia cardíaca, carga de PVC/FA y pausas
        ├── new.php                 Nuevo estudio de Holter
        ├── view.php                Ver / editar estudio con gráficos de evolución
        ├── save.php                Guardado robusto con cálculo de pvc_burden_pct
        ├── print.php               Informe imprimible / PDF con documentos adjuntos
        └── _form_fields.php        Campos de formulario compartidos
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
# 1. Crear contenedor base y tablas de los 8 módulos con semillas de list_options
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

#### 4.2.3 Actualización de instalación existente (`sql/upgrade_004_cards_batch2.sql`)
Para agregar los módulos del lote 2 (`anticoag`, `hf_followup`, `holter`) a una instalación que ya tiene el contenedor y los módulos anteriores:

```bash
# 1. Crear tablas, módulos y opciones de lista de anticoagulación, insuficiencia cardíaca y Holter
mysql -u <usuario> -p <base_de_datos> < sql/upgrade_004_cards_batch2.sql

# 2. Actualizar traducciones
mysql -u <usuario> -p <base_de_datos> < lang_custom.sql
```

> [!NOTE]
> `sql/upgrade_004_cards_batch2.sql` es idempotente y sólo usa `CREATE TABLE IF NOT EXISTS` e `INSERT IGNORE`; no altera tablas, listas ni semillas existentes. El mismo DDL y las mismas semillas están agregados a `table.sql` para instalaciones nuevas.

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

El módulo integra 8 tarjetas clínicas diseñadas para la práctica cardiológica ambulatoria:

| Código | Título | Tabla principal | Características clave y cálculos | Requisito para Finalizar |
|---|---|---|---|---|
| `echo` | Ecocardiograma Doppler | `form_card_echo` | Dimensiones en modo M/2D, gradientes y velocidades Doppler, valvulopatías, motilidad parietal, FEVI | `conclusion` |
| `stress_test` | Ergometría / Prueba de Esfuerzo | `form_card_stress_test`, `form_card_stress_test_stage` | Protocolos de múltiples etapas (Bruce, Bruce modificado, Naughton, Ramp, etc.), METs, Duke treadmill score (para protocolo Bruce), gráficos de etapas | `conclusion` |
| `ecg` | ECG de 12 derivaciones | `form_card_ecg` | Ritmo, intervalos de conducción (PR, QRS, QT), eje eléctrico, repolarización ventricular. Fórmulas: QTc Bazett = `QT / sqrt(60/FC)`, QTc Fridericia = `QT / cbrt(60/FC)`. Cálculo en tiempo real en `ecg.js`. | `conclusion` |
| `preop_risk` | Riesgo Cardiovascular Preoperatorio | `form_card_preop_risk` | Índice de Riesgo Cardíaco Revisado (RCRI de Lee) con 6 predictores clínicos (cirugía de alto riesgo, cardiopatía isquémica, insuficiencia cardíaca, enfermedad cerebrovascular, diabetes insulino-requiriente, creatinina > 2.0 mg/dL). Calcula puntaje (0 a 6) y Clase Lee (I a IV). Evaluación neutral sin porcentajes fijos de eventos. Cálculo en tiempo real en `preop_risk.js`. | `recommendation` Y `conclusion` |
| `fitness` | Certificado de Aptitud Cardiológica | `form_card_fitness` | Antropometría (peso, talla, ASC, cálculo automático de IMC = `kg/m²`), examen cardiovascular, resúmenes de ECG y ergometría, dictamen (`fit`, `fit_restrictions`, `unfit`, `temporary_unfit`), actividades deportivas, restricciones clínicas (obligatorias si `fit_restrictions`), fecha de vigencia `valid_until` (predefinida +12 meses, debe superar `study_date`). Impresión de certificado oficial con declaración médico-legal neutral: *"En base al examen realizado el <fecha>, el paciente se considera <dictamen>."* y número de matrícula médica (`state_license_number`). Cálculo en tiempo real en `fitness.js`. | `determination`, `valid_until`, `conclusion`, y `restrictions` (si `fit_restrictions`) |
| `anticoag` | Control de Anticoagulación | `form_card_anticoag` | Indicación, antagonista de vitamina K, tipo de muestra de INR, INR medido y rango objetivo, dosis semanal y cambio de dosis calculado, adherencia, eventos hemorrágicos/trombóticos, fecha de próximo control. Valores calculados: `in_range` (`in`, `below`, `above`) y `dose_change_pct = (nueva − actual) / actual × 100`. La vista y la impresión también muestran el TTR de Rosendaal. | `inr_value`, rango objetivo bajo/alto, `next_control_date` y `conclusion` |
| `hf_followup` | Seguimiento de Insuficiencia Cardíaca | `form_card_hf_followup` | Clase NYHA, tipo/etiología/estadio de IC, estado volémico y signos vitales, hallazgos de congestión, FEVI y laboratorio, indicadores de tratamiento dirigido, hospitalizaciones/urgencias, educación, plan. Valores calculados: `nyha_numeric` (I–IV → 1–4) y `weight_change_kg` respecto del estudio final anterior. | `nyha_class` y `conclusion` |
| `holter` | Estudio de Monitor Holter | `form_card_holter` | Duración del registro, latidos totales, frecuencia cardíaca mínima/media/máxima y horarios, ritmo dominante (`card_ecg_rhythm`), carga de FA y de latidos estimulados, pausas, recuentos de PVC/PAC, dobletes, rachas de TVNS/TSV, episodios de ST, bloqueo AV, síntomas y correlación, hallazgos. Valor calculado: `pvc_burden_pct = pvc_total / total_beats × 100`. | `recording_hours` y `conclusion` |
| `abpm` | Monitoreo Ambulatorio de la Presión Arterial (MAPA) | `form_card_abpm` | Registro 24h de TA/FC (6–72 h), lecturas 1–500, oficina/24h/día/noche/máx/mín TA+FC, `valid_pct`, percentages de presión de pulso y descenso, `card_pattern_descenso`, `clasificación_MAPA`. Gráficos: `abpm_24h`, `abpm_día_noche`, `abpm_descenso`, `abpm_FC`. Requiere para finalizar: `horas_registro`, `lecturas_totales`, `lecturas_validas`, `paso_24h`, `dbp_24h`, `conclusion`. Precarga solo `modelo_dispositivo`, `hora_inicio_día`, `hora_inicio_noche`, `sobre_antihipertensivos`, `nota_medicamentos` del último estudio **final** mismo paciente. | `horas_registro`, `lecturas_totales`, `lecturas_validas`, `paso_24h`, `dbp_24h`, `conclusion` |
| `chagas` | Seguimiento de Enfermedad de Chagas | `form_card_chagas` | Fase/epidemiología, tres pruebas serológicas opcionales, dos clasificaciones physician-selected independientes (Kuschnir/ABCD), banderas de ECG con `ecg_normal` mutuamente exclusivo, RX tórax/ecocardiograma, NYHA/IC condicional, dispositivo, afectación digestiva, tratamiento etiológico (fármaco/fechas, sin dosis), salud pública/familia, plan. Descargo de etapas: "Las etapas son seleccionadas por el médico según el consenso aplicado; los criterios no son evaluados por este formulario." Gráficos: `chagas_lvef`, `chagas_lvedd`. Requiere para finalizar: `fase_enfermedad`, `estado_serología`, `conclusion`; formas crónicas requieren al menos una de `etapa_Kuschnir`/`etapa_ABCD`. Fechas validadas; `next_control_date` después de la fecha del estudio. Fecha de notificación requerida cuando el caso se notifica a salud pública. | `fase_enfermedad`, `estado_serología`, `conclusion` |

### 6.1 Detalle de los módulos del lote 2

Las tres tarjetas siguen el mismo flujo que las anteriores: los borradores son editables, los estudios finalizados son de sólo lectura, toda solicitud se valida en el servidor y ningún valor calculado se lee desde `POST`.

#### `anticoag` – Control de Anticoagulación

- **Campos:** `study_date`, `indication`, `drug`, `sample_type`, `inr_value`, `target_inr_low`, `target_inr_high`, `in_range`, `current_weekly_dose_mg`, `new_weekly_dose_mg`, `dose_change_pct`, `dosing_schedule`, `missed_doses`, `bleeding_event`, `thrombotic_event`, `event_detail`, `adherence`, `interacting_factors`, `next_control_date`, `plan`, `conclusion`, `comments`.
- **Listas:** `card_anticoag_indication`, `card_anticoag_drug`, `card_inr_sample`, `card_adherence`.
- **Límites de plausibilidad:** INR `0.5–10`, objetivo bajo `1.0–4.0`, objetivo alto `1.5–5.0` (y el alto debe ser mayor que el bajo), dosis semanales `0–200 mg`.
- **Valores calculados:** `in_range` compara el INR con el rango objetivo (`below`, `in`, `above`). `dose_change_pct` es `(nueva − actual) / actual × 100` y queda `NULL` cuando la dosis actual falta o es cero.
- **Reglas de finalización:** se requieren `inr_value`, ambos límites del rango, `next_control_date` y `conclusion`. La fecha de próximo control debe ser estrictamente posterior a la fecha del estudio y no más de 365 días después. `event_detail` es obligatorio cuando `bleeding_event` o `thrombotic_event` está marcado.
- **Precarga:** un estudio nuevo copia `indication`, `drug`, `sample_type`, `target_inr_low`, `target_inr_high` y `new_weekly_dose_mg` (como `current_weekly_dose_mg`) del último estudio **final** del mismo paciente.

#### `hf_followup` – Seguimiento de Insuficiencia Cardíaca

- **Campos:** `study_date`, `nyha_class`, `nyha_numeric`, `hf_type`, `etiology`, `hf_stage`, `weight_kg`, `dry_weight_kg`, `weight_change_kg`, `heart_rate_bpm`, `bp_systolic_mmhg`, `bp_diastolic_mmhg`, `spo2_pct`, `edema`, `jugular_distension`, `rales`, `orthopnea`, `pnd`, `congestion_note`, `lvef_pct`, `lvef_date`, `nt_probnp_pg_ml`, `bnp_pg_ml`, `creatinine_mg_dl`, `potassium_mmol_l`, `sodium_mmol_l`, `adherence`, `on_raas_inhibitor`, `on_beta_blocker`, `on_mra`, `on_sglt2_inhibitor`, `on_loop_diuretic`, `medications_note`, `hospitalizations_since_last`, `er_visits_since_last`, `education_given`, `plan`, `next_control_date`, `conclusion`, `comments`.
- **Listas:** `card_nyha`, `card_hf_type`, `card_hf_etiology`, `card_hf_stage`, `card_edema_grade`, `card_adherence`.
- **Límites de plausibilidad:** peso/peso seco `20–300 kg`, frecuencia cardíaca `20–250`, presión sistólica `40–300`, diastólica `20–200`, SpO₂ `50–100 %`, FEVI `5–90 %`, NT-proBNP `0–70000 pg/mL`, BNP `0–5000 pg/mL`, creatinina `0.1–15 mg/dL`, potasio `1.5–8 mmol/L`, sodio `100–170 mmol/L`, hospitalizaciones/urgencias `0–50`.
- **Valores calculados:** `nyha_numeric` convierte la clase seleccionada (`i`–`iv`) en `1–4`. `weight_change_kg` es el `weight_kg` actual menos el `weight_kg` del estudio **final** anterior del mismo paciente con `study_date` previo al actual (se excluye la fila actual al editar).
- **Reglas cruzadas:** `lvef_date` no puede ser futura; un `next_control_date` opcional debe ser posterior a la fecha del estudio.
- **Reglas de finalización:** se requieren `nyha_class` y `conclusion`.
- **Precarga:** un estudio nuevo copia `hf_type`, `etiology`, `hf_stage`, `dry_weight_kg`, los cinco indicadores `on_*` y `medications_note` del último estudio **final** del mismo paciente.

#### `holter` – Estudio de Monitor Holter

- **Campos:** `study_date`, `indication`, `recording_hours`, `total_beats`, `hr_min_bpm`, `hr_mean_bpm`, `hr_max_bpm`, `hr_min_time`, `hr_max_time`, `dominant_rhythm`, `af_present`, `af_burden_pct`, `paced_beats_pct`, `longest_pause_ms`, `pauses_gt_3s`, `pvc_total`, `pvc_burden_pct`, `pvc_couplets`, `nsvt_runs`, `longest_nsvt_beats`, `pac_total`, `svt_runs`, `longest_svt_beats`, `st_episodes`, `st_description`, `av_block_note`, `symptoms_reported`, `symptom_correlation`, `findings`, `conclusion`, `comments`.
- **Listas:** `dominant_rhythm` usa la lista existente `card_ecg_rhythm`; no se crea una lista de ritmos nueva.
- **Límites de plausibilidad:** registro `1–168 h`, latidos totales `100–2000000`, FC mínima `20–200`, media `30–220`, máxima `40–300`, cargas `0–100 %`, pausa más larga `0–30000 ms`, pausas > 3 s `0–1000`, PVC `0–500000`, dobletes `0–100000`, rachas de TVNS `0–10000`, TVNS más larga `0–1000 latidos`, PAC `0–500000`, rachas de TSV `0–10000`, TSV más larga `0–10000 latidos`, episodios de ST `0–1000`.
- **Reglas cruzadas:** `pvc_total` no puede superar `total_beats`; cuando los tres están presentes, las frecuencias deben cumplir `hr_min ≤ hr_mean ≤ hr_max`. Los horarios aceptan `HH:MM` o `HH:MM:SS` y se almacenan como `HH:MM:SS`.
- **Valor calculado:** `pvc_burden_pct = pvc_total / total_beats × 100`.

#### `abpm` – Monitoreo Ambulatorio de la Presión Arterial (MAPA)

- **Campos:** `study_date`, `horas_registro`, `modelo_dispositivo`, `hora_inicio_día`, `hora_inicio_noche`, `sobre_antihipertensivos`, `nota_medicamentos`, `indicación`, `lecturas_totales`, `lecturas_validas`, `valid_pct`, `paso_24h`, `dbp_24h`, `pulse_pressure_24h`, `dip_sbp_pct`, `dip_dbp_pct`, `pattern_descenso`, `clasificación_MAPA`, `conclusion`, `oficina_sbp_mmhg`, `oficina_dbp_mmhg`, `oficina_fc_bpm`, `dia_sbp_mmhg`, `dia_dbp_mmhg`, `dia_fc_bpm`, `noche_sbp_mmhg`, `noche_dbp_mmhg`, `noche_fc_bpm`, `max_sbp_mmhg`, `max_dbp_mmhg`, `max_fc_bpm`, `min_sbp_mmhg`, `min_dbp_mmhg`, `min_fc_bpm`.
- **Listas:** `pattern_descenso`, `clasificación_MAPA`.
- **Límites de plausibilidad:** registro `6–72 h`, lecturas `1–500`, lecturas válidas `≤ total`, SBP > DBP por sitio, mín ≤ media/día/noche ≤ máx para SBP, DBP y FC, lecturas válidas ≤ lecturas totales.
- **Valores calculados:** `valid_pct = lecturas_validas / lecturas_totales × 100` (con `number_format(..., 2, '.', '')` a mano). `pulse_pressure_24h = paso_24h - dbp_24h`. `dip_sbp_pct = (paso_24h - sbp_día) / paso_24h × 100`. `dip_dbp_pct = (dbp_24h - dbp_día) / dbp_24h × 100`.
- **Reglas de ordenamiento cruzado:** SBP > DBP por sitio; mín ≤ media/día/noche ≤ máx para SBP, DBP y FC; lecturas válidas ≤ lecturas totales.
- **Reglas de finalización:** `horas_registro`, `lecturas_totales`, `lecturas_validas`, `paso_24h`, `dbp_24h` y `conclusion` son obligatorios.
- **Precarga:** solo `modelo_dispositivo`, `hora_inicio_día`, `hora_inicio_noche`, `sobre_antihipertensivos` y `nota_medicamentos` se copian del último estudio **final** del mismo paciente.
- **Gráficos exactamente:** `abpm_24h`, `abpm_día_noche`, `abpm_descenso`, `abpm_FC`.

#### `chagas` – Seguimiento de Enfermedad de Chagas

- **Campos:** `study_date`, `fase_enfermedad`, `vía_probable_de_transmisión`, `país_o_región_de_origen`, `residencia_en_área_endémica`, `antecedentes_epidemiológicos`, `prueba_serológica_1`, `tipo_prueba_1`, `resultado_serológica_1`, `fecha_serológica_1`, `prueba_serológica_2`, `tipo_prueba_2`, `resultado_serológica_2`, `fecha_serológica_2`, `prueba_serológica_3`, `tipo_prueba_3`, `resultado_serológica_3`, `fecha_serológica_3`, `estado_serología_global`, `etapa_seleccionada_por_médico`, `etapa_Kuschnir`, `etapa_ABCD`, `ecg_realizado`, `ecg_normal`, `ecg_bloqueo_rama_derecha`, `ecg_bloqueo_fascicular_anterior_izquierdo`, `ecg_bloqueo_fascicular_posterior_izquierdo`, `ecg_bloqueo_auriculoventricular`, `ecg_ondas_Q`, `ecg_st_t_cambios`, `ecg_baja_tensión`, `ecg_arrhythmia`, `otro_ hallazgo_ECG`, `rx_tórax_realizado`, `fecha_rx_tórax`, `cardiomegalia`, `hallazgos_rx_tórax`, `ecocardiograma_realizado`, `fecha_ecocardiograma`, `fracción_deeyección_pct`, `diametro_lediastólico_ventriculo_izquierdo_mm`, `aneurisma_ventricular_izquierdo`, `trombo_ventricular_izquierdo`, `motilidad_parietal`, `insuficiencia_cardíaca`, `clase_NYHA`, `afectación_digestiva`, `megaesófago`, `megacolon`, `estreñimiento_crónico`, `hallazgos_digestivos`, `estado_tratamiento`, `fármaco_tratamiento`, `fecha_inicio_tratamiento`, `fecha_fin_tratamiento`, `nota_tratamiento`, `notificable_a_salud_publica`, `fecha_notificación`, `nota_salud_publica`, `tamizaje_familiar_realizado`, `notas_tamizaje_familiar`, `plan`, `conclusion`, `comentarios`.
- **Listas:** `fase_chagas`, `vía_de_transmisión_chagas`, `estado_serología_chagas`, `prueba_serológica_chagas`, `resultado_serológica_chagas`, `etapa_Kuschnir_chagas`, `etapa_ABCD_chagas`, `dispositivo_chagas`, `estado_tratamiento_chagas`, `fármaco_tratamiento_chagas`, `card_nyha`, `card_motilidad_parietal`.
- **Límites de plausibilidad:** FEVI `5–90 %`, LVEDD `10–200 mm`, fechas no futuras, fecha fin tratamiento ≥ fecha inicio tratamiento.
- **Reglas cruzadas:** Clase NYHA solo se puede registrar cuando hay insuficiencia cardíaca; cualquier bandera digestiva requiere que afectación digestiva esté marcada como presente.
- **Reglas de finalización:** `fase_enfermedad`, `estado_serología` y `conclusion` son obligatorios. Formas crónicas (identificadas por `fase_enfermedad` opciones que comienzan con `chronic_`) requieren al menos una de `etapa_Kuschnir` o `etapa_ABCD`. El resultado requiere su tipo y el tipo requiere su resultado. Fechas validadas; `next_control_date` debe ser después de la fecha del estudio. Fecha de notificación requerida cuando el caso se notifica a salud pública.
- **Precarga:** solo `área_origen`, `residencia_endémica`, `antecedentes_epidemiológicos`, `vía_de_transmisión`, tres grupos serológicos, `estado_serología`, `estado_tratamiento`, `fármaco_tratamiento`, fechas de tratamiento, `tamizaje_familiar_realizado`, `notas_tamizaje_familiar`; nunca precarguen etapas o hallazgos.
- **Gráficos exactamente:** `chagas_lvef`, `chagas_lvedd`.

### 6.2 Tiempo en rango terapéutico (TTR, método de Rosendaal)

`lib/CardAnticoag.php::ttr(int $pid, string $studyDate, int $windowDays = 180)` calcula el TTR de un paciente:

1. Selecciona sólo estudios de anticoagulación **finales**, activos, con INR no nulo, rango objetivo válido (`high > low`) y `DATE(study_date)` dentro de la ventana que termina en la fecha de referencia (180 días por defecto). Los resultados se ordenan por `study_date ASC, id ASC`.
2. Cada par consecutivo de pruebas forma un intervalo. Los intervalos mayores de **56 días** se omiten porque la interpolación lineal no se considera confiable en una brecha tan extensa.
3. La **prueba anterior** del intervalo aporta el rango objetivo para todo el intervalo.
4. El INR se interpola linealmente entre ambas pruebas y se calcula la fracción del intervalo dentro del rango objetivo. El TTR es `Σ(días del intervalo × fracción en rango) / Σ(días del intervalo) × 100`.
5. La función devuelve `ttr_pct` (un decimal o `null`), `days_evaluated`, `tests` y `message`. Con menos de dos pruebas utilizables (o sin intervalos utilizables), `ttr_pct` es `null` y se muestra el mensaje traducido **«Datos insuficientes»**. `view.php` y `print.php` muestran el porcentaje y los días evaluados.

**Ejemplo manual:** pruebas finales del 2026-01-01 (INR 2.0, objetivo 2.0–3.0), 2026-01-11 (INR 3.0, el objetivo cambia a 2.5–3.5) y 2026-02-10 (INR 1.0).

- Intervalo 1 (2026-01-01 → 2026-01-11, 10 días) usa el objetivo anterior 2.0–3.0. El INR sube de 2.0 a 3.0, por lo que permanece dentro del rango todo el intervalo: `10 × 1 = 10` días en rango.
- Intervalo 2 (2026-01-11 → 2026-02-10, 30 días) sigue usando el objetivo anterior 2.5–3.5. El INR cae linealmente de 3.0 a 1.0 y cruza 2.5 tras una caída de 0.5 sobre la caída total de 2.0, es decir, un cuarto del intervalo, por lo que está en rango 7.5 de 30 días: `30 × 0.25 = 7.5` días en rango.
- TTR = `(10 + 7.5) / (10 + 30) × 100 = 43.8 %` sobre **40 días evaluados**.

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

## 9.2 Informes imprimibles y logotipo institucional (`CardPrintAssets`)

Cada `cards/<code>/print.php` genera un informe HTML autocontenido que se abre
en una pestaña nueva. La página **no** invoca Dompdf por sí misma; está escrita
para que la impresión del navegador ("Imprimir → Guardar como PDF") o cualquier
envoltorio de Dompdf la consuma sin problemas.

- `lib/CardPrintAssets.php` (namespace `OpenEMR\Forms\Cardiology`) expone
  `CardPrintAssets::logoDataUri()`, que devuelve el logotipo institucional como
  URI `data:image/...;base64,...` o una cadena vacía si no hay logo disponible.
- Orden de búsqueda: `CLINIC_LOGO_PATH` → `<webserver_root>/public/images/logos/cardiology/`
  → la carpeta `assets/` propia del módulo.
- **Se prefiere PNG antes que SVG** a propósito: algunas versiones de Dompdf /
  `php-svg-lib` no renderizan `<text>` ni fuentes embebidas en SVG, por lo que
  el logo desaparecería del PDF.
- El módulo incluye `assets/logo_banner.png` (principal),
  `assets/logo_banner.svg`, `assets/logo.png` y `assets/logo.svg`. Mantener
  `logo_banner.png` al desplegar; no requiere configuración en base de datos.
- Todos los informes conservan la variable `$logoBase64` que usa el encabezado
  compartido. Si el helper devuelve una cadena vacía, el encabezado simplemente
  omite la imagen.
- Cada informe usa un bloque `@page` A4 vertical (`18mm 15mm 16mm 15mm`) con
  cuerpo de 12 px, títulos de sección de 13 px y el logotipo centrado limitado a
  90 px de alto por 320 px de ancho. El encabezado lleva una línea inferior y el
  cuerpo activa `print-color-adjust: exact` para conservar la banda de título,
  las insignias y las tablas al usar "Imprimir → Guardar como PDF".
- El logotipo va en un bloque centrado propio, arriba del encabezado de la
  clínica, para que no quede limitado por el ancho de la columna izquierda.

### Vinculación de documentos subidos

`CardDocuments::storeUploadedFile()` guarda el archivo mediante la clase
`Document` de OpenEMR y luego lo vincula al estudio en `card_documents`. El
insert usa `sqlInsert()`, cuyo valor de retorno es el ID del vínculo (en
OpenEMR 8.2–8.5 no existe `QueryUtils::lastInsertId()`). `documents_upload.php`
devuelve el arreglo `attachments` actualizado después de cada POST, de modo que
la tabla de adjuntos del estudio se refresca sin recargar la página.

### Visor de documentos

Los enlaces de adjuntos (`templates/_documents.php`, `documents_upload.php` y el
diálogo de subida) apuntan al visor propio del módulo
`documents_view.php?document_id=...`, abierto con `target="_blank"` en una pestaña nueva.
`documents_view.php` sirve el archivo con `Content-Disposition: inline`, el tipo MIME
real y `X-Content-Type-Options: nosniff`, para que los PDF e imágenes se abran en línea
en vez de descargarse. Verifica la sesión del paciente activo, la pertenencia del
documento a ese paciente, el permiso `patients/docs` y el ACL de la categoría mediante
`Document::can_access()` antes de servir el archivo.

Ya no se usa el endpoint central `controller.php?document&retrieve` para "Ver/Abrir": su
despacho posicional y el manejo de MIME/CSP del navegador podían forzar la descarga
incluso con `as_file=false`. El visor del módulo no depende de esa ruta y coincide con
`view_document.php` del portal.

### Nota sobre Font Awesome

OpenEMR 8.5 incluye Font Awesome 6.7.2. Usar `fa-folder` y `fa-folder-open`;
`fa-folder-open-o` no existe en esta versión y deja el árbol de carpetas sin
icono. El diálogo de subida es una página independiente que usa
`Header::setupHeader(['no_main-theme', 'fontawesome'])`; `fontawesome` no es un
asset autoload en `config.yaml`, por lo que debe solicitarse explícitamente o
los iconos de carpeta, subida y ojo aparecen en blanco.

### Informes imprimibles completos (print.php)

Todos los `cards/*/print.php` muestran el registro completo del estudio, no solo
unos pocos campos. El ecocardiograma (`cards/echo/print.php`) reproduce todas las
secciones de `_form_fields.php` (Contexto Clínico, VI sistólica/diastólica,
Aurículas, VD y presión pulmonar, Aorta, Válvulas, Pericardio e Informe), con los
títulos de `list_options` resueltos para los selects. Las demás cards agregan la
sección **Study Data** generada por `CardPrintReport::genericSections()`, que itera
todas las columnas de la fila del estudio (salvo las de sistema) con etiquetas
humanizadas (`lvedd_mm` → `LVEDD (mm)`) y valores vacíos como `—`.

Paciente y encuentro se cargan con los servicios de OpenEMR,
`PatientService::findByPid()` y `EncounterService::getOneByPidEid()`
(misma firma en 8.2.0 y 8.5.0), vía `lib/CardPrintReport.php`, con fallback a
`patient_data` / `form_encounter` si el servicio no está disponible. Los nombres
de médico/usuario usan `UserService` (`getUser()` / `getUserByUsername()`) y se
imprimen como "Apellido, Nombre". La página es A4 vertical con márgenes
`@page 20mm 15mm 18mm 15mm` y `padding: 4mm` en el cuerpo.

Cada informe muestra una barra solo visible en pantalla con **Imprimir**
(`window.print()`) y **Descargar PDF** (Dompdf en el servidor vía `?download=1`,
enviado como `Attachment`). Todos los textos del módulo están en inglés
(USA Hospital) y pasan por `xl*()`; las traducciones al español viven en
`sql/lang_custom.sql`, que se ejecuta manualmente como script independiente.

Después del cuerpo del informe, en su propia página: gráficos de **Evolución**
(`CardPrintReport::chartsHtml()`) que reutilizan las series de `CardCharts` y las
dibujan en el servidor como SVG inline (Dompdf no puede ejecutar Chart.js y GD
puede no estar; el SVG vía php-svg-lib funciona sin GD), y **Documentos Adjuntos**
(`CardPrintReport::attachedDocumentsHtml()`) que lista los archivos no-imagen e
incrusta los archivos de imagen uno por página cuando hay GD. Los PDF solo se
listan (Dompdf no puede fusionar páginas PDF). Sin GD, las etiquetas `<img>`
(logo e imágenes adjuntas) se quitan para que la descarga igual se renderice.

Fechas y horas en todas partes usan el manejo nativo de OpenEMR: los inputs de los
formularios son texto con las clases `datepicker` / `datetimepicker` del core
(asset `datetime-picker`, configuración de
`library/js/xl/jquery-datetimepicker-2-5-4.js.php`), los valores se muestran con
`DateFormatterUtils` (`oeFormatShortDate`, `oeFormatDateTime`, `oeFormatTime`)
para respetar los globals `date_display_format` / `time_display_format` del
usuario, y al guardar se parsean de vuelta con `DateToYYYYMMDD` /
`dateStringToDateTime` vía `lib/CardDate.php` (usado por
`CardInput::dateTime()`, `date()` y `time()`). El mismo formateador se usa en
cada `print.php`.

## Quality checks

All checks run without a database (PHP CLI only):

```bash
bash tools/run_checks.sh
```

That runs, in order:

1. `php -l` on every PHP file.
2. `node --check` on every JS file (skipped when node is not installed).
3. `php tools/tests/run.php` — self-tests for the checker (parser plus one
   fixture per rule, including the historical 62-columns / 63-slots / 61-params
   INSERT mismatch).
4. `php tools/check_schema.php` — the schema checker; it writes the report to
   `docs/schema_audit.md` and exits 1 when any ERROR exists.

Checker flags: `--only=<prefix>` (e.g. `--only=W`, `--only=S`), `--format=md|text`,
`--no-live` (skip `docs/live_schema.sql`), `--no-report`, `--out=<file>`.

What each rule means:

- **W1–W10 (writers)**: every column written by `save.php` and other writers
  exists in the table; INSERT/UPDATE column, value-slot and placeholder counts
  agree with the parameters; `$data` arrays have no duplicate keys and exclude
  identity columns on UPDATE; NOT NULL columns without defaults are inserted;
  `calculated` columns are assigned server-side, never read from POST; variables
  are assigned before use and named after their column; POST keys match form
  inputs; `RANGES` keys are columns, list-backed columns are validated with the
  exact list id from their COMMENT and fit VARCHAR length; ranges fit the
  column type (DECIMAL/int capacity); finalize logic only names real columns.
- **R1–R4 (readers)**: SELECT columns exist; study-row keys are real columns;
  chart fields are numeric columns; seeded cards have their folder files,
  existing table and existing document category.
- **S1–S7 (schema sync)**: upgrade-created tables match `table.sql`; conditional
  ALTERs match `table.sql`; upgrade-created columns are all in `table.sql`;
  seed rows agree both ways; `table.sql` conventions (no DROP, InnoDB, utf8mb4,
  comments); upgrade scripts are idempotent; `docs/live_schema.sql` (the real
  database dump) matches the files — the check that shows what the live
  database is missing.
- **UNRESOLVED / UNPARSED** (WARN level, listed under "Not verified"): statements
  the checker cannot resolve statically (e.g. OpenEMR core tables such as
  `list_options`/`documents`, which are not part of this module's schema files).

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

### 14.43 Anticoagulación: borrador, finalización y TTR
- [ ] Abrir la tarjeta **Control de Anticoagulación** (`anticoag`) en una consulta activa.
- [ ] Ingresar INR `2.4`, objetivo bajo `2.0`, objetivo alto `3.0`, dosis semanal actual `35`, dosis nueva `30` y una conclusión. Presionar **Guardar borrador**.
- [ ] Verificar que el estudio queda en `draft`, `in_range = 'in'` y `dose_change_pct = -14.29`.
- [ ] Presionar **Finalizar**. Verificar que el estudio pasa a `final`, queda de sólo lectura y se completan usuario/fecha de finalización.
- [ ] Verificar que `view.php` y `print.php` muestran el TTR (o **Datos insuficientes** cuando hay menos de dos estudios finales utilizables) y los días evaluados.
- [ ] Intentar un POST directo para editar el estudio finalizado. Verificar el rechazo con `This study is finalized and cannot be edited.`.
- [ ] Presionar **Imprimir**. Verificar encabezado del paciente, INR/objetivo, dosis, eventos, TTR, conclusión y documentos adjuntos.

### 14.44 Anticoagulación: rechazo por campos obligatorios y reglas cruzadas
- [ ] Finalizar sin INR. Verificar el rechazo con `An INR value is required to finalize the study.`.
- [ ] Finalizar con objetivo bajo `3.0` y alto `2.5`. Verificar el rechazo con `The upper target INR must be greater than the lower target INR.`.
- [ ] Finalizar sin fecha de próximo control y luego con la misma fecha del estudio. Verificar los rechazos correspondientes.
- [ ] Establecer la fecha de próximo control a más de 365 días del estudio. Verificar el rechazo por el límite de 365 días.
- [ ] Marcar `Bleeding event` y dejar `Event detail` vacío. Verificar el rechazo que exige el detalle del evento.
- [ ] Ingresar INR `0.4` (menor que `0.5`) y objetivo bajo `0.9` (menor que `1.0`). Verificar ambos rechazos por fuera de rango.
- [ ] Ingresar decimales con coma (`2,4`, `2,0`, `3,0`) y verificar que se aceptan y almacenan como `2.40`, `2.00`, `3.00`.

### 14.45 Seguimiento de Insuficiencia Cardíaca: cálculos, finalización y precarga
- [ ] Abrir la tarjeta **Seguimiento de Insuficiencia Cardíaca** (`hf_followup`).
- [ ] Seleccionar NYHA `Class III`, peso `82.5 kg`, FEVI `30 %` y una conclusión. Verificar que el número de clase NYHA calculado sea `3`.
- [ ] Guardar un primer estudio final y luego crear un estudio nuevo del mismo paciente con peso `80.0 kg`. Verificar `weight_change_kg = -2.500` respecto del estudio final anterior.
- [ ] Verificar que el formulario nuevo se precarga con `hf_type`, `etiology`, `hf_stage`, `dry_weight_kg`, los cinco indicadores `on_*` y `medications_note` del último estudio final.
- [ ] Establecer `lvef_date` en mañana. Verificar el rechazo con `The LVEF date cannot be in the future.`.
- [ ] Establecer una fecha de próximo control opcional igual o anterior al estudio. Verificar el rechazo correspondiente.
- [ ] Finalizar sin clase NYHA. Verificar el rechazo con `An NYHA class is required to finalize the study.`.
- [ ] Verificar rechazos fuera de rango: peso `19`/`301`, FC `19`, presión sistólica `39`, diastólica `19`, SpO₂ `49`, FEVI `4`/`91`, NT-proBNP `70001`, BNP `5001`, creatinina `0.09`/`16`, potasio `1.4`/`9`, sodio `99`/`171`, hospitalizaciones/urgencias `51`.
- [ ] Verificar que se aceptan decimales con coma, que los cálculos se recalculan en el servidor (enviar `nyha_numeric`/`weight_change_kg` falsificados y confirmar que se ignoran) y que la impresión muestra los documentos adjuntos.
- [ ] Finalizar un estudio e intentar un POST directo de edición; verificar `This study is finalized and cannot be edited.`.

### 14.46 Holter: cálculos, reglas cruzadas y precarga
- [ ] Abrir la tarjeta **Estudio de Monitor Holter** (`holter`).
- [ ] Ingresar duración `24` horas, latidos totales `100000`, PVC `5000` y una conclusión. Verificar `pvc_burden_pct = 5.00`.
- [ ] Establecer FC mínima `55`, media `70`, máxima `120`. Verificar que el estudio se guarda.
- [ ] Establecer mínima `80`, media `70`, máxima `120`. Verificar el rechazo por el orden de frecuencias.
- [ ] Establecer PVC `100001` con latidos totales `100000`. Verificar el rechazo con `The PVC count cannot exceed the total beat count.`.
- [ ] Finalizar sin duración de registro. Verificar el rechazo con `A recording duration is required to finalize the study.`.
- [ ] Verificar rechazos fuera de rango: registro `0`/`169`, latidos totales `99`/`2000001`, FC mínima `19`, media `29`, máxima `39`, cargas `-1`/`101`, pausa más larga `30001`, pausas > 3 s `1001`, PVC `500001`, dobletes `100001`, rachas TVNS `10001`, TVNS más larga `1001`, PAC `500001`, rachas TSV `10001`, TSV más larga `10001`, episodios ST `1001`.
- [ ] Verificar que las opciones de `dominant_rhythm` provienen de la lista existente `card_ecg_rhythm` y que los horarios `HH:MM` y `HH:MM:SS` se aceptan y almacenan como `HH:MM:SS`.
- [ ] Verificar que se aceptan decimales con coma, que un `pvc_burden_pct` falsificado en POST se ignora y que los gráficos se renderizan con múltiples estudios y valores nulos.
- [ ] Finalizar un estudio, intentar un POST directo de edición y verificar el rechazo. Luego presionar **Imprimir** y verificar encabezado del paciente, frecuencias, cargas, pausas, ectopía, conclusión y adjuntos.

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
embebido. Las ocho vistas de estudio incluyen `templates/_documents.php`, que
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
| `documents_upload.php` | GET renderiza el diálogo de dos columnas (árbol de carpetas + carpeta seleccionada + zona de arrastre, tabla de documentos); POST valida y almacena archivos y devuelve el JSON documentado. |
| `lib/CardDocumentCategories.php` | Resuelve la raíz de Cardiología por nombre, recorre el subárbol por `parent`, valida pertenencia/selección y renderiza el árbol de carpetas. |
| `js/cardiology-upload.js` | API pública `CardUpload.open()`, alojamiento con `dlgopen()` o modal Bootstrap, validación de origen/fuente, limpieza del listener, `keepOpen`, `onDone`/`onClose`/`callBack`. |
| `js/cardiology-upload-dialog.js` | Selección en el árbol de carpetas, arrastrar y soltar, selector de archivos, cola, quitar antes de subir, progreso XHR por archivo, reintento, cancelación, resumen, tabla de documentos en vivo, Escape/Cerrar. |
| `lib/CardDocuments.php` | `resolveUploadContext()`, `validateCategoryId()` y el único camino de almacenamiento `storeUploadedFile()`. |
| `documents_api.php` | Sólo acciones que no suben archivos: `list`, `attach_existing`, `unlink`, `list_candidates`. |

### 16.2 Diferencias deliberadas respecto del odontograma

| Comportamiento del odontograma | Diálogo de Cardiología | Motivo |
|---|---|---|
| Popup `window.open()` de 1000×600 | Modal `dlgopen()` o modal iframe Bootstrap 4/5 | Conserva el marco nativo de OpenEMR, el apilado, el redimensionado y el tema. |
| Un único input fijo `odon_doc` | Cola con varios archivos (límite del servidor, máximo 10) | Los adjuntos suelen llegar en varios archivos. |
| POST de formulario que recarga el opener | `XMLHttpRequest` + JSON + `postMessage` | Evita perder el estado de la página y permite progreso, reintento y resultados parciales. |
| Recarga de página tras cada subida | El diálogo permanece abierto (`keepOpen`) y ambas tablas se actualizan en vivo | El usuario puede adjuntar varios lotes al mismo estudio sin reabrir el diálogo. |
| Sin progreso por archivo | Barra de progreso y estado por archivo | Necesario para PDF grandes y redes clínicas lentas. |
| Sin reintento | Reintento sólo de archivos fallidos | Nunca se suben dos veces los archivos exitosos. |
| Sin control de cancelación | Cancelar aborta la solicitud activa | Evita subidas prolongadas si el usuario cambia de opinión. |
| Árbol de categorías libre en el diálogo | Mismo árbol de dos columnas, restringido al subárbol de Cardiología y resuelto por nombre (sin ID raíz fijo) | El usuario elige la carpeta destino como en el odontograma, mientras el servidor sigue validando pertenencia al subárbol y ACL. |
| Sin token CSRF | CSRF en cada POST | Obligatorio para solicitudes que cambian estado. |
| Validación sólo en la página | Lista de extensiones, MIME real, doble extensión, tamaño y validación de paciente/categoría/estudio | El navegador sólo aconseja; el servidor es la autoridad. |
| `Document::createDocument()` y `UPDATE documents` directos | Un único método `CardDocuments::storeUploadedFile()` | Misma lógica para subidas sueltas, en borrador y en finalizado. |
| Alerta con recarga de página | `postMessage` validado por origen/fuente y `callBack` opcional | Contrato de integración documentado y reutilizable. |

### 16.3 Contrato HTTP

`GET documents_upload.php`

| Parámetro | Obligatorio | Validación |
|---|---|---|
| `pid` | Sí | Debe coincidir con `CardiologyContainer::getSessionPid()`. |
| `category_id` | No | Debe existir en `categories`, pertenecer al subárbol de Cardiología (raíz resuelta por nombre) y ser seleccionable; su `aco_spec` se valida con ACL del núcleo. Si se omite, se usa la categoría del módulo o la 22007. |
| `card_code` | En par | Debe enviarse junto con `study_id`. |
| `study_id` | En par | Debe pertenecer a `pid`, estar activo y pasar `CardRegistry::canWrite()`. |
| `multiple` | No | `1` (predeterminado) o `0`. |
| `keep_open` | No | `1` mantiene el modal abierto después de cada lote para subir más archivos; `0` lo cierra tras el primer resultado. |

`GET` devuelve el HTML del diálogo. `POST` acepta `multipart/form-data`:

| Campo | Obligatorio | Notas |
|---|---|---|
| `csrf_token_form` | Sí | También se acepta `X-CSRF-Token`. |
| `pid` | Sí | Misma validación de sesión que en GET. |
| `files[]` | Sí | Uno o más archivos, máximo `CardDocuments::MAX_FILES_PER_REQUEST` (10). |
| `category_id`, `card_code`, `study_id`, `multiple`, `keep_open` | No | Misma validación que en GET. |

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
  "errors": [],
  "attachments": [
    {
      "link_id": 88,
      "document_id": 1234,
      "doc_name": "informe-ecocardiograma.pdf",
      "category_name": "Echocardiogram",
      "mimetype": "application/pdf",
      "size": 482113,
      "doc_date": "2026-11-04 09:14:00",
      "linked_at": "2026-11-04 10:02:11"
    }
  ]
}
```

`attachments` es la lista actualizada del estudio (`CardDocuments::listForStudy()`),
de modo que el diálogo y el opener pueden repintar sus tablas sin otra solicitud.
Es un arreglo vacío cuando la subida es independiente (no se enviaron
`card_code` + `study_id`).

`status` puede ser:

- `ok` — todos los archivos se almacenaron (y vincularon cuando se enviaron `card_code` + `study_id`).
- `partial` — al menos un archivo se almacenó y al menos uno falló.
- `error` — ningún archivo se almacenó; revisar `errors`.

Cada error tiene la forma `{ "name": "archivo.pdf", "message": "mensaje traducido" }`.

Códigos HTTP: `200` para resultados `ok`/`partial`/`error` producidos tras inspeccionar archivos, `400` para parámetros inválidos o ausencia de archivos, `403` para fallos de CSRF, sesión o ACL, `422` para fallos del servicio y `500` para errores inesperados. El diálogo muestra el mensaje de `errors` sin importar el código.

No se acepta del request ningún nombre de función callback, fragmento de JavaScript, nombre de tabla, nombre de columna ni identificador de referencia externa.

### 16.4 Contrato `postMessage`

Con `keepOpen` el diálogo publica cada lote finalizado, incluso si algunos
archivos fallaron, y permanece abierto para que el usuario reintente sin perder
el informe:

```js
window.parent.postMessage({
  source: 'cardiology-upload',
  status: 'ok' | 'partial' | 'error',
  keepOpen: true | false,
  documents: [{ id, name, category_id, linked }],
  errors: [{ name, message }],
  attachments: [ /* lista actualizada del estudio */ ]
}, window.location.origin);
```

Cuando el usuario pulsa **Cancelar**, **Cerrar** o **Escape** (incluso para
cerrar un diálogo en modo `keepOpen`), el diálogo envía una solicitud de cierre
dedicada para que los modales iframe se cierren aunque el opener no tenga un
`dlgclose()` global:

```js
window.parent.postMessage({
  source: 'cardiology-upload',
  action: 'close'
}, window.location.origin);
```

El receptor debe validar `event.origin` (igual a `window.location.origin`) y
`event.source` (la ventana del iframe del diálogo). `CardUpload.open()` lo hace,
gestiona la solicitud de cierre y además soporta un callback del lado del opener:

```js
function alCambiar(payload) {
  if (!payload || !payload.documents) return;
  console.log(payload.documents.length, payload.errors.length);
  if (payload.attachments) {
    // repintar la tabla de adjuntos local
  }
}

CardUpload.open({
  pid: 42,
  cardCode: 'echo',
  studyId: 7,
  multiple: 1,
  keepOpen: true,
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
- Sólo se aceptan `pdf`, `jpg`, `jpeg`, `jfif`, `png`, `gif`, `webp`, `bmp`, `avif`, `heic`, `heif`, `tif` y `tiff`.
- Primero se valida la extensión y luego se detecta el tipo de contenido real con fileinfo. Si los bytes corresponden a otro tipo permitido (por ejemplo un `.jpeg` que en realidad es WebP), la subida se acepta y el nombre almacenado se renombra a la extensión canónica. Se rechazan los tipos desconocidos y las dobles extensiones que ocultan ejecutables.
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
- **Visor de documentos** propio del módulo en ambas versiones: `documents_view.php?document_id=…` sirve `Content-Disposition: inline` con MIME real en una pestaña nueva, en vez de `controller.php?document&retrieve`.

### 16.8 Precaución sobre reutilización entre repositorios

El diálogo es reutilizable dentro de este módulo, pero no es una biblioteca
estable para compartir a largo plazo entre repositorios no relacionados. Su
formdir, IDs de categoría, expectativas de ACL, URL raíz y esquema
`card_documents` pertenecen a `form_cardiology`. Si otro módulo necesita la misma
experiencia, conviene copiar el contrato y adaptar las rutas y la resolución de
categorías allí, en lugar de apuntar dos repositorios a un archivo que cambia.


