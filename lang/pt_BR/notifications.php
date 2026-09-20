<?php

return [
    'candidate_import' => [
        'completed' => [
            'title' => 'Importação de candidatos concluída',
            'body' => ':source: :imported candidato(s) adicionado(s) ao seu banco, :unresolved linha(s) ainda precisam de uma decisão.',
            'action' => 'Abrir importação',
        ],
        'failed' => [
            'title' => 'A importação de candidatos não pôde ser concluída',
            'body' => ':source parou antes de processar todas as linhas. O que foi importado já está no seu banco.',
            'action' => 'Abrir importação',
        ],
    ],

    'sourcing' => [
        'completed' => [
            'title' => 'Revisão do banco de talentos concluída para :job',
            'body' => ':reviewed perfil(is) foram revisados para esta vaga.',
            'action' => 'Abrir sourcing',
        ],
        'failed' => [
            'title' => 'A revisão do banco de talentos parou em :job',
            'body' => 'A revisão não cobriu todo o seu banco. Você pode executá-la novamente quando quiser.',
            'action' => 'Abrir sourcing',
        ],
        'blocked' => [
            'title' => 'Revisão do banco de talentos pausada em :job',
            'body' => 'Seu limite de IA acabou durante a revisão, então o restante do banco não foi visto.',
            'action' => 'Abrir sourcing',
        ],
    ],

    'communication' => [
        'failed' => [
            'title' => 'O e-mail para :candidate não foi entregue',
            'body' => 'A mensagem não pôde ser enviada. Abra a conversa e envie novamente.',
            'action' => 'Abrir conversa',
        ],
    ],

    'interview' => [
        'declined' => [
            'title' => ':candidate recusou a entrevista',
            'body' => 'O convite foi recusado na agenda. O horário continua reservado aqui.',
            'action' => 'Abrir entrevistas',
        ],
    ],

    'criteria' => [
        'failed' => [
            'title' => 'Não foi possível preparar os critérios de contratação de :job',
            'body' => 'Os candidatos desta vaga não serão avaliados enquanto os critérios não estiverem prontos.',
            'action' => 'Abrir vaga',
        ],
    ],

    'ai_allowance' => [
        'blocked' => [
            'title' => 'Limite de IA esgotado',
            'body' => 'O trabalho automático está em espera até haver limite disponível.',
            'action' => 'Abrir configurações de IA',
        ],
    ],
];
