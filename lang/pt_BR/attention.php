<?php

return [
    'heading' => 'Precisa da sua atenção',
    'empty_heading' => 'Tudo sob controle.',
    'empty_description' => 'Nenhum item de recrutamento precisa da sua atenção agora.',
    'hidden' => '{1} Mais 1 item não está listado aqui.|[2,*] Mais :count itens não estão listados aqui.',
    'job_heading' => 'Precisa de atenção neste processo',
    'severities' => [
        'critical' => 'Com falha',
        'warning' => 'Aguardando você',
        'info' => 'Vale saber',
    ],
    'days' => '{0} menos de um dia|{1} 1 dia|[2,*] :count dias',
    'items' => [
        'interview_declined' => [
            'title' => ':candidate recusou a entrevista',
            'explanation' => 'O convite para :date foi recusado na agenda.',
            'action' => 'Reagendar entrevista',
        ],
        'interview_calendar_failed' => [
            'title' => 'A entrevista com :candidate não está na agenda',
            'explanation' => 'A entrevista de :date existe aqui, mas o evento de agenda não pôde ser criado, então o candidato pode não ter convite algum.',
            'action' => 'Abrir entrevistas',
        ],
        'calendar_reconnect_required' => [
            'title' => 'Sua conexão de agenda expirou',
            'explanation' => '{1} 1 entrevista sua não pode mais ser sincronizada até a agenda ser reconectada.|[2,*] :count entrevistas suas não podem mais ser sincronizadas até a agenda ser reconectada.',
            'action' => 'Reconectar agenda',
        ],
        'evaluation_failed' => [
            'title' => 'A avaliação de :candidate falhou',
            'explanation' => 'A avaliação do candidato terminou em erro, então não há aderência nem evidências para ler. A candidatura em si não foi alterada.',
            'action' => 'Abrir avaliação',
        ],
        'evaluation_blocked_by_quota' => [
            'title' => 'Avaliações aguardando limite de IA',
            'explanation' => '{1} 1 candidatura está na fila e não pode ser avaliada até o workspace ter limite disponível novamente.|[2,*] :count candidaturas estão na fila e não podem ser avaliadas até o workspace ter limite disponível novamente.',
            'action' => 'Revisar uso de IA',
        ],
        'stage_overdue' => [
            'title' => ':candidate está esperando em :stage',
            'explanation' => 'Esperando :waited em :stage — esta etapa está configurada para alertar após :threshold.',
            'action' => 'Abrir candidatura',
        ],
        'decision_pending' => [
            'title' => ':candidate está esperando uma decisão',
            'explanation' => 'Chegou em :stage há :waited e não tem próxima entrevista agendada.',
            'action' => 'Abrir candidatura',
        ],
        'job_stalled' => [
            'title' => ':job tem candidaturas, mas nenhum avanço',
            'explanation' => '{1} 1 candidato se inscreveu e nenhum chegou a uma entrevista, etapa final ou contratação.|[2,*] :count candidatos se inscreveram e nenhum chegou a uma entrevista, etapa final ou contratação.',
            'action' => 'Abrir pipeline',
        ],
        'job_ending_without_finalists' => [
            'title' => ':job termina em breve sem ninguém perto da contratação',
            'explanation' => 'A campanha termina em :date e não há finalistas nem contratações.',
            'action' => 'Revisar vaga',
        ],
        'hiring_target_reached' => [
            'title' => ':job atingiu sua meta de contratação',
            'explanation' => ':hired de :target posições preenchidas. Decida se quer pausar candidaturas, despublicar a vaga ou continuar recrutando.',
            'action' => 'Revisar vaga',
        ],
        'hiring_target_near' => [
            'title' => ':job está a uma contratação da meta',
            'explanation' => ':hired de :target posições preenchidas.',
            'action' => 'Abrir pipeline',
        ],
    ],
];
