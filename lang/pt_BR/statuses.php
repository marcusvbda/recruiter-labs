<?php

return [
    'label' => 'Etapa',
    'plural_label' => 'Etapas',
    'navigation_label' => 'Etapas',
    'relation_description' => 'Arraste para reordenar. A primeira etapa é onde novas candidaturas entram.',
    'sections' => [
        'details' => 'Detalhes da etapa',
        'communication' => 'Comunicação',
        'communication_description' => 'Opcionalmente envie um e-mail ao candidato quando ele chegar nesta etapa.',
    ],
    'fields' => [
        'name' => 'Nome',
        'color' => 'Cor',
        'is_hired' => 'Etapa de contratação',
        'is_hired_helper' => 'Candidaturas nesta etapa contam como contratações realizadas.',
        'sends_email' => 'Enviar um e-mail quando o candidato entrar nesta etapa',
        'sends_email_helper' => 'Enviado automaticamente pelo provedor de e-mail configurado.',
        'email_subject' => 'Assunto',
        'email_subject_placeholder' => 'Candidatura recebida - {{ job.title }}',
        'email_body' => 'Mensagem',
        'email_body_helper' => 'Use as variáveis abaixo para personalizar a mensagem.',
        'applications_count' => 'Candidatos',
    ],
    'badges' => [
        'email_on' => 'Com e-mail',
        'email_off' => 'Sem e-mail',
    ],
    'variables' => [
        'title' => 'Variáveis disponíveis',
        'description' => 'Clique em uma variável para copiá-la e cole no assunto ou na mensagem.',
        'copied' => 'Copiado',
        'groups' => [
            'candidate' => 'Candidato',
            'job' => 'Vaga',
            'company' => 'Empresa',
            'application' => 'Candidatura',
        ],
    ],
    'actions' => [
        'create' => 'Adicionar etapa',
    ],
    'notifications' => [
        'has_applications_title' => 'Esta etapa não pode ser excluída',
    ],
];
