<?php

return [
    'navigation_label' => 'Proveedor de Correo',
    'title' => 'Proveedor de Correo',
    'subtitle' => 'Configura los proveedores de correo utilizados para el envío de reclutamiento.',
    'eyebrow' => 'Configuración del proveedor de correo',
    'heading' => 'Configura tus proveedores de correo de reclutamiento',
    'description' => 'Esto configura los proveedores utilizados para enviar comunicaciones con candidatos y automatizaciones. No afecta los correos de la propia cuenta del sistema.',
    'default_badge' => 'Predeterminado',
    'fields' => [
        'provider' => 'Proveedor',
        'api_key' => 'Clave API',
    ],
    'providers' => [
        'resend' => 'Resend',
    ],
    'status' => [
        'valid' => 'Validada',
        'invalid' => 'Requiere atención',
        'untested' => 'Aún no probada',
        'not_configured' => 'No configurada',
        'last_validated' => 'Última validación: :date',
        'never_validated' => 'Nunca validada',
    ],
    'configure' => [
        'heading' => 'Configurar :provider',
        'description' => 'La clave se cifra y nunca volverá a mostrarse completa.',
        'save' => 'Guardar y validar',
    ],
    'remove' => [
        'heading' => '¿Eliminar la clave de :provider?',
        'description' => 'Los correos de reclutamiento dejarán de enviarse por este proveedor hasta que se configure una nueva clave. El historial de uso no se verá afectado.',
        'confirm' => 'Eliminar clave',
    ],
    'empty' => [
        'heading' => 'Aún no configurado',
        'description' => 'Agrega una clave API para habilitar las comunicaciones con candidatos y las automatizaciones en este espacio.',
    ],
    'actions' => [
        'configure' => 'Configurar',
        'replace' => 'Sustituir clave',
        'test' => 'Probar conexión',
        'remove' => 'Eliminar clave',
        'set_default' => 'Establecer como predeterminado',
    ],
    'notifications' => [
        'key_removed' => 'Clave del proveedor de correo eliminada',
        'default_updated' => 'Proveedor de correo predeterminado actualizado',
    ],
];
