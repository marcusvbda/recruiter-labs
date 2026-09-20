<?php

return [
    'label' => 'Plantilla de correo',
    'plural_label' => 'Plantillas de correo',
    'navigation_label' => 'Plantillas de correo',
    'list_subheading' => 'Mensajes reutilizables para candidatos. Escribe uno una vez y úsalo al enviar un mensaje o cuando un candidato entre en una etapa.',
    'create_subheading' => 'Ponle nombre a la plantilla, escribe el asunto y el mensaje, y usa las variables para personalizarlo.',
    'edit_subheading' => 'Los cambios se aplican la próxima vez que se use esta plantilla. Los mensajes ya enviados nunca se modifican.',
    'sections' => [
        'details' => 'Detalles de la plantilla',
        'details_description' => 'Cómo se identifica esta plantilla cuando alguien la elige.',
        'content' => 'Mensaje',
        'content_description' => 'El asunto y el cuerpo que reciben los candidatos, con las variables resueltas al enviar.',
        'preview' => 'Vista previa',
        'preview_description' => 'Cómo se lee el mensaje con valores de ejemplo en lugar de las variables.',
    ],
    'fields' => [
        'name' => 'Nombre',
        'name_helper' => 'Solo lo ven los reclutadores. Los candidatos nunca ven el nombre de la plantilla.',
        'is_available' => 'Disponible para usar',
        'is_available_helper' => 'Desactívalo para conservar la plantilla sin ofrecerla al enviar mensajes.',
        'subject' => 'Asunto',
        'subject_placeholder' => 'Tu candidatura para {{ job.title }}',
        'body' => 'Mensaje',
        'body_helper' => 'Usa las variables de abajo para personalizar el mensaje.',
        'updated_at' => 'Última actualización',
    ],
    'preview' => [
        'subject' => 'Asunto',
        'body' => 'Mensaje',
        'empty' => 'Aún no hay nada',
    ],
    'notifications' => [
        'in_use_title' => 'Esta plantilla no se puede eliminar',
        'in_use_body' => 'Es el correo que se envía cuando un candidato entra en: :stages. Primero elige otra plantilla en esas etapas o desactiva su correo de etapa.',
    ],
    'empty_state' => [
        'heading' => 'Aún no hay plantillas de correo',
        'description' => 'Crea una para reutilizar el mismo mensaje con distintos candidatos en lugar de reescribirlo cada vez.',
    ],
];
