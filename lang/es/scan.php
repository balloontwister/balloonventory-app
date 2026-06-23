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

    // Confirm match (ambiguous / low-confidence)
    'confirm_heading' => '¿Qué artículo escaneaste?',
    'confirm_body' => 'No fue una coincidencia exacta, así que elige el SKU correcto para',
    'confirm_select' => 'Este',
    'confirm_cancel' => 'Cancelar',

    // Barcode not detected — typed text fell back to a product search
    'no_barcode_heading' => 'Código de barras no detectado',
    'no_barcode_body' => '¿Es este el producto que intentas :action? Mejores coincidencias para',
    'no_barcode_empty' => 'Ningún producto coincide con lo que escribiste. Revisa la ortografía, o escanea o ingresa el código de barras.',
    'action_add' => 'agregar',
    'action_remove' => 'quitar',

    // Unknown UPC
    'unknown_upc' => 'Código desconocido',
    'unknown_upc_body' => 'Este código de barras no está vinculado a ningún SKU en tu catálogo.',
    'unknown_assign' => 'Vincular a un producto',
    'unknown_sku' => 'SKU desconocido',

    // Vincular código de barras a SKU
    'link' => [
        'title' => 'Vincular código de barras a un producto',
        'subtitle' => 'Encuentra el producto de esta bolsa y recordaremos este código la próxima vez.',
        'search_placeholder' => 'Buscar por nombre, tamaño, color…',
        'searching' => 'Buscando…',
        'no_results' => 'No hay productos coincidentes. Prueba otra búsqueda.',
        'has_barcode_badge' => 'Ya tiene un código de barras',
        'confirm' => 'Vincular este código',
        'cancel' => 'Cancelar',
        'linked_toast' => 'Código vinculado a :name.',
        'invalid_barcode' => 'Ese código de barras no parece válido — vuelve a escanear e inténtalo de nuevo.',
        'already_used' => 'Ese código ya está vinculado a ":name".',
        'has_other_code' => 'Este producto ya tiene otro código de barras registrado. Edítalo en el catálogo.',
    ],

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

    // Working bin selector
    'working_bin_label' => 'Contenedor de trabajo',
    'working_bin_auto' => 'Automático — ubicación del artículo',
    'working_bin_hint' => 'Los artículos nuevos van aquí. Los que ya están en existencia permanecen en su contenedor.',
    'bin_default_suffix' => '(predeterminado)',
    'recorded_to_bin' => 'en :bin',

    // Bin choice (which bin to pull from on removal across multiple bins)
    'pick_bin_heading' => 'Elige un contenedor del cual retirar',
    'pick_bin_body' => 'Este artículo está almacenado en más de un contenedor. ¿De cuál estás retirando?',
    'pick_bin_cancel' => 'Cancelar',
    'bin_holds' => ':full llenas / :open abiertas',

    // Bin-label scan
    'bin_set' => 'Contenedor de trabajo establecido en :bin',
    'bin_not_recognized' => 'No se reconoció ese código de contenedor.',

    // Modo solo lectura (invitado)
    'lookup_only_notice' => 'Tienes acceso de solo lectura. Escanea o escribe para buscar productos.',
    'found_heading' => 'Producto encontrado',
    'view_item' => 'Ver',
];
