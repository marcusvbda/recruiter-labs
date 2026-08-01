<?php

return [
    'label' => 'Evento de automatización',
    'plural_label' => 'Eventos de automatización',
    'navigation_label' => 'Automatizaciones',
    'event_types' => [
        'application_submitted' => 'Postulación enviada',
        'status_changed' => 'Estado cambiado',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
    ],
    'action_types' => [
        'send_email' => 'Enviar correo',
    ],
    'fields' => [
        'event_type' => 'Evento',
        'action_type' => 'Acción',
        'email_template' => 'Plantilla de correo',
        'automatable' => 'Vinculado a',
        'is_active' => 'Activo',
    ],
    'relation_manager' => [
        'title' => 'Eventos de automatización',
    ],
];
