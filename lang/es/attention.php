<?php

return [
    'heading' => 'Requiere tu atención',
    'empty_heading' => 'Todo bajo control.',
    'empty_description' => 'Ningún elemento de reclutamiento requiere tu atención ahora.',
    'hidden' => '{1} 1 elemento más no aparece aquí.|[2,*] :count elementos más no aparecen aquí.',
    'job_heading' => 'Requiere atención en este proceso',
    'severities' => [
        'critical' => 'Con fallo',
        'warning' => 'Esperando por ti',
        'info' => 'Conviene saber',
    ],
    'days' => '{0} menos de un día|{1} 1 día|[2,*] :count días',
    'items' => [
        'interview_declined' => [
            'title' => ':candidate rechazó la entrevista',
            'explanation' => 'La invitación para :date fue rechazada en el calendario.',
            'action' => 'Reprogramar entrevista',
        ],
        'interview_calendar_failed' => [
            'title' => 'La entrevista con :candidate no está en el calendario',
            'explanation' => 'La entrevista del :date existe aquí, pero su evento de calendario no pudo crearse, así que el candidato puede no tener ninguna invitación.',
            'action' => 'Abrir entrevistas',
        ],
        'calendar_reconnect_required' => [
            'title' => 'Tu conexión de calendario caducó',
            'explanation' => '{1} 1 entrevista tuya ya no puede sincronizarse hasta que reconectes el calendario.|[2,*] :count entrevistas tuyas ya no pueden sincronizarse hasta que reconectes el calendario.',
            'action' => 'Reconectar calendario',
        ],
        'evaluation_failed' => [
            'title' => 'La evaluación de :candidate falló',
            'explanation' => 'La evaluación del candidato terminó con error, así que no hay ajuste ni evidencia que leer. La candidatura en sí no se modificó.',
            'action' => 'Abrir evaluación',
        ],
        'evaluation_blocked_by_quota' => [
            'title' => 'Evaluaciones esperando cupo de IA',
            'explanation' => '{1} 1 candidatura está en cola y no puede evaluarse hasta que el espacio de trabajo vuelva a tener cupo.|[2,*] :count candidaturas están en cola y no pueden evaluarse hasta que el espacio de trabajo vuelva a tener cupo.',
            'action' => 'Revisar uso de IA',
        ],
        'stage_overdue' => [
            'title' => ':candidate está esperando en :stage',
            'explanation' => 'Esperando :waited en :stage — esta etapa está configurada para avisar después de :threshold.',
            'action' => 'Abrir candidatura',
        ],
        'decision_pending' => [
            'title' => ':candidate está esperando una decisión',
            'explanation' => 'Llegó a :stage hace :waited y no tiene próxima entrevista programada.',
            'action' => 'Abrir candidatura',
        ],
        'job_stalled' => [
            'title' => ':job tiene candidaturas pero ningún avance',
            'explanation' => '{1} 1 candidato se postuló y ninguno llegó a una entrevista, etapa final o contratación.|[2,*] :count candidatos se postularon y ninguno llegó a una entrevista, etapa final o contratación.',
            'action' => 'Abrir pipeline',
        ],
        'job_ending_without_finalists' => [
            'title' => ':job termina pronto sin nadie cerca de la contratación',
            'explanation' => 'La campaña termina el :date y no hay finalistas ni contrataciones.',
            'action' => 'Revisar vacante',
        ],
        'hiring_target_reached' => [
            'title' => ':job alcanzó su objetivo de contratación',
            'explanation' => ':hired de :target posiciones cubiertas. Decide si pausar candidaturas, despublicar la vacante o seguir reclutando.',
            'action' => 'Revisar vacante',
        ],
        'hiring_target_near' => [
            'title' => 'A :job le falta una contratación para su objetivo',
            'explanation' => ':hired de :target posiciones cubiertas.',
            'action' => 'Abrir pipeline',
        ],
    ],
];
