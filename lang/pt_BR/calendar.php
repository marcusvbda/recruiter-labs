<?php

return [
    'navigation_label' => 'Calendário',
    'integration_navigation_label' => 'Integração de agenda',
    'title' => 'Calendário',
    'subtitle' => 'Conecte sua conta de calendário para agendar entrevistas e sincronizar respostas de candidatos.',
    'eyebrow' => 'Integração de calendário',
    'heading' => 'Conecte seu calendário de recrutamento',
    'description' => 'Autorize o RecruiterLabs a criar e gerenciar eventos de entrevista em seu calendário de recrutamento.',
    'status' => [
        'connected' => 'Conectado',
        'reauthorization_required' => 'Reconexão necessária',
        'disconnected' => 'Não conectado',
    ],
    'google' => [
        'reauthorization_description' => 'O :plugin precisa da sua autorização novamente antes de poder ser usado.',
    ],
    'details' => [
        'account_name' => 'Nome da conta',
        'account_email' => 'Conta conectada',
        'connected_at' => 'Conectado em',
    ],
    'actions' => [
        'connect' => 'Conectar',
        'reconnect' => 'Reconectar',
        'disconnect' => 'Desconectar',
    ],
    'disconnect' => [
        'heading' => 'Desconectar :plugin?',
        'description' => 'O RecruiterLabs removerá a autorização armazenada para esta conta.',
        'confirm' => 'Desconectar',
    ],
    'notifications' => [
        'disconnected' => ':plugin desconectado',
    ],
    'event' => [
        'summary' => 'Entrevista: :job',
        'description' => "Candidato: :candidate\nCargo: :job",
    ],
];
