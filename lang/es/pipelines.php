<?php

return [
    'label' => 'Pipeline',
    'plural_label' => 'Pipelines',
    'navigation_label' => 'Pipelines',
    'list_subheading' => 'Un pipeline es un proceso de selección: las etapas por las que pasa un candidato y lo que cada etapa le comunica.',
    'create_subheading' => 'Dale un nombre al proceso. Después podrás renombrar, reordenar y eliminar etapas.',
    'edit_subheading' => 'Renombra el proceso, elige si es el predeterminado y gestiona sus etapas abajo.',
    'empty_flow' => 'Aún no hay etapas',
    'sections' => [
        'details' => 'Detalles del pipeline',
        'details_description' => 'Cómo se identifica este proceso de selección en los puestos.',
    ],
    'fields' => [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'description_helper' => 'Opcional. Una nota breve sobre cuándo usar este proceso.',
        'is_default' => 'Pipeline predeterminado',
        'is_default_helper' => 'Se preselecciona al crear un puesto. Solo un pipeline puede ser el predeterminado.',
        'flow' => 'Etapas',
        'jobs_count' => 'Puestos',
    ],
    'badges' => [
        'default' => 'Predeterminado',
        'secondary' => 'Disponible',
    ],
    'actions' => [
        'duplicate' => 'Duplicar',
        'duplicate_description' => 'Crea una copia de este pipeline con las mismas etapas, colores y correos de etapa. Los puestos y candidatos no se copian, y la copia no se vuelve la predeterminada.',
        'set_default' => 'Definir como predeterminado',
    ],
    'notifications' => [
        'duplicated' => 'Pipeline duplicado como ":name".',
        'default_updated' => 'Pipeline predeterminado actualizado.',
        'pipeline_in_use_title' => 'Este pipeline no se puede eliminar',
    ],
    'default' => [
        'name' => 'Selección Estándar',
        'description' => 'Proceso de selección predeterminado creado junto con la empresa.',
    ],
    'duplicate' => [
        'name' => ':name - Copia',
    ],
    'variables' => [
        'samples' => [
            'candidate_name' => 'Alex Candidato',
            'job_title' => 'Ingeniería Senior',
            'company_name' => 'Tu Empresa',
            'application_status' => 'Preselección',
        ],
    ],
    'errors' => [
        'pipeline_in_use' => 'Este pipeline lo usan :count puesto(s), por lo que no se puede eliminar. Mueve esos puestos a otro pipeline primero.',
        'status_in_use' => 'Esta etapa tiene :count candidato(s), por lo que no se puede eliminar. Muévelos a otra etapa primero.',
        'pipeline_locked' => 'Este puesto ya tiene postulaciones, por lo que su pipeline ya no puede cambiarse.',
        'cross_tenant_status' => 'Esa etapa pertenece a otra empresa.',
        'cross_pipeline_status' => 'Esa etapa pertenece a un pipeline distinto al de este puesto.',
        'missing_initial_status' => 'Este pipeline aún no tiene etapas, por lo que las postulaciones no pueden entrar en él.',
    ],
];
