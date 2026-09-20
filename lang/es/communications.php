<?php

return [
    'actions' => [
        'send_message' => 'Enviar mensaje',
        'send' => 'Enviar',
        'discard_draft' => 'Descartar borrador',
        'do_not_contact' => 'No contactar',
        'allow_contact' => 'Permitir contacto',
        'open_email_provider_settings' => 'Abrir configuración del proveedor de correo',
        'open_candidate_communications' => 'Abrir comunicación con :candidate',
    ],
    'fields' => ['recipient' => 'Destinatario', 'sender' => 'De', 'subject' => 'Asunto', 'body' => 'Mensaje', 'template' => 'Plantilla de correo', 'job_context' => 'Vacante'],
    'composer' => [
        'heading' => 'Enviar un mensaje a :candidate',
        'description' => 'Si quieres, empieza por una plantilla, edita el mensaje y envíalo.',
        'job_context' => 'Este mensaje trata sobre :job.',
        'job_helper' => 'Solo es necesario cuando el mensaje se refiere a un proceso de selección concreto.',
        'no_job_context' => 'Sin vacante específica',
        'no_template' => 'Empezar con un mensaje en blanco',
        'template_helper' => 'Elegir una plantilla completa el asunto y el mensaje de abajo. Puedes editarlos libremente antes de enviar.',
        'unresolved_context' => 'Este mensaje todavía depende de un contexto que no está disponible: :variables. Selecciona el contexto que falta o quítalo del texto antes de enviar.',
        'provider_unavailable' => 'El envío no está disponible hasta que haya una identidad de envío disponible en el espacio de trabajo. Aún puedes revisar y editar este mensaje.',
        'sender_unavailable' => 'Identidad de envío no disponible',
    ],
    'history' => [
        'heading' => 'Comunicación', 'description' => 'Mensajes de Recruiter Labs enviados a este candidato, agrupados por vacante.', 'context_description' => 'Mensajes de Recruiter Labs enviados a este candidato sobre :job.', 'application_description' => 'Mensajes enviados por Recruiter Labs para este candidato y vacante.', 'empty' => 'Todavía no se ha registrado ninguna comunicación.', 'empty_for_application' => 'Todavía no se ha registrado ninguna comunicación para esta candidatura.', 'untitled_draft' => 'Borrador sin título', 'ai_assisted' => 'Con asistencia de IA', 'recruiter_message' => 'Mensaje del reclutador', 'pipeline_status_notification' => 'Notificación de estado del flujo', 'interview_scheduled_notification' => 'Notificación de entrevista programada', 'interview_rescheduled_notification' => 'Notificación de entrevista reprogramada', 'interview_cancelled_notification' => 'Notificación de entrevista cancelada', 'recorded_message' => 'Mensaje de Recruiter Labs', 'authorized_by' => 'Autorizado por :name', 'direction' => ':sender para :recipient',
    ],
    'statuses' => ['draft' => 'Borrador', 'queued' => 'Envío solicitado', 'sending' => 'Enviando', 'sent' => 'Enviado', 'failed' => 'Entrega fallida', 'ambiguous' => 'La entrega necesita revisión'],
    'dnc' => ['block_description' => 'Se bloquearán los nuevos contactos discrecionales. La comunicación existente seguirá visible.', 'allow_description' => 'Este candidato puede volver a recibir contactos discrecionales.', 'active_description' => 'Este candidato está marcado como no contactar. La comunicación existente permanece visible, pero los nuevos contactos discrecionales no están disponibles.'],
    'recipient' => ['invalid_email' => 'Este candidato no tiene una dirección de correo válida. El envío no está disponible hasta que se corrijan sus datos de contacto.'],
    'errors' => ['invalid_email' => 'Este candidato no tiene una dirección de correo válida.', 'do_not_contact' => 'Este candidato está marcado como no contactar.', 'provider_unavailable' => 'Este espacio de trabajo no tiene un proveedor de correo predeterminado utilizable.', 'unresolved_context' => 'Este mensaje todavía depende de un contexto que no está disponible: :variables.', 'already_authorized' => 'Este mensaje ya ha sido autorizado y no se puede cambiar.', 'draft_cannot_be_edited' => 'Solo se puede editar un borrador no autorizado.', 'unavailable' => 'Esta comunicación ya no está disponible. Actualiza la página e inténtalo de nuevo.', 'subject_and_body_required' => 'Ingresa un asunto y un mensaje antes de enviar.'],
    'notifications' => ['send_requested' => 'Envío de correo solicitado.', 'draft_discarded' => 'Borrador descartado.', 'do_not_contact_set' => 'Candidato marcado como no contactar.', 'contact_allowed' => 'El candidato puede volver a ser contactado.'],
];
