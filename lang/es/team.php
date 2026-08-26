<?php

return [
    'navigation_label' => 'Equipo',
    'title' => 'Equipo',
    'subtitle' => 'Quién tiene acceso a este espacio de trabajo y quién ha sido invitado.',
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'role' => 'Rol',
        'invited_email' => 'Correo invitado',
        'status' => 'Estado',
        'invited_at' => 'Invitado el',
        'expires_at' => 'Expira el',
    ],
    'members' => [
        'heading' => 'Miembros activos',
        'description' => 'Personas con acceso a este espacio de trabajo en este momento.',
    ],
    'invitations' => [
        'heading' => 'Invitaciones pendientes',
        'description' => 'Invitaciones que aún no han sido aceptadas. Una invitación no es acceso: solo se convierte en membresía cuando se acepta.',
    ],
    'actions' => [
        'invite' => 'Invitar miembro',
        'remove' => 'Eliminar',
        'resend' => 'Reenviar',
        'revoke' => 'Revocar',
    ],
    'invite' => [
        'heading' => 'Invitar a un miembro',
        'description' => 'Recibirán un correo con un enlace para unirse a este espacio de trabajo.',
        'confirm' => 'Enviar invitación',
        'use_resend' => 'Usa Reenviar en su invitación existente en su lugar.',
    ],
    'remove' => [
        'heading' => '¿Eliminar a :name de este espacio de trabajo?',
        'description' => 'Esta persona perderá el acceso a este espacio de trabajo de inmediato. Conserva su cuenta y cualquier otro espacio de trabajo al que pertenezca.',
        'confirm' => 'Eliminar acceso',
    ],
    'revoke' => [
        'heading' => '¿Revocar la invitación a :email?',
        'description' => 'El enlace de invitación dejará de funcionar. Puedes volver a invitar a esta dirección en cualquier momento.',
        'confirm' => 'Revocar invitación',
    ],
    'notifications' => [
        'invitation_sent' => 'Invitación enviada a :email',
        'invitation_resent' => 'Invitación reenviada a :email',
        'invitation_revoked' => 'Invitación a :email revocada',
        'invitation_email_failed' => 'La invitación para :email fue creada, pero el correo no pudo enviarse. Usa Reenviar para intentarlo de nuevo.',
        'member_removed' => ':name fue eliminado de este espacio de trabajo',
    ],
    'invitation_email' => [
        'subject' => 'Te han invitado a unirte a :workspace en Recruiter Labs',
        'greeting' => '¡Hola!',
        'line_1' => ':inviter te invitó a unirte al espacio de trabajo :workspace en Recruiter Labs.',
        'action' => 'Aceptar invitación',
        'expires' => 'Esta invitación expira el :date.',
        'unknown_inviter' => 'Un propietario del espacio de trabajo',
        'line_2' => 'Si no esperabas esta invitación, puedes ignorar este correo.',
    ],
    'errors' => [
        'already_member' => ':email ya tiene acceso a este espacio de trabajo como :role.',
        'invitation_already_pending' => ':email ya tiene una invitación pendiente a este espacio de trabajo. Reenvía esa invitación en lugar de crear una nueva.',
        'invitation_revoked_cannot_resend' => 'Esta invitación fue revocada. Invita de nuevo a esta persona para darle acceso.',
        'invitation_already_accepted' => ':email ya aceptó esta invitación y es un miembro activo de este espacio de trabajo.',
        'invitation_expired_cannot_accept' => 'Esta invitación ha expirado. Pide a un propietario del espacio de trabajo que te envíe una nueva.',
        'invitation_revoked_cannot_accept' => 'Esta invitación ya no es válida. Pide a un propietario del espacio de trabajo que te invite de nuevo.',
        'invitation_already_used' => 'Esta invitación ya fue utilizada y no se puede aceptar otra vez.',
        'invitation_email_mismatch' => 'Esta invitación fue enviada a otra dirección de correo. Has iniciado sesión como :email. Inicia sesión con la dirección invitada para aceptarla.',
        'invitation_email_not_verified' => 'Verifica :email antes de unirte a este espacio de trabajo. Cuando confirmes tu correo, abre esta invitación de nuevo.',
        'owner_cannot_be_removed' => 'El propietario del espacio de trabajo no puede ser eliminado. Todo espacio de trabajo debe conservar un propietario.',
    ],
];
