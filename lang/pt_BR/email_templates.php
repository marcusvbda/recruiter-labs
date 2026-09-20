<?php

return [
    'label' => 'Modelo de e-mail',
    'plural_label' => 'Modelos de e-mail',
    'navigation_label' => 'Modelos de e-mail',
    'list_subheading' => 'Mensagens reutilizáveis para candidatos. Escreva uma vez e use ao enviar uma mensagem ou quando um candidato entrar em uma etapa.',
    'create_subheading' => 'Dê um nome ao modelo, escreva o assunto e a mensagem e use as variáveis para personalizá-lo.',
    'edit_subheading' => 'As alterações valem na próxima vez que este modelo for usado. Mensagens já enviadas nunca são alteradas.',
    'sections' => [
        'details' => 'Detalhes do modelo',
        'details_description' => 'Como este modelo é identificado quando alguém o escolhe.',
        'content' => 'Mensagem',
        'content_description' => 'O assunto e o corpo que os candidatos recebem, com as variáveis resolvidas no envio.',
        'preview' => 'Pré-visualização',
        'preview_description' => 'Como a mensagem fica com valores de exemplo no lugar das variáveis.',
    ],
    'fields' => [
        'name' => 'Nome',
        'name_helper' => 'Somente recrutadores veem isto. Os candidatos nunca veem o nome do modelo.',
        'is_available' => 'Disponível para uso',
        'is_available_helper' => 'Desative para manter o modelo sem oferecê-lo ao enviar mensagens.',
        'subject' => 'Assunto',
        'subject_placeholder' => 'Sua candidatura para {{ job.title }}',
        'body' => 'Mensagem',
        'body_helper' => 'Use as variáveis abaixo para personalizar a mensagem.',
        'updated_at' => 'Última atualização',
    ],
    'preview' => [
        'subject' => 'Assunto',
        'body' => 'Mensagem',
        'empty' => 'Nada ainda',
    ],
    'notifications' => [
        'in_use_title' => 'Este modelo não pode ser excluído',
        'in_use_body' => 'É o e-mail enviado quando um candidato entra em: :stages. Primeiro escolha outro modelo nessas etapas ou desative o e-mail de etapa.',
    ],
    'empty_state' => [
        'heading' => 'Ainda não há modelos de e-mail',
        'description' => 'Crie um para reutilizar a mesma mensagem com vários candidatos em vez de reescrevê-la toda vez.',
    ],
];
