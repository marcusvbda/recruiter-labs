<?php

return [
    'label' => 'Vaga',
    'plural_label' => 'Vagas',
    'navigation_label' => 'Vagas',
    'fields' => [
        'name' => 'Nome',
        'created_at' => 'Criado em',
        'description' => 'Descrição',
        'starts_at' => 'Data de início',
        'ends_at' => 'Data de término',
        'campaign_expectation' => 'Expectativa da campanha',
        'campaign_expectation_helper' => 'Um texto livre descrevendo como é o sucesso desta campanha, por exemplo: "Espera-se contratar 4 desenvolvedores sêniores atendendo a pelo menos 80% dos critérios até o fim da campanha". Usado posteriormente pela IA para avaliar se a campanha atingiu seu objetivo.',
    ],
    'sections' => [
        'campaign' => 'Campanha',
        'criteria' => 'Critérios de avaliação',
    ],
    'criteria' => [
        'criterion' => 'Critério',
        'weight' => 'Peso',
        'add' => 'Adicionar critério',
    ],
    'pipeline' => [
        'view' => 'Pipeline',
    ],
];
