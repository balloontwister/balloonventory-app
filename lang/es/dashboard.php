<?php

return [
    'meta_title' => 'Panel',
    'heading' => 'Panel',

    'greeting_morning' => 'Buenos días, :name',
    'greeting_afternoon' => 'Buenas tardes, :name',
    'greeting_evening' => 'Buenas noches, :name',

    'quick_actions' => [
        'title' => 'Acciones Rápidas',
        'scan_in' => 'Escanear Entrada',
        'scan_out' => 'Escanear Salida',
        'add_inventory' => 'Agregar Inventario',
        'reorder_list' => 'Lista de Reorden',
        'view_inventory' => 'Ver Inventario',
        'no_actions' => 'Solo tienes acceso de lectura. Pide a un propietario que actualice tu rol.',
    ],

    'low_stock' => [
        'title' => 'Stock Bajo',
        'empty' => 'Estás bien abastecido.',
        'on_hand' => 'en mano',
        'threshold' => 'mínimo',
        'view_all' => 'Ver lista de reorden',
        'count' => '{0} Todo abastecido|{1} :count artículo necesita reposición|[2,*] :count artículos necesitan reposición',
    ],

    'kpis' => [
        'title' => 'Resumen de Inventario',
        'distinct_skus' => 'SKUs',
        'total_bags' => 'Bolsas Totales',
        'bins' => 'Compartimentos',
        'low_stock' => 'Stock Bajo',
    ],

    'activity' => [
        'title' => 'Actividad Reciente',
        'empty' => 'Sin actividad aún. Comienza escaneando globos.',
        'direction' => [
            'in' => 'Entrada escaneada',
            'out' => 'Salida escaneada',
            'removed' => 'Eliminado',
            'restored' => 'Restaurado',
            'adjusted' => 'Ajustado',
        ],
        'bags' => ':count bolsa|:count bolsas',
        'open_bags' => ':count bolsa abierta|:count bolsas abiertas',
    ],

    'nudges' => [
        'clear_samples' => 'Tienes inventario de muestra. Elimínalo cuando estés listo para rastrear stock real.',
        'clear_samples_action' => 'Limpiar Muestras',
        'verify_email' => 'Por favor verifica tu correo electrónico para mantener tu cuenta segura.',
        'verify_email_action' => 'Reenviar Verificación',
        'user_contact' => 'Agrega tu número de teléfono para que los miembros del equipo puedan contactarte.',
        'user_contact_action' => 'Actualizar Perfil',
        'business_contact' => 'Agrega los datos de contacto de tu negocio para que los clientes puedan encontrarte.',
        'business_contact_action' => 'Actualizar Negocio',
        'onboarding' => 'Termina de configurar tu cuenta para aprovechar al máximo Balloonventory.',
        'onboarding_action' => 'Continuar Configuración',
    ],

    'empty_state' => [
        'title' => 'Bienvenido a Balloonventory',
        'body' => 'Agrega tu primer SKU de globos o escanea el código de barras de una bolsa para comenzar.',
        'add' => 'Ver Inventario',
        'scan' => 'Escanear una Bolsa',
    ],
];
