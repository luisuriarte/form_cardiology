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
-- WHERE NOT EXISTS (SELECT 1 FROM lang_languages WHERE lang_code = 'el')

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
-- 'None' y 'Trace' y 'Mild' y 'Moderate' ya están arriba, sólo se agrega 'Large'
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
('Spanish (Latin American)', 'el', 'Submaximal (target not reached)',        'Submáxima (objetivo no alcanzado)'),

-- --------------------------------------------------------------------------
-- Stress Test card – títulos de página y navegación
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Exercise Stress Test – New Study',      'Ergometría – Nuevo Estudio'),
('Spanish (Latin American)', 'el', 'Exercise Stress Test – View Study',     'Ergometría – Ver Estudio'),
('Spanish (Latin American)', 'el', 'Exercise Stress Test Report',           'Informe de Ergometría'),
('Spanish (Latin American)', 'el', 'Study not found or has been deleted.',  'Estudio no encontrado o eliminado.'),
('Spanish (Latin American)', 'el', 'This study is finalized and cannot be edited.',
                                                                            'Este estudio está finalizado y no puede editarse.'),
('Spanish (Latin American)', 'el', 'Study Date',                            'Fecha del Estudio'),
('Spanish (Latin American)', 'el', 'Physician',                             'Médico'),
('Spanish (Latin American)', 'el', 'Finalized',                             'Finalizado'),
('Spanish (Latin American)', 'el', 'by',                                    'por'),
('Spanish (Latin American)', 'el', 'Study ID',                              'ID del Estudio'),
('Spanish (Latin American)', 'el', 'Study',                                 'Estudio'),
('Spanish (Latin American)', 'el', 'Back',                                  'Volver'),
('Spanish (Latin American)', 'el', 'Print',                                 'Imprimir'),
('Spanish (Latin American)', 'el', 'Report generated',                      'Informe generado'),

-- --------------------------------------------------------------------------
-- Stress Test card – sección Contexto Clínico
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Clinical Context',                      'Contexto Clínico'),
('Spanish (Latin American)', 'el', 'Indication',                            'Indicación'),
('Spanish (Latin American)', 'el', 'Age (years)',                           'Edad (años)'),
('Spanish (Latin American)', 'el', 'Height (cm)',                           'Talla (cm)'),
('Spanish (Latin American)', 'el', 'Weight (kg)',                           'Peso (kg)'),
('Spanish (Latin American)', 'el', 'On beta-blocker',                       'Bajo betabloqueante'),
('Spanish (Latin American)', 'el', 'Medications',                           'Medicaciones'),
('Spanish (Latin American)', 'el', 'Protocol',                              'Protocolo'),
('Spanish (Latin American)', 'el', 'Resting ECG Findings',                  'ECG de reposo'),
('Spanish (Latin American)', 'el', 'Indication for the test',              'Indicación del estudio'),
('Spanish (Latin American)', 'el', 'Relevant medications at study time',    'Medicaciones relevantes al momento del estudio'),
('Spanish (Latin American)', 'el', 'ECG findings before the test',          'Hallazgos en el ECG previo a la prueba'),

-- --------------------------------------------------------------------------
-- Stress Test card – sección Valores Basales
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Baseline Values',                       'Valores Basales'),
('Spanish (Latin American)', 'el', 'Resting HR (bpm)',                      'FC en reposo (lpm)'),
('Spanish (Latin American)', 'el', 'Resting BP Systolic (mmHg)',            'PAS en reposo (mmHg)'),
('Spanish (Latin American)', 'el', 'Resting BP Diastolic (mmHg)',           'PAD en reposo (mmHg)'),

-- --------------------------------------------------------------------------
-- Stress Test card – sección Performance
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Exercise Performance',                  'Rendimiento al Esfuerzo'),
('Spanish (Latin American)', 'el', 'Max Predicted HR',                      'FC Máxima Predicha'),
('Spanish (Latin American)', 'el', 'Calculated: 220 - age',                 'Calculado: 220 - edad'),
('Spanish (Latin American)', 'el', 'Target HR (85%)',                       'FC Objetivo (85%)'),
('Spanish (Latin American)', 'el', 'Calculated: 85% of max predicted HR',   'Calculado: 85% de la FC máxima predicha'),
('Spanish (Latin American)', 'el', 'Peak HR (bpm)',                         'FC Pico (lpm)'),
('Spanish (Latin American)', 'el', 'Peak HR % Predicted',                   'FC Pico % de Predicha'),
('Spanish (Latin American)', 'el', 'Calculated: peak HR / max predicted × 100',
                                                                            'Calculado: FC pico / FC máx predicha × 100'),
('Spanish (Latin American)', 'el', 'Peak BP Sys. (mmHg)',                   'PAS Pico (mmHg)'),
('Spanish (Latin American)', 'el', 'Peak BP Dia. (mmHg)',                   'PAD Pico (mmHg)'),
('Spanish (Latin American)', 'el', 'Double Product',                        'Doble Producto'),
('Spanish (Latin American)', 'el', 'Calculated: peak HR × peak systolic BP','Calculado: FC pico × PAS pico'),
('Spanish (Latin American)', 'el', 'Exercise Duration (s)',                  'Duración del Esfuerzo (s)'),
('Spanish (Latin American)', 'el', 'METs Achieved',                         'METs Alcanzados'),
('Spanish (Latin American)', 'el', 'BP Response',                           'Respuesta Tensional'),
('Spanish (Latin American)', 'el', 'HR Recovery 1 min',                     'Recuperación FC 1 min'),

