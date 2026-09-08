<?php

return [
    'errors' => [
        'dismissed_must_be_restored_first' => 'Este candidato fue descartado para esta vacante. Restáuralo primero para volver a considerarlo.',
        'not_dismissed' => 'Solo se puede restaurar un candidato descartado.',
        'candidate_already_in_job' => 'Este candidato ya forma parte del proceso de selección de esta vacante.',
    ],

    'not_confirmed' => [
        'title' => 'Confirma los criterios de evaluación para iniciar la búsqueda',
        'description' => 'La búsqueda de candidatos revisa a los candidatos existentes del workspace contra los criterios de contratación confirmados de la vacante. Confirma los criterios primero y luego solicita coincidencias.',
        'action' => 'Revisar criterios de evaluación',
    ],

    'status' => [
        'not_started' => 'Aún sin búsqueda',
        'searching' => 'Buscando',
        'completed' => 'Completada',
        'failed' => 'La búsqueda falló',
        'blocked' => 'Bloqueada — asignación de IA no disponible',
        'outdated' => 'Desactualizada — los criterios cambiaron',
    ],

    'panel' => [
        'status_label' => 'Estado de la búsqueda',
        'find_matches' => 'Buscar coincidencias',
        'outdated_hint' => 'Estos resultados usaron una versión anterior de los criterios. Ejecuta una nueva búsqueda para actualizarlos.',
        'search_started' => 'Búsqueda de candidatos iniciada. Puede tardar si el talent pool es grande.',
        'saved' => 'Candidato guardado para esta vacante.',
        'dismissed' => 'Candidato descartado para esta vacante.',
        'restored' => 'Candidato restaurado a la lista de sugerencias.',
        'candidate_removed' => 'Candidato eliminado',
        'no_talent_pool' => 'Aún no existe un talent pool interno. La búsqueda tendrá candidatos para considerar cuando existan postulaciones o candidatos agregados al workspace.',
        'no_matches_yet' => 'Ejecuta una búsqueda para ver posibles coincidencias entre tus candidatos existentes.',
        'no_current_matches' => 'No hay coincidencias actuales. Ejecuta una nueva búsqueda cuando existan candidatos o información nueva.',
        'summary' => ':considered candidatos revisados · :matched posibles coincidencias · :insufficient candidatos con información insuficiente para una coincidencia significativa',
        'insufficient_information' => 'Información insuficiente — no hay suficiente material enviado para evaluarlo contra estos criterios.',
        'dismissed_heading' => 'Descartados',
        'state_saved' => 'Guardado',
        'already_in_job' => 'Ya está en el proceso de esta vacante',
        'restore_action' => 'Restaurar',
        'save_action' => 'Guardar',
        'dismiss_action' => 'Descartar',
        'add_to_job_action' => 'Agregar a la vacante',
        'open_candidate_action' => 'Abrir candidato',
    ],

    'match' => [
        'potential_match_label' => 'Coincidencia potencial',
        'evidence_coverage_label' => 'Cobertura de evidencia',
        'confidence_label' => 'Confianza',
        'confidence' => [
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
        ],
        'not_assessed' => 'Información insuficiente',
    ],

    'criteria' => [
        'strong_support_heading' => 'Soporte sólido',
        'needs_validation_heading' => 'Necesita validación',
        'insufficient_heading' => 'Información insuficiente',
        'weight_label' => 'peso :weight/10',
        'evidence_submitted_on' => 'enviado el :date',
    ],

    'history' => [
        'heading' => 'Interacción previa',
        'not_proof_of_fit' => 'Solo contexto histórico — no afecta esta coincidencia potencial.',
        'applied_on' => 'postuló el :date',
        'reached_final_stage' => 'alcanzó una etapa final',
        'was_hired' => 'contratado',
        'closed' => 'proceso cerrado',
        'more' => '+:count más',
    ],
];
