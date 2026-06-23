<?php

return [
    'index' => [
        'meta_title' => 'Listas y trabajos',
        'heading' => 'Listas y trabajos',
        'new_list' => 'Nueva lista',
        'section_lists' => 'Listas',
        'section_jobs' => 'Trabajos',
        'sku_count' => '{0} Sin artículos|{1} :count artículo|[2,*] :count artículos',
        'favorites_empty' => 'Aún no hay favoritos: marca artículos desde tu inventario.',
        'no_custom_lists' => 'Aún no hay listas. Crea una para agrupar SKU de un evento, un cliente o un pedido recurrente.',
        'no_archived_lists' => 'No hay listas archivadas.',
        'show_archived' => '{1} Mostrar :count lista archivada|[2,*] Mostrar :count listas archivadas',
        'hide_archived' => 'Ocultar archivadas',
        'section_archived' => 'Archivadas',
        'jobs_coming_title' => 'Los trabajos llegarán pronto',
        'jobs_coming_hint' => 'Un trabajo es una lista con fecha de evento y datos del cliente. Aquí podrás planificar y retirar el stock para un evento.',
    ],

    'inventory' => [
        'meta_title' => 'Inventario — Por lista',
        'manage_lists' => 'Gestionar listas',
        'no_lists' => 'Aún no hay listas para mostrar.',
    ],

    'detail' => [
        'col_item' => 'Artículo',
        'col_brand' => 'Marca',
        'col_size' => 'Tamaño',
        'col_stock' => 'Disponible',
        'edit' => 'Editar lista',
        'delete' => 'Eliminar',
        'archived_badge' => 'Archivada',
        'archived_notice' => 'Esta lista está archivada y oculta de la vista principal.',
        'unarchive' => 'Desarchivar',
        'remove_item' => 'Quitar de la lista',
        'empty_title' => 'Esta lista está vacía',
        'empty_hint' => 'Busca arriba para agregar artículos o explora tu inventario.',
        'browse_inventory' => 'Explorar inventario',
        'add_placeholder' => 'Buscar artículos para agregar…',
        'add_searching' => 'Buscando…',
        'add_no_results' => 'No hay artículos coincidentes.',
        'add_already' => 'Agregado',
        'delete_confirm_title' => '¿Eliminar esta lista?',
        'delete_confirm_body' => 'La lista ":list" se eliminará. Los artículos que contiene no se eliminan de tu inventario.',
    ],

    'items' => [
        'planned_quantity' => 'Cant. planificada',
        'reorder_threshold' => 'Reabastecer en',
        'none' => '—',
    ],

    'form' => [
        'name_label' => 'Nombre de la lista',
        'name_placeholder' => 'p. ej. Boda Smith, Pedido mensual',
        'notes_label' => 'Notas',
        'notes_placeholder' => 'Notas opcionales para tu equipo',
        'visibility_label' => 'Tipo de lista',
        'visibility_standard' => 'Estándar — el equipo puede ver y editar',
        'visibility_owner_editable' => 'Lista del propietario — el equipo ve, solo tú puedes editar',
        'visibility_private' => 'Privada — solo tú puedes ver y editar',
        'archive_label' => 'Archivar esta lista',
        'archive_hint' => 'Oculta de la vista principal, pero disponible cuando muestras listas archivadas.',
        'create_submit' => 'Crear lista',
        'save_submit' => 'Guardar cambios',
    ],

    'create' => [
        'meta_title' => 'Nueva lista',
        'heading' => 'Nueva lista',
    ],
    'show' => [
        'meta_title' => 'Lista',
        'heading' => 'Lista',
    ],
    'edit' => [
        'meta_title' => 'Editar lista',
        'heading' => 'Editar lista',
    ],

    'history' => [
        'heading' => 'Historial',
        'created' => ':user creó esta lista',
        'renamed' => ':user renombró de ":old" a ":new"',
        'archived' => ':user archivó esta lista',
        'unarchived' => ':user desarchivó esta lista',
        'visibility_changed' => ':user cambió el tipo de lista',
        'item_added' => ':user agregó :sku',
        'item_removed' => ':user eliminó :sku',
        'item_qty_changed' => ':user cambió la cantidad planificada de :sku de :old a :new',
        'show_more' => 'Mostrar :count más',
        'show_less' => 'Mostrar menos',
    ],
];