-- --------------------------------------------------------------------------
-- Stress Test card – sección Síntomas, ST y Arritmias
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Symptoms, ST Segment & Arrhythmias',    'Síntomas, Segmento ST y Arritmias'),
('Spanish (Latin American)', 'el', 'Main Symptom',                          'Síntoma Principal'),
('Spanish (Latin American)', 'el', 'Angina Index',                          'Índice de Angina'),
('Spanish (Latin American)', 'el', 'Non-limiting',                          'No limitante'),
('Spanish (Latin American)', 'el', 'Test-limiting',                         'Limitante de la prueba'),
('Spanish (Latin American)', 'el', 'ST Depression (mm)',                    'Depresión del ST (mm)'),
('Spanish (Latin American)', 'el', 'ST Elevation (mm)',                     'Elevación del ST (mm)'),
('Spanish (Latin American)', 'el', 'ST Leads',                              'Derivaciones del ST'),
('Spanish (Latin American)', 'el', 'ST Onset HR (bpm)',                     'FC de inicio de cambios ST (lpm)'),
('Spanish (Latin American)', 'el', 'Arrhythmias',                           'Arritmias'),
('Spanish (Latin American)', 'el', 'e.g. V4-V6',                            'p.ej. V4-V6'),
('Spanish (Latin American)', 'el', 'Arrhythmias observed during exercise or recovery',
                                                                            'Arritmias observadas durante el esfuerzo o la recuperación'),

-- --------------------------------------------------------------------------
-- Stress Test card – sección Resultado
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Result',                                'Resultado'),
('Spanish (Latin American)', 'el', 'Stop Reason',                           'Motivo de Detención'),
('Spanish (Latin American)', 'el', 'Duke Score',                            'Puntaje de Duke'),
('Spanish (Latin American)', 'el', 'Calculated: min - 5×ST_depression - 4×angina_index',
                                                                            'Calculado: min - 5×dep. ST - 4×índice de angina'),
('Spanish (Latin American)', 'el', 'Duke Risk',                             'Riesgo de Duke'),
('Spanish (Latin American)', 'el', 'Overall Result',                        'Resultado Global'),
('Spanish (Latin American)', 'el', 'Low risk',                              'Riesgo bajo'),
('Spanish (Latin American)', 'el', 'Intermediate risk',                     'Riesgo intermedio'),
('Spanish (Latin American)', 'el', 'High risk',                             'Riesgo alto'),
('Spanish (Latin American)', 'el', 'Study conclusion for the PDF report',   'Conclusión del estudio para el informe PDF'),
('Spanish (Latin American)', 'el', 'Additional comments or recommendations','Comentarios adicionales o recomendaciones'),

-- --------------------------------------------------------------------------
-- Stress Test card – tabla de etapas
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Stage-by-Stage Records',                'Registro por Etapas'),
('Spanish (Latin American)', 'el', 'Add Stage',                             'Agregar Etapa'),
('Spanish (Latin American)', 'el', 'No stages recorded.',                   'No se registraron etapas.'),
('Spanish (Latin American)', 'el', 'Phase',                                 'Fase'),
('Spanish (Latin American)', 'el', 'Stage',                                 'Etapa'),
('Spanish (Latin American)', 'el', 'Elapsed (s)',                           'Tiempo (s)'),
('Spanish (Latin American)', 'el', 'Speed (km/h)',                          'Velocidad (km/h)'),
('Spanish (Latin American)', 'el', 'Grade (%)',                             'Pendiente (%)'),
('Spanish (Latin American)', 'el', 'BP Sys',                                'PAS'),
('Spanish (Latin American)', 'el', 'BP Dia',                                'PAD'),
('Spanish (Latin American)', 'el', 'Rest',                                  'Reposo'),
('Spanish (Latin American)', 'el', 'Exercise',                              'Esfuerzo'),
('Spanish (Latin American)', 'el', 'Recovery',                              'Recuperación'),
('Spanish (Latin American)', 'el', 'Remove stage',                          'Eliminar etapa'),

-- --------------------------------------------------------------------------
-- Echo card – títulos de página y navegación
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Echocardiogram – New Study',            'Ecocardiograma – Nuevo Estudio'),
('Spanish (Latin American)', 'el', 'Echocardiogram – View Study',           'Ecocardiograma – Ver Estudio'),
('Spanish (Latin American)', 'el', 'Echocardiogram Report',                 'Informe de Ecocardiograma'),
('Spanish (Latin American)', 'el', 'Doppler Echocardiogram Report',         'Informe de Ecocardiograma Doppler'),

-- --------------------------------------------------------------------------
-- Echo card – secciones del formulario y columnas del informe
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Left Ventricle – Dimensions & Systolic Function',
                                                                            'Ventrículo Izquierdo – Dimensiones y Función Sistólica'),
