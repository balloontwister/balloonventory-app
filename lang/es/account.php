<?php

return [
    'meta_title' => 'Cuenta',
    'heading' => 'Cuenta',

    'frozen_banner' => [
        'title' => 'Tu cuenta está limitada',
        'body' => 'El acceso a la app está pausado por ahora. Aún puedes actualizar tu perfil y preferencias aquí. Comunícate con soporte para restaurar el acceso completo.',
    ],

    'rows' => [
        'profile' => [
            'label' => 'Perfil',
            'subtext' => 'Nombre, correo, contraseña, avatar',
        ],
        'business' => [
            'label' => 'Mi Negocio',
            'subtext_fallback' => 'Gestiona el nombre y el logo del negocio',
        ],
        'preferences' => [
            'label' => 'Preferencias',
            'subtext' => 'Idioma y zona horaria',
        ],
        'support' => [
            'label' => 'Ayuda y soporte',
            'subtext' => 'Contacta al equipo de Balloonventory',
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'subtext' => 'Administración del sitio',
        ],
        'log_out' => [
            'label' => 'Cerrar sesión',
        ],
    ],

    'other_businesses' => [
        'heading' => 'Otros Negocios',
        'switch' => 'Cambiar',
        'leave' => 'Salir',
        'leave_confirm' => '¿Salir de :business? Perderás el acceso a menos que te vuelvan a invitar.',
    ],
];
