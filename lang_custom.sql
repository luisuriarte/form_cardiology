-- ============================================================================
-- lang_custom.sql  –  Formulario Cardiología (form_cardiology)
-- Español (Argentina) – lang_code = 'el'
--
-- Contiene las traducciones de todos los strings que aparecen en llamadas
-- xl() / xlt() / xla() / xlj() de los archivos PHP del módulo, más los
-- títulos de las listas (list_options) registradas en table.sql.
--
-- Compatibilidad: OpenEMR 8.2.0 – 8.5.0
-- Seguro de re-ejecutar: usa INSERT IGNORE / UPDATE solo cuando difiere.
-- ============================================================================

START TRANSACTION;

-- ============================================================================
-- 1. Asegurar que el registro del idioma Español (Latinoamérica) exista
-- ============================================================================
-- La siguiente línea está comentada porque el registro ya existe en la mayoría
-- de las instalaciones de OpenEMR. Descomentarla sólo si es necesario.
-- INSERT IGNORE INTO lang_languages (lang_code, lang_description)
-- SELECT 'el', 'Spanish (Latin American)'
-- WHERE NOT EXISTS (SELECT 1 FROM lang_languages WHERE lang_code = 'el');

-- ============================================================================
-- 2. Insertar traducciones en lang_custom (INSERT IGNORE = no sobreescribe)
-- ============================================================================
INSERT IGNORE INTO lang_custom (lang_description, lang_code, constant_name, definition) VALUES

-- --------------------------------------------------------------------------
-- Título del formulario y navegación general
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Cardiology',                            'Cardiología'),
('Spanish (Latin American)', 'el', 'Cardiology - Card Configuration',       'Cardiología – Configuración de Módulos'),
('Spanish (Latin American)', 'el', 'Cardiology Card Configuration',         'Configuración de Módulos de Cardiología'),

-- --------------------------------------------------------------------------
-- Acciones comunes
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Save',                                  'Guardar'),
('Spanish (Latin American)', 'el', 'Save Draft',                            'Guardar Borrador'),
('Spanish (Latin American)', 'el', 'Cancel',                                'Cancelar'),
('Spanish (Latin American)', 'el', 'Close',                                 'Cerrar'),
('Spanish (Latin American)', 'el', 'New',                                   'Nuevo'),
('Spanish (Latin American)', 'el', 'Finalize',                              'Finalizar'),
('Spanish (Latin American)', 'el', 'Print PDF',                             'Imprimir PDF'),
('Spanish (Latin American)', 'el', 'Open study',                            'Abrir estudio'),
('Spanish (Latin American)', 'el', 'View study',                            'Ver estudio'),
('Spanish (Latin American)', 'el', 'Yes',                                   'Sí'),
('Spanish (Latin American)', 'el', 'No',                                    'No'),
('Spanish (Latin American)', 'el', 'Apply',                                 'Aplicar'),

-- --------------------------------------------------------------------------
-- Contenedor – encabezado de consulta
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Patient',                               'Paciente'),
('Spanish (Latin American)', 'el', 'Encounter',                             'Consulta'),
('Spanish (Latin American)', 'el', 'ID',                                    'ID'),

-- --------------------------------------------------------------------------
-- Contenedor – campos Reason / Notes
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Reason for Visit',                      'Motivo de Consulta'),
('Spanish (Latin American)', 'el', 'Visit Notes',                           'Notas de la Consulta'),
('Spanish (Latin American)', 'el', 'Reason',                                'Motivo'),
('Spanish (Latin American)', 'el', 'Notes',                                 'Notas'),
('Spanish (Latin American)', 'el', 'Enter the reason for the cardiology visit',
                                                                            'Ingrese el motivo de la consulta cardiológica'),
('Spanish (Latin American)', 'el', 'Shared notes visible to all cards in this encounter',
                                                                            'Notas compartidas visibles en todos los módulos de esta consulta'),

