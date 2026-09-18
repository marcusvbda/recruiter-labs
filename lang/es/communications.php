<?php

return [
    'actions' => [
        'contact_candidate' => 'Contactar candidato',
        'prepare_outreach' => 'Preparar contacto',
        'prepare_follow_up' => 'Preparar seguimiento',
        'prepare_with_ai' => 'Preparar con IA',
        'send' => 'Enviar',
        'save_draft' => 'Guardar borrador',
        'do_not_contact' => 'No contactar',
        'allow_contact' => 'Permitir contacto',
        'open_email_provider_settings' => 'Abrir configuración del proveedor de correo',
        'open_candidate_communications' => 'Abrir comunicación con :candidate',
    ],
    'fields' => ['recipient' => 'Destinatario', 'sender' => 'De', 'subject' => 'Asunto', 'body' => 'Mensaje', 'language' => 'Idioma del borrador'],
    'composer' => ['heading' => 'Contactar a :candidate', 'job_context' => 'Este mensaje trata sobre :job.', 'provider_unavailable' => 'El envío no está disponible hasta que haya una identidad de envío disponible en el espacio de trabajo. Aún puedes revisar y editar este borrador.', 'sender_unavailable' => 'Identidad de envío no disponible'],
    'history' => [
        'heading' => 'Comunicación', 'description' => 'Mensajes de Recruiter Labs enviados a este candidato, agrupados por vacante.', 'context_description' => 'Mensajes de Recruiter Labs enviados a este candidato sobre :job.', 'application_description' => 'Mensajes enviados por Recruiter Labs para este candidato y vacante.', 'empty' => 'Todavía no se ha registrado ninguna comunicación.', 'empty_for_application' => 'Todavía no se ha registrado ninguna comunicación para esta candidatura.', 'untitled_draft' => 'Borrador sin título', 'ai_assisted' => 'Con asistencia de IA', 'recruiter_message' => 'Mensaje del reclutador', 'pipeline_status_notification' => 'Notificación de estado del flujo', 'interview_scheduled_notification' => 'Notificación de entrevista programada', 'interview_rescheduled_notification' => 'Notificación de entrevista reprogramada', 'interview_cancelled_notification' => 'Notificación de entrevista cancelada', 'recorded_message' => 'Mensaje de Recruiter Labs', 'authorized_by' => 'Autorizado por :name', 'direction' => ':sender para :recipient',
    ],
    'statuses' => ['draft' => 'Borrador', 'queued' => 'Envío solicitado', 'sending' => 'Enviando', 'sent' => 'Enviado', 'failed' => 'Entrega fallida', 'ambiguous' => 'La entrega necesita revisión'],
    'dnc' => ['block_description' => 'Se bloquearán los nuevos contactos discrecionales y los borradores de IA. La comunicación existente seguirá visible.', 'allow_description' => 'Este candidato puede volver a recibir contactos discrecionales.', 'active_description' => 'Este candidato está marcado como no contactar. La comunicación existente permanece visible, pero los nuevos contactos discrecionales y los borradores de IA no están disponibles.'],
    'recipient' => ['invalid_email' => 'Este candidato no tiene una dirección de correo válida. El envío no está disponible hasta que se corrijan sus datos de contacto.'],
    'errors' => ['invalid_email' => 'Este candidato no tiene una dirección de correo válida.', 'do_not_contact' => 'Este candidato está marcado como no contactar.', 'provider_unavailable' => 'Este espacio de trabajo no tiene un proveedor de correo predeterminado utilizable.', 'ai_unavailable' => 'La redacción con IA no está disponible para este espacio de trabajo. Puedes continuar con un mensaje manual.', 'ai_allowance_reached' => 'Se ha alcanzado la asignación de IA del espacio de trabajo. Puedes continuar con un mensaje manual.', 'follow_up_requires_history' => 'Prepara un seguimiento después de que exista al menos un mensaje saliente autorizado en esta comunicación.', 'already_authorized' => 'Este mensaje ya ha sido autorizado y no se puede cambiar.', 'draft_cannot_be_edited' => 'Solo se puede editar un borrador no autorizado.', 'unavailable' => 'Esta comunicación ya no está disponible. Actualiza la página e inténtalo de nuevo.', 'subject_and_body_required' => 'Ingresa un asunto y un mensaje antes de enviar.'],
    'notifications' => ['send_requested' => 'Envío de correo solicitado.', 'draft_prepared' => 'Borrador de IA preparado. Revísalo y envíalo explícitamente cuando esté listo.', 'draft_saved' => 'Borrador guardado.', 'do_not_contact_set' => 'Candidato marcado como no contactar.', 'contact_allowed' => 'El candidato puede volver a ser contactado.'],
];
