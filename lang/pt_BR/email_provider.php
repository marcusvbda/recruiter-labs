<?php

return [
    'navigation_label' => 'Provedor de E-mail',
    'title' => 'Provedor de E-mail',
    'subtitle' => 'Configure os provedores de e-mail usados para o envio de recrutamento.',
    'default_badge' => 'Padrão',
    'fields' => [
        'provider' => 'Provedor',
        'api_key' => 'Chave de API',
        'from_address' => 'Endereço de e-mail do remetente',
    ],
    'providers' => [
        'resend' => 'Resend',
        'gmail' => 'Gmail',
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
    'gmail' => [
        'reauthorization_description' => 'O :plugin precisa da sua autorização novamente antes de enviar e-mails de recrutamento.',
        'default_uses_another_connection' => 'O :plugin é o provedor padrão do ambiente por meio da conta conectada de outro recrutador.',
        'status' => [
            'connected' => 'Conectado',
            'reauthorization_required' => 'Reconexão necessária',
            'disconnected' => 'Não conectado',
        ],
        'details' => [
            'account_name' => 'Nome da conta',
            'account_email' => 'Conta conectada',
            'connected_at' => 'Conectado em',
        ],
        'actions' => [
            'connect' => 'Conectar :plugin',
            'reconnect' => 'Reconectar',
            'disconnect' => 'Desconectar',
        ],
        'disconnect' => [
            'heading' => 'Desconectar o :plugin?',
            'description' => 'O RecruiterLabs removerá sua autorização armazenada. Se esta conexão for a padrão do ambiente, deixará de ser usada para e-mails de recrutamento.',
            'confirm' => 'Desconectar',
        ],
        'notifications' => [
            'disconnected' => ':plugin desconectado',
        ],
    ],
];
