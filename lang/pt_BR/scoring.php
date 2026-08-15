<?php

return [
    'navigation_label' => 'Pontuação',
    'title' => 'Pontuação',
    'subtitle' => 'Equilibre como as candidaturas são pontuadas neste ambiente.',
    'eyebrow' => 'Configuração de pontuação',
    'heading' => 'Equilibre como as candidaturas são pontuadas',
    'description' => 'A análise de compatibilidade da IA é a nota base. Uma indicação soma um bônus sobre ela, limitado a 100.',
    'weights_heading' => 'Bônus atual',
    'weights_description' => 'Aplicado a toda candidatura indicada pontuada neste workspace.',
    'fields' => [
        'referral_bonus' => 'Bônus de indicação',
    ],
    'update' => [
        'action' => 'Atualizar bônus',
        'heading' => 'Atualizar bônus de indicação',
        'description' => 'Um número inteiro entre 0 e 100. Um bônus de 40% transforma uma nota de IA 80 em 100.',
        'bonus_helper' => 'Somado sobre a nota da IA para candidatos indicados. O resultado nunca passa de 100.',
        'save' => 'Salvar bônus',
    ],
    'notifications' => [
        'updated' => 'Bônus de indicação atualizado',
    ],
];
