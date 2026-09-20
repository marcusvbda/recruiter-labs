<?php

return [
    'indicator' => [
        'label' => 'IA de RecruiterLabs',
        'open' => 'Abrir Actividad de IA',
    ],

    'state' => [
        'working' => 'Trabajando',
        'waiting' => 'Esperando',
        'blocked' => 'Bloqueado',
        'up_to_date' => 'Al día',
    ],

    'panel' => [
        'heading' => 'Actividad de IA',
        'description' => 'Lo que la IA de RecruiterLabs está haciendo por este espacio de trabajo ahora.',
        'working_heading' => 'Trabajando ahora',
        'waiting_heading' => 'Esperando',
        'blocked_heading' => 'Necesita tu atención',
        'recent_heading' => 'Completado recientemente',
        'empty' => 'Nada en ejecución. Todo lo que la IA podía hacer está al día.',
        'recent_empty' => 'Todavía no se ha completado ningún trabajo de IA.',
    ],

    'role' => [
        'criteria_analyst' => 'Analista de criterios',
        'candidate_reviewer' => 'Revisor de candidatos',
        'talent_matcher' => 'Buscador de talento',
        'candidate_importer' => 'Importador de candidatos',
    ],

    'work' => [
        'criteria_preparing' => 'Preparando los criterios de :job',
        'criteria_waiting_allowance' => 'Los criterios de :job están en espera',
        'criteria_failed' => 'No se pudieron preparar los criterios de :job',

        'evaluating_evaluating' => 'Evaluando a :candidate para :job',
        'evaluating_waiting_criteria' => ':candidate está esperando evaluación para :job',
        'evaluating_waiting_allowance' => 'La evaluación de :candidate para :job está en espera',
        'evaluating_failed' => 'No se pudo completar la evaluación de :candidate para :job',
        'evaluating_many' => '{1} 1 candidato en evaluación para :job|[2,*] :count candidatos en evaluación para :job',

        'sourcing_reviewing' => 'Revisando el grupo de talento para :job',
        'sourcing_waiting_allowance' => 'La revisión del grupo de talento de :job está en espera',
        'sourcing_failed' => 'No se pudo completar la revisión del grupo de talento de :job',
        'sourcing_done' => 'Grupo de talento revisado para :job',

        'import_importing' => 'Importando candidatos desde :source',
        'import_paused' => 'La importación de candidatos desde :source está pausada',
        'import_failed' => 'No se pudo completar la importación de candidatos desde :source',
        'import_done' => 'Candidatos importados desde :source',
    ],

    'done' => [
        'evaluated' => ':candidate evaluado para :job',
        'evaluated_many' => '{1} 1 candidato evaluado para :job|[2,*] :count candidatos evaluados para :job',
        'criteria' => 'Criterios preparados para :job',
    ],

    'reason' => [
        'job_criteria' => 'Esperando los criterios del puesto',
        'ai_allowance' => 'Esperando saldo de IA',
        'criteria_failed' => 'No se pudieron preparar los criterios del puesto',
        'evaluation_failed' => 'No se pudo completar la evaluación del candidato',
        'sourcing_failed' => 'No se pudo completar la revisión del grupo de talento',
        'import_paused' => 'Esperando que reanudes la importación',
        'import_failed' => 'No se pudo completar la importación de candidatos',
    ],

    'fallback' => [
        'candidate' => 'un candidato',
        'job' => 'un puesto',
    ],

    'provenance' => [
        'generated' => 'Generado por IA',
        'assisted' => 'Asistido por IA',
        'tooltip' => 'Esta sección fue producida por la IA de RecruiterLabs. Las decisiones de contratación siguen siendo tuyas.',
    ],
];