-- --------------------------------------------------------------------------
-- Grilla de módulos (card grid)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Clinical Studies',                      'Estudios Clínicos'),
('Spanish (Latin American)', 'el', 'No cardiology modules are currently active for your account.',
                                                                            'No hay módulos de cardiología activos para su cuenta.'),
('Spanish (Latin American)', 'el', 'Not installed',                         'No instalado'),
('Spanish (Latin American)', 'el', 'No studies for this encounter.',         'Sin estudios para esta consulta.'),
('Spanish (Latin American)', 'el', '%d study(s) in this encounter',         '%d estudio(s) en esta consulta'),
('Spanish (Latin American)', 'el', 'Save visit first, then open this card', 'Guarde la consulta primero, luego abra este módulo'),
('Spanish (Latin American)', 'el', 'New Study',                             'Nuevo Estudio'),

-- --------------------------------------------------------------------------
-- Estados de estudio
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Draft',                                 'Borrador'),
('Spanish (Latin American)', 'el', 'Final',                                 'Final'),
('Spanish (Latin American)', 'el', 'Finalize this study? It will become read-only.',
                                                                            '¿Finalizar este estudio? Quedará en modo sólo lectura.'),

-- --------------------------------------------------------------------------
-- Nombres de módulos (card_modules.title)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Doppler Echocardiogram',                'Ecocardiograma Doppler'),
('Spanish (Latin American)', 'el', 'Echocardiogram - New Study',            'Ecocardiograma – Nuevo Estudio'),
('Spanish (Latin American)', 'el', 'Exercise Stress Test',                  'Ergometría / Prueba de Esfuerzo'),

-- --------------------------------------------------------------------------
-- Errores y validaciones (backend / frontend)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Not authorized',                        'No autorizado'),
('Spanish (Latin American)', 'el', 'No active patient or encounter.',        'No hay un paciente o consulta activa.'),
('Spanish (Latin American)', 'el', 'Missing required parameters.',           'Faltan parámetros requeridos.'),
('Spanish (Latin American)', 'el', 'Session mismatch. Please reload and try again.',
                                                                            'Discrepancia de sesión. Por favor recargue e intente nuevamente.'),
('Spanish (Latin American)', 'el', 'The cardiology record is not available or has been deleted.',
                                                                            'El registro de cardiología no está disponible o fue eliminado.'),
('Spanish (Latin American)', 'el', 'No card modules found in card_modules table.',
                                                                            'No se encontraron módulos en la tabla card_modules.'),

-- --------------------------------------------------------------------------
-- Configuración de módulos (config.php)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Enable or disable individual cardiology cards for the entire clinic or per user.',
                                                                            'Activar o desactivar módulos de cardiología para toda la clínica o por usuario.'),
('Spanish (Latin American)', 'el', 'Configure for',                         'Configurar para'),
('Spanish (Latin American)', 'el', 'Clinic default',                        'Predeterminado de clínica'),
('Spanish (Latin American)', 'el', 'Specific user',                         'Usuario específico'),
('Spanish (Latin American)', 'el', '-- Select user --',                     '-- Seleccionar usuario --'),
('Spanish (Latin American)', 'el', 'Clinic-wide defaults',                  'Predeterminados de clínica'),
('Spanish (Latin American)', 'el', 'Per-user overrides',                    'Anulaciones por usuario'),
('Spanish (Latin American)', 'el', 'Card',                                  'Módulo'),
('Spanish (Latin American)', 'el', 'Code',                                  'Código'),
('Spanish (Latin American)', 'el', 'Global Active',                         'Activo globalmente'),
('Spanish (Latin American)', 'el', 'Active (this scope)',                   'Activo (este ámbito)'),
('Spanish (Latin American)', 'el', 'Card is globally disabled',             'El módulo está desactivado globalmente'),
('Spanish (Latin American)', 'el', 'user_id = 0',                          'user_id = 0'),
('Spanish (Latin American)', 'el', 'Settings saved successfully.',           'Configuración guardada correctamente.'),

