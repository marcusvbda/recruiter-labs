<?php

return [
    'navigation_label' => 'Pontuação',
    'title' => 'Pontuação',
    'subtitle' => 'Equilibre como as candidaturas são pontuadas neste ambiente.',
    'eyebrow' => 'Configuração de pontuação',
    'heading' => 'Equilibre como as candidaturas são pontuadas',
    'description' => 'Controle o peso da análise de IA e do bônus de indicação ao classificar as candidaturas.',
    'weights_heading' => 'Pesos atuais',
    'weights_description' => 'Esses pesos são aplicados a todas as candidaturas pontuadas neste ambiente.',
    'fields' => [
        'analysis_weight' => 'Peso da análise',
        'referral_weight' => 'Peso da indicação',
    ],
    'update' => [
        'action' => 'Atualizar pesos',
        'heading' => 'Atualizar pesos de pontuação',
        'description' => 'Os dois pesos devem ser números inteiros entre 0 e 100 que somem exatamente 100.',
        'sum_helper' => 'Os pesos de análise e indicação devem somar 100.',
        'save' => 'Salvar pesos',
    ],
    'notifications' => [
        'updated' => 'Pesos de pontuação atualizados',
    ],
];