('Spanish (Latin American)', 'el', 'Left Ventricle – Diastolic Function',   'Ventrículo Izquierdo – Función Diastólica'),
('Spanish (Latin American)', 'el', 'Atria',                                 'Aurículas'),
('Spanish (Latin American)', 'el', 'Right Ventricle & Pulmonary Pressure',  'Ventrículo Derecho y Presión Pulmonar'),
('Spanish (Latin American)', 'el', 'Aorta',                                 'Aorta'),
('Spanish (Latin American)', 'el', 'Valves',                                'Válvulas'),
('Spanish (Latin American)', 'el', 'Pericardium',                           'Pericardio'),
('Spanish (Latin American)', 'el', 'Report',                                'Informe'),
('Spanish (Latin American)', 'el', 'Aortic Valve',                          'Válvula Aórtica'),
('Spanish (Latin American)', 'el', 'Mitral Valve',                          'Válvula Mitral'),
('Spanish (Latin American)', 'el', 'Tricuspid & Pulmonary Valves',          'Válvulas Tricuspídea y Pulmonar'),
('Spanish (Latin American)', 'el', 'LVEF Method',                           'Método de FEVI'),
('Spanish (Latin American)', 'el', 'Global Wall Motion',                    'Motilidad Global'),
('Spanish (Latin American)', 'el', 'Segmental Wall Motion Detail',          'Detalle de Motilidad Segmentaria'),
('Spanish (Latin American)', 'el', 'Additional Valve Findings',             'Otros Hallazgos Valvulares'),
('Spanish (Latin American)', 'el', 'Pericardial Effusion',                  'Derrame Pericárdico'),
('Spanish (Latin American)', 'el', 'Height (cm)',                           'Talla (cm)'),
('Spanish (Latin American)', 'el', 'Weight (kg)',                           'Peso (kg)'),
('Spanish (Latin American)', 'el', 'BSA (m²)',                              'SCT (m²)'),
('Spanish (Latin American)', 'el', 'Heart Rate',                            'Frecuencia Cardíaca'),
('Spanish (Latin American)', 'el', 'BP',                                    'TA'),
('Spanish (Latin American)', 'el', 'BSA',                                   'SCT'),
('Spanish (Latin American)', 'el', 'LV Mass',                               'Masa del VI'),
('Spanish (Latin American)', 'el', 'LV Mass Index',                         'Índice de Masa del VI'),
('Spanish (Latin American)', 'el', 'Wall Motion',                           'Motilidad Parietal'),
('Spanish (Latin American)', 'el', 'Detail',                                'Detalle'),
('Spanish (Latin American)', 'el', 'Dec. Time',                             'Tiempo de desaceleración'),
('Spanish (Latin American)', 'el', 'LA Diameter',                           'Diámetro AI'),
('Spanish (Latin American)', 'el', 'LA Volume Index',                       'Índice de Vol. AI'),
('Spanish (Latin American)', 'el', 'RA Area',                               'Área AD'),
('Spanish (Latin American)', 'el', 'RV Basal Diam.',                        'Diám. Basal VD'),
('Spanish (Latin American)', 'el', 'Aortic Root',                           'Raíz Aórtica'),
('Spanish (Latin American)', 'el', 'Ascending Aorta',                       'Aorta Ascendente'),
('Spanish (Latin American)', 'el', 'Valve',                                 'Válvula'),
('Spanish (Latin American)', 'el', 'Regurgitation',                         'Regurgitación'),
('Spanish (Latin American)', 'el', 'Stenosis',                              'Estenosis'),
('Spanish (Latin American)', 'el', 'Peak Vel. (m/s)',                       'Vel. pico (m/s)'),
('Spanish (Latin American)', 'el', 'Mean Grad. (mmHg)',                     'Grad. medio (mmHg)'),
('Spanish (Latin American)', 'el', 'Area (cm²)',                            'Área (cm²)'),
('Spanish (Latin American)', 'el', 'Aortic',                                'Aórtica'),
('Spanish (Latin American)', 'el', 'Mitral',                                'Mitral'),
('Spanish (Latin American)', 'el', 'Tricuspid',                             'Tricuspídea'),
('Spanish (Latin American)', 'el', 'Pulmonary',                             'Pulmonar'),
('Spanish (Latin American)', 'el', 'Method',                                'Método'),
('Spanish (Latin American)', 'el', 'Conclusion',                            'Conclusión'),
('Spanish (Latin American)', 'el', 'Comments',                              'Comentarios'),
('Spanish (Latin American)', 'el', 'DOB',                                   'Fecha de Nac.'),
('Spanish (Latin American)', 'el', 'Sex',                                   'Sexo'),
('Spanish (Latin American)', 'el', 'Status',                                'Estado'),

-- --------------------------------------------------------------------------
-- Validaciones y errores adicionales (CardInput y save handlers)
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', 'Cardiology Error',                       'Error de Cardiología'),
('Spanish (Latin American)', 'el', 'Validation Error',                       'Error de Validación'),
('Spanish (Latin American)', 'el', 'A conclusion is required to finalize the study.',
                                                                            'Se requiere una conclusión para finalizar el estudio.'),
('Spanish (Latin American)', 'el', 'The container does not match the patient or encounter.',
                                                                            'El contenedor no coincide con el paciente o la consulta.'),
('Spanish (Latin American)', 'el', 'Maximum of 100 stage records exceeded.', 'Se superó el límite máximo de 100 registros de etapas.'),
('Spanish (Latin American)', 'el', 'Invalid phase for stage: ',              'Fase inválida para la etapa: '),
('Spanish (Latin American)', 'el', 'Invalid number in field %s',             'Número inválido en el campo %s'),
('Spanish (Latin American)', 'el', 'Value in %s must be greater than or equal to %s',
                                                                            'El valor en %s debe ser mayor o igual a %s'),
('Spanish (Latin American)', 'el', 'Value in %s must be less than or equal to %s',
                                                                            'El valor en %s debe ser menor o igual a %s'),
