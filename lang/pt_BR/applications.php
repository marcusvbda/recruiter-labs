<?php

return [
    'label' => 'Candidatura',
    'plural_label' => 'Candidaturas',
    'pipeline' => [
        'title' => 'Pipeline: :job',
        'view_kanban' => 'Visualização Kanban',
        'view_list' => 'Visualização em lista',
        'add_candidate' => 'Adicionar candidato',
        'no_candidates' => 'Ainda não há candidatos nesta coluna.',
        'select_candidate' => 'Candidato',
        'candidate_added' => 'Candidato adicionado ao pipeline.',
        'no_eligible_candidates' => 'Todos os candidatos já foram adicionados a este pipeline.',
        'no_statuses' => 'Esta empresa ainda não tem status configurados, portanto não é possível adicionar um candidato ao pipeline.',
        'already_added' => 'Este candidato já foi adicionado a este pipeline.',
    ],
    'fields' => [
        'candidate' => 'Candidato',
        'status' => 'Status',
        'created_at' => 'Candidatura em',
    ],
];
