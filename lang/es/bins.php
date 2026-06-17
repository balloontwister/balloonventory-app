<?php

return [
    'meta_title' => 'Contenedores',
    'heading' => 'Inventario',

    'tabs' => [
        'by_item' => 'Por artículo',
        'by_bin' => 'Por contenedor',
    ],

    'add_location' => 'Agregar ubicación',
    'add_bin' => 'Agregar contenedor',
    'default_badge' => 'Predeterminado',
    'view_label' => 'Ver etiqueta',
    'print_all' => 'Imprimir todas las etiquetas',
    'print_title' => 'Etiquetas de contenedores',

    'label' => [
        'view_title' => 'Etiqueta del contenedor',
        'size' => 'Tamaño de etiqueta',
        'custom' => 'Tamaño personalizado',
        'width_in' => 'Ancho (pulg)',
        'height_in' => 'Alto (pulg)',
        'copy' => 'Copiar imagen',
        'copied' => 'Copiado al portapapeles',
        'copy_error' => 'No se pudo copiar — usa Descargar',
        'download_png' => 'Descargar PNG',
        'download_svg' => 'Descargar SVG',
    ],

    'empty' => [
        'heading' => 'Aún no hay contenedores',
        'body' => 'Agrega una ubicación y contenedores para organizar dónde está tu inventario.',
    ],

    'location' => [
        'bins_count_singular' => ':count contenedor',
        'bins_count_plural' => ':count contenedores',
        'empty' => 'Esta ubicación aún no tiene contenedores.',
    ],

    'bin' => [
        'number_prefix' => '#:number',
        'summary_empty' => 'Vacío',
        'summary_singular' => ':count artículo',
        'summary_plural' => ':count artículos',
        'full_bags' => ':count llenas',
        'open_bags' => ':count abiertas',
        'empty' => 'Este contenedor está vacío.',
        'expand' => 'Ver contenido',
        'collapse' => 'Ocultar contenido',
        'loading' => 'Cargando contenido…',
    ],

    'form' => [
        'location_name' => 'Nombre de la ubicación',
        'location_name_placeholder' => 'p. ej. Trastienda, Camioneta',
        'bin_name' => 'Nombre del contenedor',
        'bin_name_placeholder' => 'p. ej. Estante superior, Caja verde',
        'bin_number' => 'Número',
        'bin_number_placeholder' => 'Opcional',
        'bin_location' => 'Ubicación',
        'description' => 'Descripción',
        'description_placeholder' => 'Notas opcionales',
        'create_location_title' => 'Agregar ubicación',
        'edit_location_title' => 'Editar ubicación',
        'create_bin_title' => 'Agregar contenedor',
        'edit_bin_title' => 'Editar contenedor',
        'create' => 'Crear',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
    ],

    'delete' => [
        'bin_confirm' => '¿Eliminar este contenedor? Esta acción no se puede deshacer.',
        'location_confirm' => '¿Eliminar esta ubicación? Esta acción no se puede deshacer.',
    ],

    'flash' => [
        'location_created' => 'Ubicación creada.',
        'location_updated' => 'Ubicación actualizada.',
        'location_deleted' => 'Ubicación eliminada.',
        'location_default_protected' => 'La ubicación Predeterminada no se puede eliminar.',
        'location_has_bins' => 'Elimina los contenedores de esta ubicación antes de eliminarla.',
        'bin_created' => 'Contenedor creado.',
        'bin_updated' => 'Contenedor actualizado.',
        'bin_deleted' => 'Contenedor eliminado.',
        'bin_default_protected' => 'El contenedor Predeterminado no se puede eliminar.',
        'bin_has_stock' => 'Este contenedor todavía tiene existencias. Retíralas o transfiérelas primero.',
    ],
];
