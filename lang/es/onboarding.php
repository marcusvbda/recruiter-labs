<?php

return [
    'checklist' => [
        'heading' => 'Camino a tu primera evaluación',
        'setup_complete' => 'Configuración completa',
        'progress' => ':done de :total pasos completados',
        'optional_heading' => 'Configuración opcional',
        'optional_done' => 'Hecho',
        'optional_not_available' => 'Pídeselo a un propietario del espacio de trabajo',
        'steps' => [
            'workspace_created' => [
                'title' => 'Espacio de trabajo creado',
                'hint' => 'Tu espacio de trabajo está listo. Los siguientes pasos preparan su primer proceso de contratación.',
            ],
            'create_first_job' => [
                'title' => 'Crea tu primera vacante',
                'hint' => 'Una vacante le indica a RecruiterLabs qué estás buscando, para que pueda empezar a comparar candidatos con ella.',
                'action' => 'Crear vacante',
            ],
            'confirm_hiring_criteria' => [
                'title' => 'Confirma los criterios de contratación',
                'hint' => 'Con los criterios confirmados, RecruiterLabs evalúa a cada candidato de forma consistente con lo que realmente buscas.',
                'action' => 'Confirmar criterios',
            ],
            'add_first_application' => [
                'title' => 'Añade tu primera candidatura',
                'hint' => 'RecruiterLabs necesita una candidatura real antes de poder mostrarte cómo es una evaluación.',
                'action' => 'Añadir candidatura',
            ],
            'evaluate_first_application' => [
                'title' => 'Evalúa tu primera candidatura',
                'hint' => 'Esto convierte tus criterios de contratación y el perfil del candidato en una evaluación respaldada por evidencia — el valor central de RecruiterLabs.',
                'action' => 'Abrir evaluación',
            ],
        ],
        'optional' => [
            'invite_teammate' => [
                'title' => 'Invita a un compañero de equipo',
                'action' => 'Invitar compañero',
            ],
            'connect_calendar' => [
                'title' => 'Conecta tu calendario',
                'action' => 'Conectar calendario',
            ],
            'connect_email' => [
                'title' => 'Conecta tu correo',
                'action' => 'Conectar correo',
            ],
        ],
    ],
    'welcome' => [
        'heading' => 'Bienvenido a RecruiterLabs',
        'intro' => 'RecruiterLabs convierte tus criterios de contratación en evaluaciones de candidatos respaldadas por evidencia. Unos pocos pasos te llevan a la primera.',
        'progress' => ':done de :total pasos completados',
        'next_step_label' => 'Siguiente paso',
        'get_started' => 'Empezar',
        'continue_later' => 'Continuar más tarde',
    ],
    'launcher' => [
        'label' => 'Configuración del espacio de trabajo',
        'progress' => ':done de :total pasos completados',
        'view_checklist' => 'Ver lista de pasos',
        'hide' => 'Ocultar',
    ],
];