('Spanish (Latin American)', 'el', 'Invalid integer in field %s',            'Entero inválido en el campo %s'),
('Spanish (Latin American)', 'el', 'Date and time required for %s',          'Fecha y hora requeridas para %s'),
('Spanish (Latin American)', 'el', 'Invalid date format in field %s. Expected Y-m-d H:i[:s]',
                                                                            'Formato de fecha inválido en el campo %s. Se esperaba Y-m-d H:i[:s]'),
('Spanish (Latin American)', 'el', 'Invalid option in field %s',             'Opción inválida en el campo %s'),

-- --------------------------------------------------------------------------
-- Nuevos módulos: ECG, Riesgo Preoperatorio y Certificado de Apto
-- --------------------------------------------------------------------------
('Spanish (Latin American)', 'el', '12-Lead Electrocardiogram',              'Electrocardiograma de 12 derivaciones'),
('Spanish (Latin American)', 'el', '12-Lead ECG',                            'ECG de 12 derivaciones'),
('Spanish (Latin American)', 'el', '12-Lead ECG – New Study',                'ECG de 12 derivaciones – Nuevo Estudio'),
('Spanish (Latin American)', 'el', '12-Lead ECG – View Study',               'ECG de 12 derivaciones – Ver Estudio'),
('Spanish (Latin American)', 'el', '12-Lead ECG Report',                     'Informe de ECG de 12 derivaciones'),
('Spanish (Latin American)', 'el', '12-Lead Electrocardiogram Report',       'Informe de Electrocardiograma de 12 derivaciones'),
('Spanish (Latin American)', 'el', 'Preoperative Cardiovascular Risk Assessment', 'Evaluación de Riesgo Cardiovascular Preoperatorio'),
('Spanish (Latin American)', 'el', 'Preoperative Risk',                      'Riesgo Preoperatorio'),
('Spanish (Latin American)', 'el', 'Preoperative Cardiovascular Risk',       'Riesgo Cardiovascular Preoperatorio'),
('Spanish (Latin American)', 'el', 'Preoperative Risk – New Assessment',     'Riesgo Preoperatorio – Nueva Evaluación'),
('Spanish (Latin American)', 'el', 'Preoperative Risk – View Assessment',    'Riesgo Preoperatorio – Ver Evaluación'),
('Spanish (Latin American)', 'el', 'Preoperative Risk Report',               'Informe de Riesgo Preoperatorio'),
('Spanish (Latin American)', 'el', 'Cardiology Fitness Certificate',         'Certificado de Apto Cardiológico'),
('Spanish (Latin American)', 'el', 'Cardiology Fitness Certificate – New Evaluation', 'Certificado de Apto Cardiológico – Nueva Evaluación'),
('Spanish (Latin American)', 'el', 'Fitness Certificate – View Evaluation',  'Certificado de Apto – Ver Evaluación'),
('Spanish (Latin American)', 'el', 'Cardiovascular Physical Activity Clearance', 'Apto Cardiovascular para Actividad Física'),
('Spanish (Latin American)', 'el', 'Print Certificate',                      'Imprimir Certificado'),
('Spanish (Latin American)', 'el', 'Print Certificate PDF',                  'Imprimir PDF del Certificado'),

-- Títulos de listas registradas en list_options
('Spanish (Latin American)', 'el', 'Cardiology - ECG rhythm',                'Cardiología – Ritmo del ECG'),
('Spanish (Latin American)', 'el', 'Cardiology - ECG AV conduction',         'Cardiología – Conducción AV del ECG'),
('Spanish (Latin American)', 'el', 'Cardiology - ECG ventricular conduction','Cardiología – Conducción ventricular del ECG'),
('Spanish (Latin American)', 'el', 'Cardiology - ECG chamber hypertrophy',   'Cardiología – Hipertrofia de cavidades del ECG'),
('Spanish (Latin American)', 'el', 'Cardiology - ECG ST-T changes',          'Cardiología – Alteraciones ST-T del ECG'),
('Spanish (Latin American)', 'el', 'Cardiology - Surgery urgency',           'Cardiología – Urgencia quirúrgica'),
('Spanish (Latin American)', 'el', 'Cardiology - Surgery risk class',        'Cardiología – Clase de riesgo quirúrgico'),
('Spanish (Latin American)', 'el', 'Cardiology - Functional capacity',       'Cardiología – Capacidad funcional'),
('Spanish (Latin American)', 'el', 'Cardiology - ASA physical status',       'Cardiología – Estado físico ASA'),
('Spanish (Latin American)', 'el', 'Cardiology - Preop recommendation',      'Cardiología – Recomendación preoperatoria'),
('Spanish (Latin American)', 'el', 'Cardiology - Fitness purpose',           'Cardiología – Finalidad del apto'),
('Spanish (Latin American)', 'el', 'Cardiology - Fitness ECG result',        'Cardiología – Resultado de ECG para apto'),
('Spanish (Latin American)', 'el', 'Cardiology - Fitness determination',     'Cardiología – Dictamen de aptitud'),

