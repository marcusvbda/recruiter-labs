<?php

return [
    'navigation_label' => 'Calendario',
    'integration_navigation_label' => 'Integración de agenda',
    'title' => 'Calendario',
    'subtitle' => 'Conecta tu cuenta de calendario para programar entrevistas y sincronizar respuestas de candidatos.',
    'eyebrow' => 'Integración de calendario',
    'heading' => 'Conecta tu calendario de reclutamiento',
    'description' => 'Autoriza a RecruiterLabs a crear y gestionar eventos de entrevista en tu calendario de reclutamiento.',
    'status' => [
        'connected' => 'Conectado',
        'reauthorization_required' => 'Es necesario reconectar',
        'disconnected' => 'No conectado',
    ],
    'google' => [
        'reauthorization_description' => ':plugin necesita nuevamente tu autorización para poder utilizarse.',
    ],
    'details' => [
        'account_name' => 'Nombre de la cuenta',
        'account_email' => 'Cuenta conectada',
        'connected_at' => 'Fecha de conexión',
    ],
    'actions' => [
        'connect' => 'Conectar',
        'reconnect' => 'Reconectar',
        'disconnect' => 'Desconectar',
    ],
    'disconnect' => [
        'heading' => '¿Desconectar :plugin?',
        'description' => 'RecruiterLabs eliminará la autorización almacenada para esta cuenta.',
        'confirm' => 'Desconectar',
    ],
    'notifications' => [
        'disconnected' => ':plugin desconectado',
    ],
    'event' => [
        'summary' => 'Entrevista: :job',
        'description' => "Candidato: :candidate\nPuesto: :job",
    ],
];
