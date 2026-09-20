<?php

return [
    'indicator' => [
        'label' => 'IA RecruiterLabs',
        'open' => 'Abrir Atividade da IA',
    ],

    'state' => [
        'working' => 'Trabalhando',
        'waiting' => 'Aguardando',
        'blocked' => 'Bloqueado',
        'up_to_date' => 'Em dia',
    ],

    'panel' => [
        'heading' => 'Atividade da IA',
        'description' => 'O que a IA do RecruiterLabs está fazendo por este workspace agora.',
        'working_heading' => 'Trabalhando agora',
        'waiting_heading' => 'Aguardando',
        'blocked_heading' => 'Precisa da sua atenção',
        'recent_heading' => 'Concluído recentemente',
        'empty' => 'Nada em execução. Tudo o que a IA podia fazer está em dia.',
        'recent_empty' => 'Nenhum trabalho da IA foi concluído ainda.',
    ],

    'role' => [
        'criteria_analyst' => 'Analista de critérios',
        'candidate_reviewer' => 'Revisor de candidatos',
        'talent_matcher' => 'Buscador de talentos',
        'candidate_importer' => 'Importador de candidatos',
    ],

    'work' => [
        'criteria_preparing' => 'Preparando os critérios de :job',
        'criteria_waiting_allowance' => 'Os critérios de :job estão em espera',
        'criteria_failed' => 'Não foi possível preparar os critérios de :job',

        'evaluating_evaluating' => 'Avaliando :candidate para :job',
        'evaluating_waiting_criteria' => ':candidate está aguardando avaliação para :job',
        'evaluating_waiting_allowance' => 'A avaliação de :candidate para :job está em espera',
        'evaluating_failed' => 'Não foi possível concluir a avaliação de :candidate para :job',
        'evaluating_many' => '{1} 1 candidato sendo avaliado para :job|[2,*] :count candidatos sendo avaliados para :job',

        'sourcing_reviewing' => 'Revisando o banco de talentos para :job',
        'sourcing_waiting_allowance' => 'A revisão do banco de talentos de :job está em espera',
        'sourcing_failed' => 'Não foi possível concluir a revisão do banco de talentos de :job',
        'sourcing_done' => 'Banco de talentos revisado para :job',

        'import_importing' => 'Importando candidatos de :source',
        'import_paused' => 'A importação de candidatos de :source está pausada',
        'import_failed' => 'Não foi possível concluir a importação de candidatos de :source',
        'import_done' => 'Candidatos importados de :source',
    ],

    'done' => [
        'evaluated' => ':candidate avaliado para :job',
        'evaluated_many' => '{1} 1 candidato avaliado para :job|[2,*] :count candidatos avaliados para :job',
        'criteria' => 'Critérios preparados para :job',
    ],

    'reason' => [
        'job_criteria' => 'Aguardando os critérios da vaga',
        'ai_allowance' => 'Aguardando saldo de IA',
        'criteria_failed' => 'Não foi possível preparar os critérios da vaga',
        'evaluation_failed' => 'Não foi possível concluir a avaliação do candidato',
        'sourcing_failed' => 'Não foi possível concluir a revisão do banco de talentos',
        'import_paused' => 'Aguardando você retomar a importação',
        'import_failed' => 'Não foi possível concluir a importação de candidatos',
    ],

    'fallback' => [
        'candidate' => 'um candidato',
        'job' => 'uma vaga',
    ],

    'provenance' => [
        'generated' => 'Gerado por IA',
        'assisted' => 'Assistido por IA',
        'tooltip' => 'Esta seção foi produzida pela IA do RecruiterLabs. As decisões de contratação continuam sendo suas.',
    ],
];