-- Opciones de listas de ECG
('Spanish (Latin American)', 'el', 'Sinus rhythm',                           'Ritmo sinusal'),
('Spanish (Latin American)', 'el', 'Sinus bradycardia',                      'Bradicardia sinusal'),
('Spanish (Latin American)', 'el', 'Sinus tachycardia',                      'Taquicardia sinusal'),
('Spanish (Latin American)', 'el', 'Atrial fibrillation',                    'Fibrilación auricular'),
('Spanish (Latin American)', 'el', 'Atrial flutter',                         'Aleteo auricular'),
('Spanish (Latin American)', 'el', 'Atrial tachycardia',                     'Taquicardia auricular'),
('Spanish (Latin American)', 'el', 'Junctional rhythm',                      'Ritmo de la unión'),
('Spanish (Latin American)', 'el', 'Paced rhythm',                           'Ritmo de marcapasos'),
('Spanish (Latin American)', 'el', 'Other rhythm',                           'Otro ritmo'),
('Spanish (Latin American)', 'el', 'Normal AV conduction',                   'Conducción AV normal'),
('Spanish (Latin American)', 'el', 'First-degree AV block',                  'Bloqueo AV de primer grado'),
('Spanish (Latin American)', 'el', 'Second-degree AV block Mobitz I',        'Bloqueo AV de segundo grado Mobitz I'),
('Spanish (Latin American)', 'el', 'Second-degree AV block Mobitz II',       'Bloqueo AV de segundo grado Mobitz II'),
('Spanish (Latin American)', 'el', 'Third-degree (complete) AV block',       'Bloqueo AV de tercer grado (completo)'),
('Spanish (Latin American)', 'el', 'Normal intraventricular conduction',     'Conducción intraventricular normal'),
('Spanish (Latin American)', 'el', 'Right bundle branch block (RBBB)',       'Bloqueo completo de rama derecha (BCRD)'),
('Spanish (Latin American)', 'el', 'Left bundle branch block (LBBB)',        'Bloqueo completo de rama izquierda (BCRI)'),
('Spanish (Latin American)', 'el', 'Left anterior fascicular block (LAFB)',  'Hemibloqueo anterior izquierdo (HBAI)'),
('Spanish (Latin American)', 'el', 'Left posterior fascicular block (LPFB)', 'Hemibloqueo posterior izquierdo (HBPI)'),
('Spanish (Latin American)', 'el', 'Bifascicular block',                     'Bloqueo bifascicular'),
('Spanish (Latin American)', 'el', 'Nonspecific intraventricular conduction delay', 'Trastorno inespecífico de la conducción intraventricular'),
('Spanish (Latin American)', 'el', 'No hypertrophy',                         'Sin hipertrofia'),
('Spanish (Latin American)', 'el', 'Left ventricular hypertrophy (LVH)',     'Hipertrofia ventricular izquierda (HVI)'),
('Spanish (Latin American)', 'el', 'Right ventricular hypertrophy (RVH)',    'Hipertrofia ventricular derecha (HVD)'),
('Spanish (Latin American)', 'el', 'Biventricular hypertrophy',              'Hipertrofia biventricular'),
('Spanish (Latin American)', 'el', 'Normal repolarization',                  'Repolarización normal'),
('Spanish (Latin American)', 'el', 'ST-segment depression',                  'Infradesnivel del segmento ST'),
('Spanish (Latin American)', 'el', 'ST-segment elevation',                   'Supradesnivel del segmento ST'),
('Spanish (Latin American)', 'el', 'T-wave inversion',                       'Inversión de onda T'),
('Spanish (Latin American)', 'el', 'Nonspecific ST-T abnormalities',         'Alteraciones inespecíficas del ST-T'),

-- Opciones de listas de Riesgo Preoperatorio
('Spanish (Latin American)', 'el', 'Elective',                               'Electiva'),
('Spanish (Latin American)', 'el', 'Urgent',                                 'Urgente'),
('Spanish (Latin American)', 'el', 'Emergency',                              'Emergencia'),
('Spanish (Latin American)', 'el', 'Low risk (< 1%)',                        'Riesgo bajo (< 1%)'),
('Spanish (Latin American)', 'el', 'Intermediate risk (1-5%)',               'Riesgo intermedio (1-5%)'),
('Spanish (Latin American)', 'el', 'High risk (> 5%)',                       'Riesgo alto (> 5%)'),
('Spanish (Latin American)', 'el', 'Unknown / Not assessed',                 'Desconocida / No evaluada'),
('Spanish (Latin American)', 'el', 'Poor (< 4 METs)',                        'Pobre (< 4 METs)'),
('Spanish (Latin American)', 'el', 'Moderate (4-10 METs)',                   'Moderada (4-10 METs)'),
('Spanish (Latin American)', 'el', 'Good (> 10 METs)',                       'Buena (> 10 METs)'),
('Spanish (Latin American)', 'el', 'ASA I - Normal healthy patient',         'ASA I – Paciente sano'),
('Spanish (Latin American)', 'el', 'ASA II - Patient with mild systemic disease', 'ASA II – Enfermedad sistémica leve'),
('Spanish (Latin American)', 'el', 'ASA III - Patient with severe systemic disease', 'ASA III – Enfermedad sistémica severa'),
('Spanish (Latin American)', 'el', 'ASA IV - Severe systemic disease that is a constant threat to life', 'ASA IV – Enfermedad severa con amenaza constante a la vida'),
('Spanish (Latin American)', 'el', 'ASA V - Moribund patient not expected to survive without operation', 'ASA V – Paciente moribundo'),
('Spanish (Latin American)', 'el', 'ASA VI - Brain-dead patient for organ donation', 'ASA VI – Muerte cerebral para donación de órganos'),
('Spanish (Latin American)', 'el', 'Proceed with surgery as planned',        'Proceder con la cirugía según lo planificado'),
('Spanish (Latin American)', 'el', 'Proceed with medical optimization',       'Proceder con optimización médica'),
('Spanish (Latin American)', 'el', 'Further cardiovascular evaluation recommended', 'Se recomienda evaluación cardiovascular complementaria'),
('Spanish (Latin American)', 'el', 'Postpone elective surgery',              'Postergar cirugía electiva'),

