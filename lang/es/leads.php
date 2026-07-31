<?php

return [
    'label' => 'Lead',
    'plural_label' => 'Leads',
    'navigation_label' => 'Leads',
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'phone' => 'Teléfono',
        'socials' => 'Redes sociales',
        'network' => 'Red',
        'account' => 'Cuenta',
        'created_at' => 'Creado el',
    ],
    'filters' => [
        'created_between' => 'Creado entre',
        'from' => 'Desde',
        'until' => 'Hasta',
        'social_network' => 'Red social',
    ],
    'networks' => [
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'other' => 'Otro',
    ],
];
