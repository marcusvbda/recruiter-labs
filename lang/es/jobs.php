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
        'campaign_expectation' => 'Expectativa de la campaña',
        'campaign_expectation_helper' => 'Un texto libre que describe cómo se ve el éxito de esta campaña, por ejemplo: "Se espera contratar a 4 desarrolladores senior que cumplan al menos el 80% de los criterios antes de que finalice la campaña". La IA lo usará luego para evaluar si la campaña cumplió su objetivo.',
    ],
    'sections' => [
        'details' => 'Detalles del empleo',
        'campaign' => 'Campaña',
        'criteria' => 'Criterios de evaluación',
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