-- Opciones de listas de Apto Cardiológico
('Spanish (Latin American)', 'el', 'Recreational sports',                    'Deporte recreativo'),
('Spanish (Latin American)', 'el', 'Competitive sports',                     'Deporte competitivo'),
('Spanish (Latin American)', 'el', 'Gym / Fitness center',                   'Gimnasio / Centro de entrenamiento'),
('Spanish (Latin American)', 'el', 'Occupational / Work clearance',          'Apto laboral / Ocupacional'),
('Spanish (Latin American)', 'el', 'School / University physical education', 'Educación física escolar / universitaria'),
('Spanish (Latin American)', 'el', 'High altitude / Adventure travel',       'Viaje de aventura / Alta montaña'),
('Spanish (Latin American)', 'el', 'Other activity',                         'Otra actividad'),
('Spanish (Latin American)', 'el', 'Normal ECG',                             'ECG normal'),
('Spanish (Latin American)', 'el', 'Abnormal ECG',                           'ECG anormal'),
('Spanish (Latin American)', 'el', 'Not performed',                          'No realizado'),
('Spanish (Latin American)', 'el', 'Fit without restrictions',               'Apto sin restricciones'),
('Spanish (Latin American)', 'el', 'Fit with restrictions',                  'Apto con restricciones'),
('Spanish (Latin American)', 'el', 'Temporarily or permanently unfit',        'No apto temporal o permanentemente'),
('Spanish (Latin American)', 'el', 'Clearance pending further evaluation',   'Apto pendiente de estudios complementarios'),

-- Etiquetas de campos y textos de los formularios
('Spanish (Latin American)', 'el', 'Study Date & Time',                      'Fecha y hora del estudio'),
('Spanish (Latin American)', 'el', 'Evaluation Date & Time',                 'Fecha y hora de la evaluación'),
('Spanish (Latin American)', 'el', 'Rhythm & Intervals',                     'Ritmo e intervalos'),
('Spanish (Latin American)', 'el', 'Heart Rate (bpm)',                       'Frecuencia cardíaca (lpm)'),
('Spanish (Latin American)', 'el', 'Select rhythm',                          'Seleccionar ritmo'),
('Spanish (Latin American)', 'el', 'Electrical Axis (°)',                    'Eje eléctrico (°)'),
('Spanish (Latin American)', 'el', 'PR Interval (ms)',                       'Intervalo PR (ms)'),
('Spanish (Latin American)', 'el', 'QRS Duration (ms)',                      'Duración del QRS (ms)'),
('Spanish (Latin American)', 'el', 'QT Interval (ms)',                       'Intervalo QT (ms)'),
('Spanish (Latin American)', 'el', 'QTc Bazett (ms)',                        'QTc Bazett (ms)'),
('Spanish (Latin American)', 'el', 'QTc Fridericia (ms)',                    'QTc Fridericia (ms)'),
('Spanish (Latin American)', 'el', 'Conduction & Morphology',                'Conducción y morfología'),
('Spanish (Latin American)', 'el', 'AV Conduction',                          'Conducción AV'),
('Spanish (Latin American)', 'el', 'Select AV conduction',                   'Seleccionar conducción AV'),
('Spanish (Latin American)', 'el', 'Ventricular Conduction',                 'Conducción ventricular'),
('Spanish (Latin American)', 'el', 'Select intraventricular conduction',     'Seleccionar conducción intraventricular'),
('Spanish (Latin American)', 'el', 'Chamber Hypertrophy',                    'Hipertrofia de cavidades'),
('Spanish (Latin American)', 'el', 'Select hypertrophy',                     'Seleccionar hipertrofia'),
('Spanish (Latin American)', 'el', 'ST-T Segment Changes',                   'Alteraciones del segmento ST-T'),
('Spanish (Latin American)', 'el', 'Select ST-T changes',                    'Seleccionar alteraciones ST-T'),
('Spanish (Latin American)', 'el', 'ST-T Changes Detail',                    'Detalle de alteraciones ST-T'),
('Spanish (Latin American)', 'el', 'Pathologic Q Waves Present',             'Presencia de ondas Q patológicas'),
('Spanish (Latin American)', 'el', 'Pathologic Q Leads',                     'Derivaciones con Q patológica'),
('Spanish (Latin American)', 'el', 'Electrocardiographic Findings',          'Hallazgos electrocardiográficos'),
('Spanish (Latin American)', 'el', 'Interpretation & Conclusion',            'Interpretación y conclusión'),
('Spanish (Latin American)', 'el', 'Comments & Recommendations',             'Comentarios y recomendaciones'),
('Spanish (Latin American)', 'el', 'Electrical Axis',                        'Eje eléctrico'),
('Spanish (Latin American)', 'el', 'PR Interval',                            'Intervalo PR'),
('Spanish (Latin American)', 'el', 'QRS Duration',                           'Duración del QRS'),
('Spanish (Latin American)', 'el', 'QT Interval',                            'Intervalo QT'),
('Spanish (Latin American)', 'el', 'QTc (Bazett)',                           'QTc (Bazett)'),
('Spanish (Latin American)', 'el', 'QTc (Fridericia)',                       'QTc (Fridericia)'),
('Spanish (Latin American)', 'el', 'ST-T Changes',                           'Alteraciones del ST-T'),
('Spanish (Latin American)', 'el', 'ST-T Detail',                            'Detalle del ST-T'),
('Spanish (Latin American)', 'el', 'Pathologic Q Waves',                     'Ondas Q patológicas'),
('Spanish (Latin American)', 'el', 'Q Leads',                                'Derivaciones con Q'),
('Spanish (Latin American)', 'el', 'Detailed Findings',                      'Hallazgos detallados'),
('Spanish (Latin American)', 'el', 'Interpreting Physician',                 'Médico informante'),

