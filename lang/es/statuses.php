<?php

return [
    'label' => 'Estado',
    'plural_label' => 'Estados',
    'navigation_label' => 'Estados',
    'sections' => [
        'details' => 'Detalles del estado',
    ],
    'fields' => [
        'name' => 'Nombre',
        'color' => 'Color',
        'is_hired' => 'Estado de contratación',
        'is_hired_helper' => 'Las postulaciones en este estado cuentan como contrataciones realizadas.',
    ],
    'notifications' => [
        'has_applications' => 'Este estado tiene candidatos y no se puede eliminar.',
    ],
];
