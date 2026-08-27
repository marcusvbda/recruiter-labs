<?php

return [
    'checklist' => [
        'heading' => 'Caminho até sua primeira avaliação',
        'setup_complete' => 'Configuração concluída',
        'progress' => ':done de :total etapas concluídas',
        'optional_heading' => 'Configuração opcional',
        'optional_done' => 'Concluído',
        'optional_not_available' => 'Peça a um proprietário do workspace',
        'steps' => [
            'workspace_created' => [
                'title' => 'Workspace criado',
                'hint' => 'Seu workspace está pronto. Os próximos passos preparam o primeiro processo seletivo dele.',
            ],
            'create_first_job' => [
                'title' => 'Crie sua primeira vaga',
                'hint' => 'Uma vaga diz ao RecruiterLabs o que você está contratando, para que ele possa começar a comparar candidatos com ela.',
                'action' => 'Criar vaga',
            ],
            'confirm_hiring_criteria' => [
                'title' => 'Confirme os critérios de contratação',
                'hint' => 'Com os critérios confirmados, o RecruiterLabs avalia cada candidato de forma consistente com o que você realmente procura.',
                'action' => 'Confirmar critérios',
            ],
            'add_first_application' => [
                'title' => 'Adicione sua primeira candidatura',
                'hint' => 'O RecruiterLabs precisa de uma candidatura real antes de poder mostrar como é uma avaliação.',
                'action' => 'Adicionar candidatura',
            ],
            'evaluate_first_application' => [
                'title' => 'Avalie sua primeira candidatura',
                'hint' => 'Isso transforma seus critérios de contratação e o perfil do candidato em uma avaliação baseada em evidências — o valor central do RecruiterLabs.',
                'action' => 'Abrir avaliação',
            ],
        ],
        'optional' => [
            'invite_teammate' => [
                'title' => 'Convide um colega de equipe',
                'action' => 'Convidar colega',
            ],
            'connect_calendar' => [
                'title' => 'Conecte sua agenda',
                'action' => 'Conectar agenda',
            ],
            'connect_email' => [
                'title' => 'Conecte seu e-mail',
                'action' => 'Conectar e-mail',
            ],
        ],
    ],
    'welcome' => [
        'heading' => 'Bem-vindo ao RecruiterLabs',
        'intro' => 'O RecruiterLabs transforma seus critérios de contratação em avaliações de candidatos baseadas em evidências. Algumas etapas te levam até a primeira.',
        'progress' => ':done de :total etapas concluídas',
        'next_step_label' => 'Próxima etapa',
        'get_started' => 'Começar',
        'continue_later' => 'Continuar depois',
    ],
    'launcher' => [
        'label' => 'Configuração do workspace',
        'progress' => ':done de :total etapas concluídas',
        'view_checklist' => 'Ver checklist',
        'hide' => 'Ocultar',
    ],
];
