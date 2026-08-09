<?php

return [
    'navigation_label' => 'Provedor de E-mail',
    'title' => 'Provedor de E-mail',
    'subtitle' => 'Configure os provedores de e-mail usados para o envio de recrutamento.',
    'eyebrow' => 'Configuração do provedor de e-mail',
    'heading' => 'Configure seus provedores de e-mail de recrutamento',
    'description' => 'Isso configura os provedores usados para enviar e-mails de recrutamento aos candidatos. Não afeta os e-mails da própria conta do sistema.',
    'default_badge' => 'Padrão',
    'fields' => [
        'provider' => 'Provedor',
        'api_key' => 'Chave de API',
        'from_address' => 'Endereço de e-mail do remetente',
    ],
    'providers' => [
        'resend' => 'Resend',
    ],
    'status' => [
        'valid' => 'Validada',
        'invalid' => 'Requer atenção',
        'untested' => 'Ainda não testada',
        'not_configured' => 'Não configurada',
        'last_validated' => 'Última validação em :date',
        'never_validated' => 'Nunca validada',
    ],
    'configure' => [
        'heading' => 'Configurar :provider',
        'description' => 'A chave é criptografada e nunca será exibida novamente por completo. O endereço do remetente deve ser verificado pelo provedor.',
        'save' => 'Salvar e validar',
    ],
    'remove' => [
        'heading' => 'Remover a chave do :provider?',
        'description' => 'Os e-mails de recrutamento deixarão de ser enviados por este provedor até que uma nova chave seja configurada. O histórico de uso não será afetado.',
        'confirm' => 'Remover chave',
    ],
    'empty' => [
        'heading' => 'Ainda não configurado',
        'description' => 'Adicione uma chave de API e um endereço de remetente verificado para habilitar os e-mails de recrutamento neste ambiente.',
    ],
    'actions' => [
        'configure' => 'Configurar',
        'replace' => 'Substituir chave',
        'test' => 'Testar conexão',
        'remove' => 'Remover chave',
        'set_default' => 'Definir como padrão',
    ],
    'notifications' => [
        'key_removed' => 'Chave do provedor de e-mail removida',
        'default_updated' => 'Provedor de e-mail padrão atualizado',
    ],
];
