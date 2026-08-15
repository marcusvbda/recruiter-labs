<?php

return [
    'label' => 'Pipeline',
    'plural_label' => 'Pipelines',
    'navigation_label' => 'Pipelines',
    'list_subheading' => 'Um pipeline é um processo seletivo: as etapas pelas quais o candidato passa e o que cada etapa comunica a ele.',
    'create_subheading' => 'Dê um nome ao processo. Você pode renomear, reordenar e remover etapas depois.',
    'edit_subheading' => 'Renomeie o processo, defina se ele é o padrão e gerencie suas etapas abaixo.',
    'empty_flow' => 'Nenhuma etapa ainda',
    'sections' => [
        'details' => 'Detalhes do pipeline',
        'details_description' => 'Como este processo seletivo é identificado nas vagas.',
    ],
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'description_helper' => 'Opcional. Uma nota curta sobre quando usar este processo.',
        'is_default' => 'Pipeline padrão',
        'is_default_helper' => 'Pré-selecionado ao criar uma vaga. Apenas um pipeline pode ser o padrão.',
        'flow' => 'Etapas',
        'jobs_count' => 'Vagas',
    ],
    'badges' => [
        'default' => 'Padrão',
        'secondary' => 'Disponível',
    ],
    'actions' => [
        'duplicate' => 'Duplicar',
        'duplicate_description' => 'Cria uma cópia deste pipeline com as mesmas etapas, cores e e-mails de etapa. Vagas e candidatos não são copiados, e a cópia não se torna o padrão.',
        'set_default' => 'Definir como padrão',
    ],
    'notifications' => [
        'duplicated' => 'Pipeline duplicado como ":name".',
        'default_updated' => 'Pipeline padrão atualizado.',
        'pipeline_in_use_title' => 'Este pipeline não pode ser excluído',
    ],
    'default' => [
        'name' => 'Recrutamento Padrão',
        'description' => 'Processo seletivo padrão criado junto com a empresa.',
    ],
    'duplicate' => [
        'name' => ':name - Cópia',
    ],
    'variables' => [
        'samples' => [
            'candidate_name' => 'Alex Candidato',
            'job_title' => 'Pessoa Engenheira Sênior',
            'company_name' => 'Sua Empresa',
            'application_status' => 'Triagem',
        ],
    ],
    'errors' => [
        'pipeline_in_use' => 'Este pipeline é usado por :count vaga(s) e por isso não pode ser excluído. Mova essas vagas para outro pipeline primeiro.',
        'status_in_use' => 'Esta etapa tem :count candidato(s) e por isso não pode ser excluída. Mova essas pessoas para outra etapa primeiro.',
        'pipeline_locked' => 'Esta vaga já possui candidaturas, então seu pipeline não pode mais ser alterado.',
        'cross_tenant_status' => 'Essa etapa pertence a outra empresa.',
        'cross_pipeline_status' => 'Essa etapa pertence a um pipeline diferente do desta vaga.',
        'missing_initial_status' => 'Este pipeline ainda não tem etapas, então candidaturas não podem entrar nele.',
    ],
];
