<?php

return [
    'label' => 'Status',
    'plural_label' => 'Status',
    'navigation_label' => 'Status',
    'sections' => [
        'details' => 'Detalhes do status',
    ],
    'fields' => [
        'name' => 'Nome',
        'color' => 'Cor',
        'is_hired' => 'Status de contratação',
        'is_hired_helper' => 'Candidaturas neste status contam como contratações realizadas.',
    ],
    'notifications' => [
        'has_applications' => 'Este status possui candidatos e não pode ser excluído.',
    ],
];
