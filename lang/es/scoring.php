<?php

return [
    'navigation_label' => 'Puntuación',
    'title' => 'Puntuación',
    'subtitle' => 'Equilibra cómo se puntúan las candidaturas en este espacio de trabajo.',
    'eyebrow' => 'Configuración de puntuación',
    'heading' => 'Equilibra cómo se puntúan las candidaturas',
    'description' => 'Controla cuánto peso tienen el análisis de IA y el bono de referencia al clasificar las candidaturas.',
    'weights_heading' => 'Pesos actuales',
    'weights_description' => 'Estos pesos se aplican a todas las candidaturas puntuadas en este espacio de trabajo.',
    'fields' => [
        'analysis_weight' => 'Peso del análisis',
        'referral_weight' => 'Peso de la referencia',
    ],
    'update' => [
        'action' => 'Actualizar pesos',
        'heading' => 'Actualizar pesos de puntuación',
        'description' => 'Ambos pesos deben ser números enteros entre 0 y 100 que sumen exactamente 100.',
        'sum_helper' => 'Los pesos de análisis y referencia deben sumar 100.',
        'save' => 'Guardar pesos',
    ],
    'notifications' => [
        'updated' => 'Pesos de puntuación actualizados',
    ],
];