-- --------------------------------------------------------------------------
-- Listas de opciones: títulos de las listas (registradas con list_id = 'lists')
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Cardiology - Valve regurgitation/stenosis grade',
                                                                            'Cardiología – Grado de regurgitación/estenosis valvular'),
('Spanish (Latin American)', 'el', 'Cardiology - Wall motion',              'Cardiología – Motilidad parietal'),
('Spanish (Latin American)', 'el', 'Cardiology - Pericardial effusion grade',
                                                                            'Cardiología – Grado de derrame pericárdico'),
('Spanish (Latin American)', 'el', 'Cardiology - Stress test protocol',     'Cardiología – Protocolo de ergometría'),
('Spanish (Latin American)', 'el', 'Cardiology - LVEF method',              'Cardiología – Método de medición de FEVI'),
('Spanish (Latin American)', 'el', 'Cardiology - Blood pressure response',  'Cardiología – Respuesta de la presión arterial'),
('Spanish (Latin American)', 'el', 'Cardiology - Stress test symptom',      'Cardiología – Síntoma en la ergometría'),
('Spanish (Latin American)', 'el', 'Cardiology - Stress test stop reason',  'Cardiología – Motivo de detención de la ergometría'),
('Spanish (Latin American)', 'el', 'Cardiology - Stress test result',       'Cardiología – Resultado de la ergometría'),

-- --------------------------------------------------------------------------
-- card_valve_grade  (opciones de grado valvular)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'None',                                  'Sin alteración'),
('Spanish (Latin American)', 'el', 'Trace',                                 'Mínima'),
('Spanish (Latin American)', 'el', 'Mild',                                  'Leve'),
('Spanish (Latin American)', 'el', 'Moderate',                              'Moderada'),
('Spanish (Latin American)', 'el', 'Moderate-severe',                       'Moderada-severa'),
('Spanish (Latin American)', 'el', 'Severe',                                'Severa'),

-- --------------------------------------------------------------------------
-- card_wall_motion  (motilidad parietal segmentaria)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Normal',                                'Normal'),
('Spanish (Latin American)', 'el', 'Hypokinetic',                           'Hipoquinética'),
('Spanish (Latin American)', 'el', 'Akinetic',                              'Aquinética'),
('Spanish (Latin American)', 'el', 'Dyskinetic',                            'Disquinética'),

-- --------------------------------------------------------------------------
-- card_effusion_grade  (grado de derrame pericárdico)
-- --------------------------------------------------------------------------
-- 'None' y 'Trace' y 'Mild' y 'Moderate' ya están arriba; sólo se agrega 'Large'
('Spanish (Latin American)', 'el', 'Large',                                 'Abundante'),

-- --------------------------------------------------------------------------
-- card_stress_protocol  (protocolos de ergometría)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Bruce',                                 'Bruce'),
('Spanish (Latin American)', 'el', 'Modified Bruce',                        'Bruce Modificado'),
('Spanish (Latin American)', 'el', 'Naughton',                              'Naughton'),
('Spanish (Latin American)', 'el', 'Ramp',                                  'Rampa'),

-- --------------------------------------------------------------------------
-- card_ef_method  (método de medición de FEVI)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Simpson biplane',                       'Simpson biplano'),
('Spanish (Latin American)', 'el', 'Teichholz',                             'Teichholz'),
('Spanish (Latin American)', 'el', 'Visual estimate',                       'Estimación visual'),

-- --------------------------------------------------------------------------
-- card_bp_response  (respuesta tensional al esfuerzo)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Hypertensive response',                 'Respuesta hipertensiva'),
('Spanish (Latin American)', 'el', 'Blunted response',                      'Respuesta atenuada'),
('Spanish (Latin American)', 'el', 'Hypotensive response',                  'Respuesta hipotensiva'),

