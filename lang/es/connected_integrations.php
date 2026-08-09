<?php

return [
    'plugins' => [
        'google_calendar' => [
            'label' => 'Google Calendar',
            'description' => 'Conecta una cuenta de Google para programar calendarios.',
            'category' => 'Calendarios',
        ],
        'gmail' => [
            'label' => 'Gmail',
            'description' => 'Envía correos de contratación mediante una cuenta de Gmail conectada.',
            'category' => 'Correo electrónico',
        ],
    ],
    'notifications' => [
        'cancelled' => 'La autorización de :plugin fue cancelada o denegada.',
        'connect_failed' => 'No se pudo conectar :plugin. Inténtalo de nuevo.',
        'connected' => ':plugin se conectó correctamente.',
        'disconnected' => ':plugin se desconectó correctamente.',
    ],
];
