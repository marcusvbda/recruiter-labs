<?php

return [
    'label' => 'Referencia',
    'plural_label' => 'Referencias',
    'navigation_label' => 'Referencias',
    'sections' => [
        'details' => 'Detalles de la referencia',
        'availability' => 'Disponibilidad del enlace',
        'availability_description' => 'Controla cuándo este enlace de referencia puede recibir postulaciones.',
    ],
    'fields' => [
        'job' => 'Empleo',
        'user' => 'Usuario',
        'published' => 'Publicado',
        'published_helper' => 'Solo se puede acceder a los enlaces de referencia publicados.',
        'expires_at' => 'Válido hasta',
        'expires_at_helper' => 'Déjalo en blanco para no establecer una fecha de vencimiento.',
        'max_applications' => 'Postulaciones permitidas',
        'max_applications_helper' => 'El enlace deja de estar disponible al alcanzar este límite.',
        'created_at' => 'Creado el',
    ],
];
