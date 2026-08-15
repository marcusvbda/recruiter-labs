<?php

return [
    'label' => 'Etapa',
    'plural_label' => 'Etapas',
    'navigation_label' => 'Etapas',
    'relation_description' => 'Arrastra para reordenar. La primera etapa es donde entran las nuevas postulaciones.',
    'sections' => [
        'details' => 'Detalles de la etapa',
        'communication' => 'Comunicación',
        'communication_description' => 'Opcionalmente envía un correo al candidato cuando llegue a esta etapa.',
    ],
    'fields' => [
        'name' => 'Nombre',
        'color' => 'Color',
        'is_hired' => 'Etapa de contratación',
        'is_hired_helper' => 'Las postulaciones en esta etapa cuentan como contrataciones realizadas.',
        'sends_email' => 'Enviar un correo cuando el candidato entre en esta etapa',
        'sends_email_helper' => 'Se envía automáticamente por el proveedor de correo configurado.',
        'email_subject' => 'Asunto',
        'email_subject_placeholder' => 'Postulación recibida - {{ job.title }}',
        'email_body' => 'Mensaje',
        'email_body_helper' => 'Usa las variables de abajo para personalizar el mensaje.',
        'applications_count' => 'Candidatos',
    ],
    'badges' => [
        'email_on' => 'Con correo',
        'email_off' => 'Sin correo',
    ],
    'variables' => [
        'title' => 'Variables disponibles',
        'description' => 'Haz clic en una variable para copiarla y pégala en el asunto o el mensaje.',
        'copied' => 'Copiado',
        'groups' => [
            'candidate' => 'Candidato',
            'job' => 'Puesto',
            'company' => 'Empresa',
            'application' => 'Postulación',
        ],
    ],
    'actions' => [
        'create' => 'Añadir etapa',
    ],
    'notifications' => [
        'has_applications_title' => 'Esta etapa no se puede eliminar',
    ],
];
