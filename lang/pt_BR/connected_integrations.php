<?php

return [
    'plugins' => [
        'google_calendar' => [
            'label' => 'Google Calendar',
            'description' => 'Conecte uma conta do Google para agendamento no calendário.',
            'category' => 'Calendários',
        ],
        'gmail' => [
            'label' => 'Gmail',
            'description' => 'Envie e-mails de recrutamento por uma conta do Gmail conectada.',
            'category' => 'E-mail',
        ],
    ],
    'notifications' => [
        'cancelled' => 'A autorização do :plugin foi cancelada ou negada.',
        'connect_failed' => 'Não foi possível conectar o :plugin. Tente novamente.',
        'connected' => ':plugin foi conectado com sucesso.',
        'disconnected' => ':plugin foi desconectado com sucesso.',
    ],
];
