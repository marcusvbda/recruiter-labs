<?php

return [
    'navigation_label' => 'Equipe',
    'title' => 'Equipe',
    'subtitle' => 'Quem tem acesso a este workspace e quem foi convidado.',
    'fields' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'role' => 'Função',
        'invited_email' => 'E-mail convidado',
        'status' => 'Status',
        'invited_at' => 'Convidado em',
        'expires_at' => 'Expira em',
    ],
    'members' => [
        'heading' => 'Membros ativos',
        'description' => 'Pessoas com acesso a este workspace agora.',
    ],
    'invitations' => [
        'heading' => 'Convites pendentes',
        'description' => 'Convites que ainda não foram aceitos. Um convite não é acesso — só se torna associação quando aceito.',
    ],
    'actions' => [
        'invite' => 'Convidar membro',
        'remove' => 'Remover',
        'resend' => 'Reenviar',
        'revoke' => 'Revogar',
    ],
    'invite' => [
        'heading' => 'Convidar um membro',
        'description' => 'A pessoa receberá um e-mail com um link para entrar neste workspace.',
        'confirm' => 'Enviar convite',
        'use_resend' => 'Use Reenviar no convite já existente em vez disso.',
    ],
    'remove' => [
        'heading' => 'Remover :name deste workspace?',
        'description' => 'Esta pessoa perderá o acesso a este workspace imediatamente. Ela mantém sua conta e qualquer outro workspace ao qual pertença.',
        'confirm' => 'Remover acesso',
    ],
    'revoke' => [
        'heading' => 'Revogar o convite para :email?',
        'description' => 'O link do convite deixará de funcionar. Você pode convidar este endereço novamente a qualquer momento.',
        'confirm' => 'Revogar convite',
    ],
    'notifications' => [
        'invitation_sent' => 'Convite enviado para :email',
        'invitation_resent' => 'Convite reenviado para :email',
        'invitation_revoked' => 'Convite para :email revogado',
        'invitation_email_failed' => 'O convite para :email foi criado, mas o e-mail não pôde ser enviado. Use Reenviar para tentar novamente.',
        'member_removed' => ':name foi removido deste workspace',
    ],
    'invitation_email' => [
        'subject' => 'Você foi convidado para entrar em :workspace no Recruiter Labs',
        'greeting' => 'Olá!',
        'line_1' => ':inviter convidou você para entrar no workspace :workspace no Recruiter Labs.',
        'action' => 'Aceitar convite',
        'expires' => 'Este convite expira em :date.',
        'unknown_inviter' => 'Um proprietário do workspace',
        'line_2' => 'Se você não esperava este convite, pode ignorar este e-mail.',
    ],
    'errors' => [
        'already_member' => ':email já tem acesso a este workspace como :role.',
        'invitation_already_pending' => ':email já tem um convite pendente para este workspace. Reenvie esse convite em vez de criar um novo.',
        'invitation_revoked_cannot_resend' => 'Este convite foi revogado. Convide esta pessoa novamente para conceder acesso.',
        'invitation_already_accepted' => ':email já aceitou este convite e é um membro ativo deste workspace.',
        'invitation_expired_cannot_accept' => 'Este convite expirou. Peça a um proprietário do workspace para enviar um novo.',
        'invitation_revoked_cannot_accept' => 'Este convite não é mais válido. Peça a um proprietário do workspace para convidar você novamente.',
        'invitation_already_used' => 'Este convite já foi utilizado e não pode ser aceito novamente.',
        'invitation_email_mismatch' => 'Este convite foi enviado para outro endereço de e-mail. Você está conectado como :email. Entre com o endereço convidado para aceitá-lo.',
        'invitation_email_not_verified' => 'Confirme :email antes de entrar neste workspace. Assim que seu e-mail for verificado, abra este convite novamente.',
        'owner_cannot_be_removed' => 'O proprietário do workspace não pode ser removido. Todo workspace precisa manter um proprietário.',
    ],
];
