<?php

return [
    'label' => 'Hook de evento',
    'plural_label' => 'Hooks de eventos',
    'navigation_label' => 'Hooks de eventos',
    'sections' => [
        'trigger' => 'Gatilho',
        'action' => 'Ação',
    ],
    'event_types' => [
        'application_submitted' => 'Candidatura enviada',
        'status_changed' => 'Status da candidatura alterado',
    ],
    'action_types' => [
        'send_email' => 'Enviar e-mail',
    ],
    'fields' => [
        'event_type' => 'Evento',
        'action_type' => 'Ação',
        'email_template' => 'Modelo de e-mail',
        'automatable' => 'Vinculado a',
        'status' => 'Status',
        'all_option' => 'Todos(as) :type',
        'is_active' => 'Ativo',
    ],
    'relation_manager' => [
        'title' => 'Hooks de eventos',
    ],
];
