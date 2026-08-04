<?php

return [
    'label' => 'Indicação',
    'plural_label' => 'Indicações',
    'navigation_label' => 'Indicações',
    'sections' => [
        'details' => 'Detalhes da indicação',
        'availability' => 'Disponibilidade do link',
        'availability_description' => 'Controle quando este link de indicação pode receber candidaturas.',
    ],
    'fields' => [
        'job' => 'Vaga',
        'user' => 'Usuário',
        'published' => 'Publicado',
        'published_helper' => 'Somente links de indicação publicados podem ser acessados.',
        'expires_at' => 'Válido até',
        'expires_at_helper' => 'Deixe em branco para não definir uma data de expiração.',
        'max_applications' => 'Candidaturas permitidas',
        'max_applications_helper' => 'O link fica indisponível quando este limite é atingido.',
        'created_at' => 'Criado em',
    ],
];
