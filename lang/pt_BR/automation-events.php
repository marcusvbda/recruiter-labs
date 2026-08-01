<?php

return [
    'label' => 'Evento de automação',
    'plural_label' => 'Eventos de automação',
    'navigation_label' => 'Automações',
    'event_types' => [
        'application_submitted' => 'Candidatura enviada',
        'status_changed' => 'Status alterado',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
    ],
    'action_types' => [
        'send_email' => 'Enviar e-mail',
    ],
    'fields' => [
        'event_type' => 'Evento',
        'action_type' => 'Ação',
        'email_template' => 'Modelo de e-mail',
        'automatable' => 'Vinculado a',
        'is_active' => 'Ativo',
    ],
    'relation_manager' => [
        'title' => 'Eventos de automação',
    ],
];
