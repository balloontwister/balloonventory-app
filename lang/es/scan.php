<?php

return [
    'meta_title' => 'Escanear',
    'heading' => 'Escanear',

    // Mode toggle
    'mode_add' => 'Añadir',
    'mode_remove' => 'Quitar',

    // Quantity / open bag
    'qty_label' => 'Cantidad',
    'qty_preset_3' => '3',
    'qty_preset_5' => '5',
    'qty_preset_10' => '10',
    'open_bag_label' => 'Bolsa abierta',

    // Context hints — pluralized via $tChoice.
    'adding_full_context' => '{1} Añadiendo :count bolsa al inventario.|[2,*] Añadiendo :count bolsas al inventario.',
    'adding_open_context' => '{1} Añadiendo :count bolsa abierta al inventario.|[2,*] Añadiendo :count bolsas abiertas al inventario.',
    'removing_full_context' => '{1} Quitando :count bolsa del inventario.|[2,*] Quitando :count bolsas del inventario.',
    'removing_open_context' => '{1} Quitando :count bolsa abierta del inventario.|[2,*] Quitando :count bolsas abiertas del inventario.',

    // Scan field
    'scan_placeholder' => 'Escanea un código de barras…',
    'ready_to_scan' => 'Listo para escanear',
    'looking_up' => 'Buscando…',
    'scan_error' => 'Error de escaneo',
    'scanning' => 'Escaneando…',
    'duplicate' => 'Ya escaneado',
    'duplicate_hint' => 'Acabas de escanear ese código — cambia algo para registrarlo de nuevo.',
    'checking_in_to' => 'Añadiendo a :business',
    'checking_out_to' => 'Quitando de :business',
    'checking_out_for' => 'Quitando para :job · :business',

    // Camera
    'camera_button' => 'Escanear con cámara',
    'camera_start' => 'Iniciar cámara',
    'camera_stop' => 'Detener cámara',
    'camera_unsupported' => 'El escaneo con cámara no es compatible con este dispositivo.',
    'camera_error' => 'No se pudo acceder a la cámara.',
    'camera_permission_denied' => 'Se denegó el acceso a la cámara. Habilítalo en la configuración del navegador e inténtalo de nuevo.',
    'camera_not_found' => 'No se encontró ninguna cámara en este dispositivo.',
    'camera_in_use' => 'La cámara está en uso por otra aplicación. Ciérrala e inténtalo de nuevo.',
    'camera_hint' => 'Centra el código en el recuadro',
    'camera_retry' => 'Reintentar',
    'captured' => '¡Listo!',

    // General actions
    'close' => 'Cerrar',

    // Unknown UPC
    'unknown_upc' => 'Código desconocido',
    'unknown_upc_body' => 'Este código de barras no está vinculado a ningún SKU en tu catálogo.',
    'unknown_assign' => 'Asignar a SKU',
    'unknown_sku' => 'SKU desconocido',

    // Recent scans
    'recent_heading' => 'Escaneos recientes',
    'recent_empty' => 'No hay escaneos aún',
    'hide_recent' => 'Ocultar todo',

    // Actions
    'undo' => 'Deshacer',
    'undone' => 'Deshecho',

    // Bag-type badges
    'bag_open' => 'abierta',
    'bag_mixed' => 'mixto',

    // Status / errors
    'error_network' => 'Error de red — reintentar',
    'error_lookup' => 'No se pudo buscar el código.',
    'error_insufficient_stock' => 'No hay suficientes existencias.',

    // Misc
    'stock_label' => 'En existencia',
];
