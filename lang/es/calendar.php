<?php

return [
    'navigation_label' => 'Calendario',
    'title' => 'Calendario',
    'subtitle' => 'Conecta tu cuenta de calendario para futuras funciones de programación de reclutamiento.',
    'eyebrow' => 'Integración de calendario',
    'heading' => 'Conecta tu calendario de reclutamiento',
    'description' => 'Autoriza a RecruiterLabs a acceder a tu calendario. Esta conexión aún no crea entrevistas ni eventos de calendario.',
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
];
