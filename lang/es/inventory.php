<?php

return [
    'meta_title' => 'Inventario',
    'heading' => 'Inventario',

    'empty_heading' => 'Tu inventario está vacío',
    'empty_body' => 'Busca en el catálogo para agregar tu primer globo.',
    'empty_search_placeholder' => 'Buscar globos…',

    'samples' => [
        'banner' => 'Parte de esto es inventario de muestra que Tallie agregó durante la configuración.',
        'delete' => 'Eliminar productos de muestra',
        'confirm_title' => '¿Eliminar productos de muestra?',
        'confirm_body' => 'Esto quita los globos de muestra que agregó Tallie, pero conserva los que ya hayas escaneado o editado.',
        'confirm_cancel' => 'Conservarlos',
        'confirm_delete' => 'Eliminar muestras',
    ],

    'toolbar' => [
        'search_placeholder' => 'Buscar inventario…',
        'sort_label' => 'Ordenar',
        'sort_recent' => 'Actividad reciente',
        'sort_name' => 'Nombre',
        'sort_color_family' => 'Familia de color',
        'sort_shape' => 'Forma',
        'sort_size' => 'Tamaño',
        'reset_filters' => 'Restablecer',
        'filter_all_brands' => 'Todas las marcas',
        'filter_all_sizes' => 'Todos los tamaños',
        'filter_all_shapes' => 'Todas las formas',
        'filter_all_textures' => 'Todas las texturas',
        'filter_all_colors' => 'Todos los colores',
        'filter_all_materials' => 'Todos los materiales',
    ],

    'count_singular' => ':count artículo',
    'count_plural' => ':count artículos',

    'col_name' => 'Globo',
    'col_brand' => 'Marca',
    'col_size' => 'Tamaño',
    'col_bags' => 'En mano',

    'catalog_fallback_divider' => 'Del catálogo — no está en tu inventario',

    'action_view' => 'Ver',
    'action_remove' => 'Eliminar',
    'action_add_to_inventory' => 'Agregar al inventario',
    'action_add_to_list' => 'Agregar a lista',

    'add_to_list_heading' => 'Agregar a lista',
    'add_to_list_no_lists' => 'Aún no tienes listas.',
    'add_to_list_cancel' => 'Cancelar',
    'add_to_list_confirm' => 'Agregar',

    'show' => [
        'back' => 'Volver al inventario',
        'section_stock' => 'Existencias',
        'section_details' => 'Detalles',
        'section_override' => 'Tus personalizaciones',
        'section_history' => 'Actividad reciente',

        'detail_brand' => 'Marca',
        'detail_size' => 'Tamaño',
        'detail_color' => 'Color',
        'detail_texture' => 'Textura',
        'detail_material' => 'Material',
        'detail_count' => 'Cantidad',
        'detail_count_value' => ':count / bolsa',
        'detail_packaging' => 'Empaque',

        'stock_full' => 'Llenas',
        'stock_open' => 'Abiertas',
        'stock_save' => 'Guardar',
        'stock_reset' => 'Restablecer',
        'stock_pending_hint' => 'antes :full llenas · :open abiertas',
        'stock_move' => 'Mover',
        'stock_add_bin' => 'Agregar contenedor',
        'stock_choose_bin' => 'Elige un contenedor…',
        'stock_add' => 'Agregar',
        'stock_add_cancel' => 'Cancelar',
        'stock_across_bins' => 'en :count contenedores',
        'stock_across_one_bin' => 'en 1 contenedor',

        'customize_add' => 'Agregar un nombre, color o notas personalizados',
        'customize_edit' => 'Editar personalizaciones',
        'customize_summary' => 'Detalles personalizados establecidos',
        'customize_cancel' => 'Cancelar',

        'override_custom_name_label' => 'Nombre personalizado',
        'override_custom_name_placeholder' => 'Dejar en blanco para usar el nombre predeterminado',
        'override_color_hex_label' => 'Color personalizado',
        'override_notes_label' => 'Notas',
        'override_notes_placeholder' => 'Notas privadas para tu equipo',
        'override_save' => 'Guardar',

        'reorder_label' => 'Cantidad de reorden (Favoritos)',
        'reorder_hint' => 'Se establece en tu lista de Favoritos. Edítalo allí.',

        'history_direction_in' => 'Entrada',
        'history_direction_out' => 'Salida',
        'history_direction_removed' => 'Eliminado',
        'history_direction_restored' => 'Restaurado',
        'history_direction_adjusted' => 'Ajustado',
        'history_no_activity' => 'Aún no se ha registrado actividad.',

        'transfer_button' => 'Transferir',
        'transfer_title' => 'Transferir existencias',
        'transfer_from' => 'Desde el contenedor',
        'transfer_to' => 'Al contenedor',
        'transfer_full_bags' => 'Bolsas llenas',
        'transfer_open_bags' => 'Bolsas abiertas',
        'transfer_submit' => 'Transferir',
        'transfer_cancel' => 'Cancelar',
        'transfer_bin_holds' => ':full llenas / :open abiertas',
    ],

    'pagination_prev' => 'Anterior',
    'pagination_next' => 'Siguiente',
    'pagination_label' => 'Página :current de :last',
];
