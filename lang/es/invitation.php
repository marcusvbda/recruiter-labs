<?php

return [
    'meta' => [
        'title' => 'Invitación al workspace',
        'description' => 'Únete al workspace de Recruiter Labs al que te invitaron.',
    ],

    'details' => [
        'workspace' => 'Workspace',
        'invited_by' => 'Invitado por',
        'invited_email' => 'Correo invitado',
        'expires_at' => 'Válido hasta',
        'signed_in_as' => 'Sesión iniciada como',
        'unknown_inviter' => 'Un administrador del workspace',
    ],

    'states' => [
        'invalid' => [
            'title' => 'Este enlace de invitación no es válido',
            'description' => 'Es posible que el enlace se haya escrito mal o que ya no esté en uso. Pide una nueva invitación a quien te invitó.',
        ],
        'expired' => [
            'title' => 'Esta invitación ha caducado',
            'description' => 'Las invitaciones a :workspace tienen una validez limitada y esta ya venció. Pide a quien te invitó que la envíe de nuevo.',
        ],
        'revoked' => [
            'title' => 'Esta invitación fue cancelada',
            'description' => 'El workspace :workspace retiró la invitación, así que ya no se puede usar. Pide que te inviten otra vez si aún necesitas acceso.',
        ],
        'accepted' => [
            'title' => 'Esta invitación ya se utilizó',
            'description' => 'La invitación a :workspace ya fue aceptada y no puede volver a usarse. Pide una nueva invitación si necesitas acceso.',
        ],
        'already_member' => [
            'title' => 'Ya tienes acceso',
            'description' => 'Ya formas parte de :workspace, así que no queda nada por aceptar. Entra directamente al workspace.',
        ],
        'guest' => [
            'title' => 'Te invitaron a :workspace',
            'description' => 'Inicia sesión con el correo invitado para aceptar o crea tu cuenta si todavía no tienes una.',
        ],
        'email_mismatch' => [
            'title' => 'Esta invitación es para otra cuenta',
            'description' => 'Tu sesión está iniciada como :email, que no es la cuenta que invitó :workspace. Cierra sesión e inicia sesión con el correo invitado para aceptar.',
        ],
        'email_unverified' => [
            'title' => 'Confirma tu correo primero',
            'description' => 'Antes de entrar a :workspace debes confirmar el correo :email. Cuando lo confirmes, vuelve a esta página para aceptar.',
        ],
        'acceptable' => [
            'title' => 'Únete a :workspace',
            'description' => 'Al aceptar te sumas al workspace como Miembro. Conservas el acceso a cualquier otro workspace al que ya pertenezcas.',
        ],
    ],

    'actions' => [
        'accept' => 'Aceptar invitación',
        'login' => 'Iniciar sesión',
        'register' => 'Crear cuenta',
        'verify' => 'Confirmar correo',
        'workspace' => 'Ir al workspace',
    ],

    'flash' => [
        'accepted' => 'Ahora eres miembro de :workspace.',
    ],

    'register' => [
        'email_locked' => 'Esta invitación está vinculada a este correo, por lo que tu cuenta se crea con él.',
    ],
];
