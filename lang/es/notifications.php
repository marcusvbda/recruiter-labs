<?php

return [
    'candidate_import' => [
        'completed' => [
            'title' => 'Importación de candidatos finalizada',
            'body' => ':source: :imported candidato(s) añadido(s) a tu base, :unresolved fila(s) aún necesitan una decisión.',
            'action' => 'Abrir importación',
        ],
        'failed' => [
            'title' => 'La importación de candidatos no pudo finalizar',
            'body' => ':source se detuvo antes de procesar todas las filas. Lo que sí se importó ya está en tu base.',
            'action' => 'Abrir importación',
        ],
    ],

    'sourcing' => [
        'completed' => [
            'title' => 'Revisión de la base de talento finalizada para :job',
            'body' => 'Se revisaron :reviewed perfil(es) para esta vacante.',
            'action' => 'Abrir sourcing',
        ],
        'failed' => [
            'title' => 'La revisión de la base de talento se detuvo en :job',
            'body' => 'La revisión no cubrió toda tu base. Puedes volver a ejecutarla cuando quieras.',
            'action' => 'Abrir sourcing',
        ],
        'blocked' => [
            'title' => 'Revisión de la base de talento pausada en :job',
            'body' => 'Tu cupo de IA se agotó durante la revisión, así que el resto de la base no se vio.',
            'action' => 'Abrir sourcing',
        ],
    ],

    'communication' => [
        'failed' => [
            'title' => 'El correo para :candidate no se entregó',
            'body' => 'El mensaje no se pudo enviar. Abre la conversación y envíalo de nuevo.',
            'action' => 'Abrir conversación',
        ],
    ],

    'interview' => [
        'declined' => [
            'title' => ':candidate rechazó la entrevista',
            'body' => 'La invitación se rechazó en el calendario. El horario sigue reservado aquí.',
            'action' => 'Abrir entrevistas',
        ],
    ],

    'criteria' => [
        'failed' => [
            'title' => 'No se pudieron preparar los criterios de contratación de :job',
            'body' => 'Los candidatos de esta vacante no se evaluarán hasta que los criterios estén listos.',
            'action' => 'Abrir vacante',
        ],
    ],

    'ai_allowance' => [
        'blocked' => [
            'title' => 'Cupo de IA agotado',
            'body' => 'El trabajo automático está en espera hasta que haya cupo disponible.',
            'action' => 'Abrir ajustes de IA',
        ],
    ],
];
