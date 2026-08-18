<?php

return [
    'navigation_label' => 'Pontuação',
    'title' => 'Pontuação',
    'subtitle' => 'Equilibre como as candidaturas são pontuadas neste ambiente.',
    'eyebrow' => 'Configuração de pontuação',
    'heading' => 'Equilibre como as candidaturas são pontuadas',
    'description' => 'A Avaliação de aderência e as contribuições de indicação são combinadas para pontuar candidaturas neste ambiente.',
    'weights_heading' => 'Pesos atuais',
    'weights_description' => 'Estes pesos são aplicados a todas as candidaturas pontuadas neste ambiente.',
    'fields' => [
        'fit_evaluation_weight' => 'Peso da Avaliação de aderência',
        'referral_weight' => 'Peso da indicação',
    ],
    'update' => [
        'action' => 'Atualizar pesos',
        'heading' => 'Atualizar pesos de pontuação',
        'description' => 'Os dois pesos devem ser números inteiros entre 0 e 100 que somem exatamente 100.',
        'sum_helper' => 'Os pesos da Avaliação de aderência e da indicação devem somar 100.',
        'save' => 'Salvar pesos',
    ],
    'validation' => [
        'weights_must_sum' => 'Os pesos da Avaliação de aderência e da indicação devem somar 100.',
    ],
    'notifications' => [
        'updated' => 'Pesos de pontuação atualizados',
    ],
];
