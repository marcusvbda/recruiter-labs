<?php

return [
    'navigation_label' => 'Puntuación',
    'title' => 'Puntuación',
    'subtitle' => 'Equilibra cómo se puntúan las candidaturas en este espacio de trabajo.',
    'eyebrow' => 'Configuración de puntuación',
    'heading' => 'Equilibra cómo se puntúan las candidaturas',
    'description' => 'El análisis de compatibilidad de la IA es la nota base. Una referencia suma un bonus sobre ella, limitado a 100.',
    'weights_heading' => 'Bonus actual',
    'weights_description' => 'Se aplica a toda postulación referida puntuada en este espacio de trabajo.',
    'fields' => [
        'referral_bonus' => 'Bonus de referencia',
    ],
    'update' => [
        'action' => 'Actualizar bonus',
        'heading' => 'Actualizar bonus de referencia',
        'description' => 'Un número entero entre 0 y 100. Un bonus del 40% convierte una nota de IA de 80 en 100.',
        'bonus_helper' => 'Se suma sobre la nota de la IA para candidatos referidos. El resultado nunca supera 100.',
        'save' => 'Guardar bonus',
    ],
    'notifications' => [
        'updated' => 'Bonus de referencia actualizado',
    ],
];