('Spanish (Latin American)', 'el', 'Planned Surgery & Urgency',              'Cirugía planificada y urgencia'),
('Spanish (Latin American)', 'el', 'Planned Surgery / Procedure',            'Cirugía / Procedimiento planificado'),
('Spanish (Latin American)', 'el', 'Surgery Date',                           'Fecha de la cirugía'),
('Spanish (Latin American)', 'el', 'Surgical Urgency',                       'Urgencia quirúrgica'),
('Spanish (Latin American)', 'el', 'Select urgency',                         'Seleccionar urgencia'),
('Spanish (Latin American)', 'el', 'Inherent Surgical Risk (Physician-set)', 'Riesgo quirúrgico inherente (definido por el médico)'),
('Spanish (Latin American)', 'el', 'Inherent Surgical Risk',                 'Riesgo quirúrgico inherente'),
('Spanish (Latin American)', 'el', 'Select surgical risk',                   'Seleccionar riesgo quirúrgico'),
('Spanish (Latin American)', 'el', 'Functional Capacity',                    'Capacidad funcional'),
('Spanish (Latin American)', 'el', 'Select capacity',                        'Seleccionar capacidad'),
('Spanish (Latin American)', 'el', 'ASA Physical Status',                    'Estado físico ASA'),
('Spanish (Latin American)', 'el', 'Select ASA status',                      'Seleccionar estado ASA'),
('Spanish (Latin American)', 'el', 'Vital Signs & Cardiac Function',         'Signos vitales y función cardíaca'),
('Spanish (Latin American)', 'el', 'Revised Cardiac Risk Index (Lee RCRI)',  'Índice de Riesgo Cardíaco Revisado (Lee RCRI)'),
('Spanish (Latin American)', 'el', 'High-risk surgery (intraperitoneal, intrathoracic, or suprainguinal vascular)', 'Cirugía de alto riesgo (intraperitoneal, intratorácica o vascular suprainguinal)'),
('Spanish (Latin American)', 'el', 'History of ischemic heart disease (prior MI, positive stress test, angina, nitrate use)', 'Antecedente de cardiopatía isquémica (IAM previo, ergometría positiva, angina, nitratos)'),
('Spanish (Latin American)', 'el', 'History of congestive heart failure',    'Antecedente de insuficiencia cardíaca congestiva'),
('Spanish (Latin American)', 'el', 'History of cerebrovascular disease (stroke or TIA)', 'Antecedente de enfermedad cerebrovascular (ACV o AIT)'),
('Spanish (Latin American)', 'el', 'Preoperative insulin therapy for diabetes mellitus', 'Tratamiento preoperatorio con insulina para diabetes mellitus'),
('Spanish (Latin American)', 'el', 'Preoperative serum creatinine > 2.0 mg/dL (> 177 µmol/L)', 'Creatinina sérica preoperatoria > 2,0 mg/dL (> 177 µmol/L)'),
('Spanish (Latin American)', 'el', 'Score',                                  'Puntaje'),
('Spanish (Latin American)', 'el', 'Class',                                  'Clase'),
('Spanish (Latin American)', 'el', 'Complementary Studies & Medications',    'Estudios complementarios y medicaciones'),
('Spanish (Latin American)', 'el', 'Preoperative ECG Summary',               'Resumen de ECG preoperatorio'),
('Spanish (Latin American)', 'el', 'Echocardiogram Summary',                 'Resumen de ecocardiograma'),
('Spanish (Latin American)', 'el', 'Current Medications',                    'Medicación habitual'),
('Spanish (Latin American)', 'el', 'Relevant Laboratory Values',             'Valores de laboratorio relevantes'),
('Spanish (Latin American)', 'el', 'Recommendation & Perioperative Plan',    'Recomendación y plan perioperatorio'),
('Spanish (Latin American)', 'el', 'Cardiovascular Recommendation',          'Recomendación cardiovascular'),
('Spanish (Latin American)', 'el', 'Select recommendation',                  'Seleccionar recomendación'),
('Spanish (Latin American)', 'el', 'Perioperative Management Instructions',  'Instrucciones de manejo perioperatorio'),
('Spanish (Latin American)', 'el', 'Conclusion & Risk Assessment Summary',   'Conclusión y resumen del riesgo'),
('Spanish (Latin American)', 'el', 'Additional Comments',                    'Comentarios adicionales'),
('Spanish (Latin American)', 'el', 'Surgery / Procedure',                    'Cirugía / Procedimiento'),
('Spanish (Latin American)', 'el', 'Lee RCRI Score',                         'Puntaje Lee RCRI'),
('Spanish (Latin American)', 'el', 'Lee RCRI Class',                         'Clase Lee RCRI'),
('Spanish (Latin American)', 'el', 'Identified Risk Factors',                'Factores de riesgo identificados'),
('Spanish (Latin American)', 'el', 'None (Score 0)',                         'Ninguno (Puntaje 0)'),
('Spanish (Latin American)', 'el', 'High-risk surgery',                      'Cirugía de alto riesgo'),
('Spanish (Latin American)', 'el', 'History of ischemic heart disease',      'Antecedente de cardiopatía isquémica'),
('Spanish (Latin American)', 'el', 'History of cerebrovascular disease',     'Antecedente de enfermedad cerebrovascular'),
('Spanish (Latin American)', 'el', 'Preoperative insulin therapy for diabetes', 'Insulinoterapia preoperatoria para diabetes'),
('Spanish (Latin American)', 'el', 'Preoperative serum creatinine > 2.0 mg/dL', 'Creatinina sérica preoperatoria > 2,0 mg/dL'),
('Spanish (Latin American)', 'el', 'Relevant Labs',                          'Laboratorio relevante'),
('Spanish (Latin American)', 'el', 'Cardiovascular Clearance Recommendation','Recomendación de aptitud cardiovascular'),
('Spanish (Latin American)', 'el', 'Conclusion & Assessment Summary',        'Conclusión y resumen de la evaluación'),
('Spanish (Latin American)', 'el', 'Evaluating Cardiologist',                'Cardiólogo evaluador'),
('Spanish (Latin American)', 'el', 'A recommendation is required to finalize the study.', 'Se requiere una recomendación para finalizar el estudio.'),