-- --------------------------------------------------------------------------
-- card_stress_symptom  (síntomas durante la prueba)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Typical angina',                        'Angina típica'),
('Spanish (Latin American)', 'el', 'Atypical chest pain',                   'Dolor torácico atípico'),
('Spanish (Latin American)', 'el', 'Dyspnea',                               'Disnea'),
('Spanish (Latin American)', 'el', 'Dizziness',                             'Mareos / Vértigo'),
('Spanish (Latin American)', 'el', 'Fatigue',                               'Fatiga'),
('Spanish (Latin American)', 'el', 'Leg claudication',                      'Claudicación de miembros inferiores'),

-- --------------------------------------------------------------------------
-- card_stop_reason  (motivo de detención)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Target heart rate reached',             'Frecuencia cardíaca objetivo alcanzada'),
('Spanish (Latin American)', 'el', 'Fatigue or exhaustion',                 'Fatiga o agotamiento'),
('Spanish (Latin American)', 'el', 'Symptoms',                              'Síntomas'),
('Spanish (Latin American)', 'el', 'ST segment changes',                    'Cambios del segmento ST'),
('Spanish (Latin American)', 'el', 'Arrhythmia',                            'Arritmia'),
('Spanish (Latin American)', 'el', 'Hypertensive response',                 'Respuesta hipertensiva'),
('Spanish (Latin American)', 'el', 'Hypotensive response',                  'Respuesta hipotensiva'),
('Spanish (Latin American)', 'el', 'Patient request',                       'Solicitud del paciente'),

-- --------------------------------------------------------------------------
-- card_stress_result  (resultado de la prueba)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Negative for ischemia',                 'Negativa para isquemia'),
('Spanish (Latin American)', 'el', 'Positive for ischemia',                 'Positiva para isquemia'),
('Spanish (Latin American)', 'el', 'Inconclusive',                          'No concluyente'),
('Spanish (Latin American)', 'el', 'Submaximal (target not reached)',        'Submáxima (objetivo no alcanzado)')

; -- fin del INSERT IGNORE

-- ============================================================================
-- 3. Sincronizar lang_custom → lang_constants (agregar strings fuente faltantes)
-- ============================================================================
INSERT IGNORE INTO lang_constants (constant_name)
SELECT DISTINCT CONVERT(lc.constant_name USING utf8mb4)
FROM lang_custom lc
WHERE lc.constant_name <> ''
  AND NOT EXISTS (
    SELECT 1 FROM lang_constants c
    WHERE c.constant_name = CONVERT(lc.constant_name USING utf8mb4)
  );

-- ============================================================================
-- 4. Insertar definiciones nuevas (saltar si ya existe)
-- ============================================================================
INSERT IGNORE INTO lang_definitions (cons_id, lang_id, definition)
SELECT c.cons_id, l.lang_id, lc.definition
FROM lang_custom lc
INNER JOIN lang_constants c
    ON c.constant_name = CONVERT(lc.constant_name USING utf8mb4)
INNER JOIN lang_languages l
    ON l.lang_code = lc.lang_code
WHERE NOT EXISTS (
    SELECT 1 FROM lang_definitions d
    WHERE d.cons_id = c.cons_id AND d.lang_id = l.lang_id
);

-- ============================================================================
-- 5. Actualizar definiciones que cambiaron desde la última ejecución
-- ============================================================================
UPDATE lang_definitions d
INNER JOIN lang_constants c ON c.cons_id = d.cons_id
INNER JOIN lang_languages l ON l.lang_id = d.lang_id
INNER JOIN lang_custom lc
    ON l.lang_code = lc.lang_code
    AND c.constant_name = CONVERT(lc.constant_name USING utf8mb4)
SET d.definition = lc.definition
WHERE IFNULL(d.definition, '') <> IFNULL(lc.definition, '');

COMMIT;

