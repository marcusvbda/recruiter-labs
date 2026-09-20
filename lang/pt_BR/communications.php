<?php

return [
    'actions' => [
        'send_message' => 'Enviar mensagem',
        'send' => 'Enviar',
        'discard_draft' => 'Descartar rascunho',
        'do_not_contact' => 'Não contatar',
        'allow_contact' => 'Permitir contato',
        'open_email_provider_settings' => 'Abrir configurações do provedor de e-mail',
        'open_candidate_communications' => 'Abrir comunicação com :candidate',
    ],
    'fields' => ['recipient' => 'Destinatário', 'sender' => 'De', 'subject' => 'Assunto', 'body' => 'Mensagem', 'template' => 'Modelo de e-mail', 'job_context' => 'Vaga'],
    'composer' => [
        'heading' => 'Enviar mensagem para :candidate',
        'description' => 'Se quiser, comece por um modelo, edite a mensagem e envie.',
        'job_context' => 'Esta mensagem é sobre :job.',
        'job_helper' => 'Só é necessário quando a mensagem se refere a um processo seletivo específico.',
        'no_job_context' => 'Nenhuma vaga específica',
        'no_template' => 'Começar com uma mensagem em branco',
        'template_helper' => 'Escolher um modelo preenche o assunto e a mensagem abaixo. Você pode editá-los livremente antes de enviar.',
        'unresolved_context' => 'Esta mensagem ainda depende de um contexto indisponível: :variables. Selecione o contexto que falta ou remova-o do texto antes de enviar.',
        'provider_unavailable' => 'O envio não está disponível até que uma identidade de envio do workspace esteja disponível. Você ainda pode revisar e editar esta mensagem.',
        'sender_unavailable' => 'Identidade de envio indisponível',
    ],
    'history' => [
        'heading' => 'Comunicação', 'description' => 'Mensagens do Recruiter Labs enviadas a este candidato, agrupadas por vaga.', 'context_description' => 'Mensagens do Recruiter Labs enviadas a este candidato sobre :job.', 'application_description' => 'Mensagens enviadas pelo Recruiter Labs para este candidato e vaga.', 'empty' => 'Nenhuma comunicação foi registrada ainda.', 'empty_for_application' => 'Nenhuma comunicação foi registrada para esta candidatura ainda.', 'untitled_draft' => 'Rascunho sem título', 'ai_assisted' => 'Com assistência de IA', 'recruiter_message' => 'Mensagem do recrutador', 'pipeline_status_notification' => 'Notificação de status do pipeline', 'interview_scheduled_notification' => 'Notificação de entrevista agendada', 'interview_rescheduled_notification' => 'Notificação de entrevista reagendada', 'interview_cancelled_notification' => 'Notificação de entrevista cancelada', 'recorded_message' => 'Mensagem do Recruiter Labs', 'authorized_by' => 'Autorizado por :name', 'direction' => ':sender para :recipient',
    ],
    'statuses' => ['draft' => 'Rascunho', 'queued' => 'Envio solicitado', 'sending' => 'Enviando', 'sent' => 'Enviado', 'failed' => 'Falha na entrega', 'ambiguous' => 'Entrega precisa de revisão'],
    'dnc' => ['block_description' => 'Novas abordagens discricionárias serão bloqueadas. A comunicação existente continuará visível.', 'allow_description' => 'Este candidato poderá receber abordagens discricionárias novamente.', 'active_description' => 'Este candidato está marcado como não contatar. A comunicação existente permanece visível, mas novas abordagens discricionárias não estão disponíveis.'],
    'recipient' => ['invalid_email' => 'Este candidato não tem um endereço de e-mail válido. O envio não estará disponível até que os dados de contato sejam corrigidos.'],
    'errors' => ['invalid_email' => 'Este candidato não tem um endereço de e-mail válido.', 'do_not_contact' => 'Este candidato está marcado como não contatar.', 'provider_unavailable' => 'Este workspace não tem um provedor de e-mail padrão utilizável.', 'unresolved_context' => 'Esta mensagem ainda depende de um contexto indisponível: :variables.', 'already_authorized' => 'Esta mensagem já foi autorizada e não pode ser alterada.', 'draft_cannot_be_edited' => 'Apenas um rascunho não autorizado pode ser editado.', 'unavailable' => 'Esta comunicação não está mais disponível. Atualize a página e tente novamente.', 'subject_and_body_required' => 'Informe um assunto e uma mensagem antes de enviar.'],
    'notifications' => ['send_requested' => 'Envio de e-mail solicitado.', 'draft_discarded' => 'Rascunho descartado.', 'do_not_contact_set' => 'Candidato marcado como não contatar.', 'contact_allowed' => 'O candidato pode ser contatado novamente.'],
];
