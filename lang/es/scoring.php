<?php

return [
    'navigation_label' => 'Puntuación',
    'title' => 'Puntuación',
    'subtitle' => 'Equilibra cómo se puntúan las candidaturas en este espacio de trabajo.',
    'eyebrow' => 'Configuración de puntuación',
    'heading' => 'Equilibra cómo se puntúan las candidaturas',
    'description' => 'La Evaluación de ajuste y las contribuciones de referencias se combinan para puntuar las postulaciones en este espacio de trabajo.',
    'weights_heading' => 'Pesos actuales',
    'weights_description' => 'Estos pesos se aplican a cada postulación puntuada en este espacio de trabajo.',
    'fields' => [
        'fit_evaluation_weight' => 'Peso de la evaluación de ajuste',
        'referral_weight' => 'Peso de referencia',
    ],
    'update' => [
        'action' => 'Actualizar pesos',
        'heading' => 'Actualizar pesos de puntuación',
        'description' => 'Ambos pesos deben ser números enteros entre 0 y 100 que sumen exactamente 100.',
        'sum_helper' => 'Los pesos de Evaluación de ajuste y referencia deben sumar 100.',
        'save' => 'Guardar pesos',
    ],
    'validation' => [
        'weights_must_sum' => 'Los pesos de Evaluación de ajuste y referencia deben sumar 100.',
    ],
    'notifications' => [
        'updated' => 'Pesos de puntuación actualizados',
    ],
];
