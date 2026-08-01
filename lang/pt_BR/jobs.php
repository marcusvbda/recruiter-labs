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
        'published' => 'Publicada',
        'campaign_expectation' => 'Expectativa da campanha',
        'campaign_expectation_helper' => 'Um texto livre descrevendo como é o sucesso desta campanha, por exemplo: "Espera-se contratar 4 desenvolvedores sêniores atendendo a pelo menos 80% dos critérios até o fim da campanha". Usado posteriormente pela IA para avaliar se a campanha atingiu seu objetivo.',
    ],
    'sections' => [
        'details' => 'Detalhes da vaga',
        'application' => 'Página de candidatura',
        'campaign' => 'Campanha',
        'criteria' => 'Critérios de avaliação',
    ],
    'application' => [
        'section_description' => 'Configure as informações e os formatos de currículo solicitados aos candidatos na página de candidatura.',
        'accepted_cv_types' => 'Formatos de currículo aceitos',
        'accepted_cv_types_helper' => 'Escolha um ou mais formatos. Use Selecionar todos para aceitar PDF, DOC e DOCX.',
        'questions' => 'Perguntas da candidatura',
        'question' => 'Pergunta',
        'response_type' => 'Tipo do campo de resposta',
        'required' => 'Resposta obrigatória',
        'field_description' => 'Descrição do campo',
        'field_description_helper' => 'Orientação opcional exibida abaixo do campo para o candidato.',
        'add_question' => 'Adicionar pergunta',
        'question_types' => [
            'text' => 'Input de texto',
            'number' => 'Input numérico',
            'textarea' => 'Área de texto',
        ],
        'cv_types' => [
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'docx' => 'DOCX',
        ],
    ],
    'criteria' => [
        'prompt' => 'Instrução do critério',
        'prompt_helper' => 'Descreva o que este critério significa para o agente avaliador (máximo de 150 caracteres).',
        'weight' => 'Peso',
        'add' => 'Adicionar critério',
    ],
    'pipeline' => [
        'view' => 'Pipeline',
    ],
];
