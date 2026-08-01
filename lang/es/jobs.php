<?php

return [
    'label' => 'Empleo',
    'plural_label' => 'Empleos',
    'navigation_label' => 'Empleos',
    'fields' => [
        'name' => 'Nombre',
        'created_at' => 'Creado el',
        'description' => 'Descripción',
        'starts_at' => 'Fecha de inicio',
        'ends_at' => 'Fecha de fin',
        'published' => 'Publicado',
        'campaign_expectation' => 'Expectativa de la campaña',
        'campaign_expectation_helper' => 'Un texto libre que describe cómo se ve el éxito de esta campaña, por ejemplo: "Se espera contratar a 4 desarrolladores senior que cumplan al menos el 80% de los criterios antes de que finalice la campaña". La IA lo usará luego para evaluar si la campaña cumplió su objetivo.',
    ],
    'sections' => [
        'details' => 'Detalles del empleo',
        'application' => 'Página de postulación',
        'campaign' => 'Campaña',
        'criteria' => 'Criterios de evaluación',
    ],
    'application' => [
        'section_description' => 'Configura la información y los formatos de currículum solicitados a los candidatos en la página de postulación.',
        'accepted_cv_types' => 'Formatos de currículum aceptados',
        'accepted_cv_types_helper' => 'Elige uno o más formatos. Usa Seleccionar todos para aceptar PDF, DOC y DOCX.',
        'questions' => 'Preguntas de la postulación',
        'question' => 'Pregunta',
        'response_type' => 'Tipo de campo de respuesta',
        'required' => 'Respuesta obligatoria',
        'field_description' => 'Descripción del campo',
        'field_description_helper' => 'Orientación opcional que se muestra debajo del campo para el candidato.',
        'add_question' => 'Agregar pregunta',
        'question_types' => [
            'text' => 'Campo de texto',
            'number' => 'Campo numérico',
            'textarea' => 'Área de texto',
        ],
        'cv_types' => [
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'docx' => 'DOCX',
        ],
    ],
    'criteria' => [
        'prompt' => 'Instrucción del criterio',
        'prompt_helper' => 'Describe qué significa este criterio para el agente evaluador (máximo 150 caracteres).',
        'weight' => 'Peso',
        'add' => 'Agregar criterio',
    ],
    'pipeline' => [
        'view' => 'Pipeline',
    ],
];
