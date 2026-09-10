<?php

return [
    'errors' => [
        'dismissed_must_be_restored_first' => 'Este candidato foi descartado para esta vaga. Restaure-o antes de considerá-lo novamente.',
        'not_dismissed' => 'Apenas um candidato descartado pode ser restaurado.',
        'candidate_already_in_job' => 'Este candidato já faz parte do processo seletivo desta vaga.',
    ],

    'not_confirmed' => [
        'title' => 'Confirme os critérios de avaliação para iniciar o sourcing',
        'description' => 'O sourcing pesquisa os candidatos já existentes no workspace em relação aos critérios de contratação confirmados desta vaga. Confirme os critérios primeiro e depois solicite correspondências.',
        'action' => 'Revisar critérios de avaliação',
    ],

    'status' => [
        'not_started' => 'Ainda não pesquisado',
        'searching' => 'Pesquisando',
        'completed' => 'Concluída',
        'failed' => 'A pesquisa falhou',
        'blocked' => 'Bloqueada — cota de IA indisponível',
        'outdated' => 'Desatualizada — os critérios mudaram',
        'predates_pool' => 'Novos candidatos ou currículos adicionados — atualize para incluí-los',
    ],

    'panel' => [
        'status_label' => 'Status do sourcing',
        'find_matches' => 'Buscar candidatos',
        'outdated_hint' => 'Estes resultados usaram uma versão anterior dos critérios. Execute uma nova busca para atualizá-los.',
        'search_started' => 'Busca de sourcing iniciada. Pode levar um tempo para um talent pool grande.',
        'saved' => 'Candidato salvo para esta vaga.',
        'dismissed' => 'Candidato descartado para esta vaga.',
        'restored' => 'Candidato restaurado para a lista de sugestões.',
        'candidate_removed' => 'Candidato removido',
        'no_talent_pool' => 'Ainda não existe um talent pool interno. O sourcing terá candidatos para considerar assim que houver candidaturas ou candidatos adicionados ao workspace.',
        'no_matches_yet' => 'Execute uma busca para ver possíveis correspondências entre seus candidatos existentes.',
        'no_current_matches' => 'Nenhuma correspondência atual. Execute uma nova busca quando houver candidatos ou informações novas.',
        'summary' => ':considered candidatos revisados · :matched possíveis correspondências · :insufficient candidatos com informação insuficiente para uma correspondência significativa',
        'insufficient_information' => 'Informação insuficiente — não há material suficiente enviado para avaliar contra estes critérios.',
        'dismissed_heading' => 'Descartados',
        'state_saved' => 'Salvo',
        'already_in_job' => 'Já está no processo desta vaga',
        'restore_action' => 'Restaurar',
        'save_action' => 'Salvar',
        'dismiss_action' => 'Descartar',
        'add_to_job_action' => 'Adicionar à vaga',
        'open_candidate_action' => 'Abrir candidato',
    ],

    'match' => [
        'potential_match_label' => 'Correspondência potencial',
        'evidence_coverage_label' => 'Cobertura de evidências',
        'confidence_label' => 'Confiança',
        'confidence' => [
            'high' => 'Alta',
            'medium' => 'Média',
            'low' => 'Baixa',
        ],
        'not_assessed' => 'Informação insuficiente',
    ],

    'criteria' => [
        'strong_support_heading' => 'Suporte forte',
        'needs_validation_heading' => 'Precisa de validação',
        'insufficient_heading' => 'Informação insuficiente',
        'weight_label' => 'peso :weight/10',
        'evidence_submitted_on' => 'enviado em :date',
    ],

    'history' => [
        'heading' => 'Interação anterior',
        'not_proof_of_fit' => 'Apenas contexto histórico — não afeta esta correspondência potencial.',
        'applied_on' => 'candidatou-se em :date',
        'reached_final_stage' => 'alcançou uma etapa final',
        'was_hired' => 'contratado',
        'closed' => 'processo encerrado',
        'more' => '+:count mais',
    ],
];