('Spanish (Latin American)', 'el', 'Purpose & Examination Context',          'Finalidad y contexto del examen'),
('Spanish (Latin American)', 'el', 'Evaluation Purpose',                     'Finalidad de la evaluación'),
('Spanish (Latin American)', 'el', 'Select purpose',                         'Seleccionar finalidad'),
('Spanish (Latin American)', 'el', 'Specific Sport / Activity / Job Detail', 'Detalle específico del deporte / actividad / trabajo'),
('Spanish (Latin American)', 'el', 'Clinical History & Symptoms',            'Antecedentes clínicos y síntomas'),
('Spanish (Latin American)', 'el', 'Cardiovascular Symptoms / History',      'Síntomas cardiovasculares / Antecedentes'),
('Spanish (Latin American)', 'el', 'Family History',                         'Antecedentes familiares'),
('Spanish (Latin American)', 'el', 'Physical Examination & Vitals',          'Examen físico y signos vitales'),
('Spanish (Latin American)', 'el', 'BMI (kg/m²)',                            'IMC (kg/m²)'),
('Spanish (Latin American)', 'el', 'Physical Examination Findings',          'Hallazgos del examen físico'),
('Spanish (Latin American)', 'el', 'Physical Examination',                   'Examen físico'),
('Spanish (Latin American)', 'el', 'Electrocardiogram & Additional Studies', 'Electrocardiograma y estudios adicionales'),
('Spanish (Latin American)', 'el', 'Resting ECG Result',                     'Resultado del ECG en reposo'),
('Spanish (Latin American)', 'el', 'Select ECG result',                      'Seleccionar resultado del ECG'),
('Spanish (Latin American)', 'el', 'ECG Summary / Findings',                 'Resumen / Hallazgos del ECG'),
('Spanish (Latin American)', 'el', 'Additional Diagnostic Studies',          'Estudios diagnósticos adicionales'),
('Spanish (Latin American)', 'el', 'Certificate Determination & Validity',   'Dictamen de aptitud y validez del certificado'),
('Spanish (Latin American)', 'el', 'Fitness Determination',                  'Dictamen de aptitud'),
('Spanish (Latin American)', 'el', 'Select determination',                   'Seleccionar dictamen'),
('Spanish (Latin American)', 'el', 'Valid Until (Expiration Date)',          'Válido hasta (Fecha de vencimiento)'),
('Spanish (Latin American)', 'el', 'Activity Restrictions',                  'Restricciones para la actividad'),
('Spanish (Latin American)', 'el', 'Required when Fit with Restrictions',    'Requerido cuando es Apto con restricciones'),
('Spanish (Latin American)', 'el', 'Medical certificate conclusion printed on document', 'Conclusión del certificado médico impresa en el documento'),
('Spanish (Latin American)', 'el', 'Comments / Recommendations',             'Comentarios / Recomendaciones'),
('Spanish (Latin American)', 'el', 'Cardiovascular Determination',           'Dictamen Cardiovascular'),
('Spanish (Latin American)', 'el', 'Validity Expiration Date',              'Fecha de vencimiento de la validez'),
('Spanish (Latin American)', 'el', 'Issued on',                              'Emitido el'),
('Spanish (Latin American)', 'el', 'License / Reg. No.',                     'Matrícula profesional'),
('Spanish (Latin American)', 'el', 'Cardiologist / Physician',               'Médico Cardiólogo'),
('Spanish (Latin American)', 'el', 'Based on the examination performed on %s, the patient is considered %s.', 'En base al examen realizado el %s, el paciente se considera %s.'),
('Spanish (Latin American)', 'el', 'A determination is required to finalize the study.', 'Se requiere un dictamen de aptitud para finalizar el estudio.'),
('Spanish (Latin American)', 'el', 'A validity date is required to finalize the study.', 'Se requiere una fecha de validez para finalizar el estudio.'),
('Spanish (Latin American)', 'el', 'The validity date must be after the study date.', 'La fecha de validez debe ser posterior a la fecha del estudio.'),
('Spanish (Latin American)', 'el', 'Restrictions must be specified when the determination is Fit with Restrictions.', 'Deben especificarse las restricciones cuando el dictamen es Apto con restricciones.'),
('Spanish (Latin American)', 'el', 'years',                                  'años')

;

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

