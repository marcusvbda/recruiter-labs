<?php

return [
    'meta' => [
        'title' => 'Convite para workspace',
        'description' => 'Participe de um workspace do Recruiter Labs para o qual você foi convidado.',
    ],

    'details' => [
        'workspace' => 'Workspace',
        'invited_by' => 'Convidado por',
        'invited_email' => 'E-mail convidado',
        'expires_at' => 'Válido até',
        'signed_in_as' => 'Conectado como',
        'unknown_inviter' => 'Um administrador do workspace',
    ],

    'states' => [
        'invalid' => [
            'title' => 'Este link de convite não é válido',
            'description' => 'O link pode ter sido digitado incorretamente ou não está mais em uso. Peça um novo convite a quem convidou você.',
        ],
        'expired' => [
            'title' => 'Este convite expirou',
            'description' => 'Os convites para o :workspace têm prazo de validade e este já passou da data. Peça a quem convidou você para enviá-lo novamente.',
        ],
        'revoked' => [
            'title' => 'Este convite foi cancelado',
            'description' => 'O convite para o :workspace foi cancelado pelo workspace e não pode mais ser usado. Peça um novo convite se ainda precisar de acesso.',
        ],
        'accepted' => [
            'title' => 'Este convite já foi utilizado',
            'description' => 'O convite para o :workspace já foi aceito e não pode ser usado de novo. Peça um novo convite ao workspace se precisar de acesso.',
        ],
        'already_member' => [
            'title' => 'Você já tem acesso',
            'description' => 'Você já faz parte do :workspace, então não há nada a aceitar. Vá direto para o workspace.',
        ],
        'access_disabled' => [
            'title' => 'Seu acesso está desativado',
            'description' => 'Você continua fazendo parte do :workspace, mas seu acesso está desativado no momento, então este convite não muda nada. Peça ao proprietário do workspace para reativar seu acesso.',
        ],
        'guest' => [
            'title' => 'Você foi convidado para o :workspace',
            'description' => 'Entre com o e-mail convidado para aceitar ou crie sua conta, caso ainda não tenha uma.',
        ],
        'email_mismatch' => [
            'title' => 'Este convite é para outra conta',
            'description' => 'Você está conectado como :email, que não é a conta convidada pelo :workspace. Saia e entre novamente com o e-mail convidado para aceitar.',
        ],
        'email_unverified' => [
            'title' => 'Confirme seu e-mail primeiro',
            'description' => 'Antes de entrar no :workspace, você precisa confirmar o e-mail :email. Depois de confirmar, volte a esta página para aceitar.',
        ],
        'acceptable' => [
            'title' => 'Entrar no :workspace',
            'description' => 'Ao aceitar, você entra no workspace como Membro. Você continua com acesso a qualquer outro workspace do qual já participa.',
        ],
    ],

    'actions' => [
        'accept' => 'Aceitar convite',
        'login' => 'Entrar',
        'register' => 'Criar conta',
        'verify' => 'Confirmar e-mail',
        'workspace' => 'Ir para o workspace',
    ],

    'flash' => [
        'accepted' => 'Agora você é membro do :workspace.',
    ],

    'register' => [
        'email_locked' => 'Este convite está vinculado a este e-mail, então sua conta é criada com ele.',
    ],
];
