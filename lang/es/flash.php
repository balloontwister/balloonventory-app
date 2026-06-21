<?php

return [
    'settings' => [
        'preferences_updated' => 'Preferencias actualizadas.',
        'business_name_updated' => 'Nombre del negocio actualizado.',
        'business_logo_updated' => 'Logo del negocio actualizado.',
    ],

    'profile' => [
        'avatar_updated' => 'Foto de perfil actualizada.',
    ],

    'catalog' => [
        'reference' => [
            'added' => 'Elemento agregado.',
            'updated' => 'Elemento actualizado.',
            'deleted' => 'Elemento eliminado.',
        ],
        'sku' => [
            'created' => 'SKU ":name" creado.',
            'updated' => 'SKU ":name" actualizado.',
            'deleted' => 'SKU eliminado.',
        ],
        'color' => [
            'added' => 'Color ":name" agregado.',
            'updated' => 'Color ":name" actualizado.',
            'deleted' => 'Color eliminado.',
        ],
        'brand' => [
            'added' => 'Marca ":name" agregada.',
            'updated' => 'Marca ":name" actualizada.',
            'gs1_added' => 'Prefijo GS1 :prefix agregado.',
            'gs1_removed' => 'Prefijo GS1 :prefix eliminado.',
        ],
    ],

    'support' => [
        'reply_failed' => 'No se pudo enviar la respuesta. Inténtalo de nuevo.',
    ],

    'feedback' => [
        'reply_failed' => 'No se pudo enviar el correo de respuesta. Inténtalo de nuevo.',
    ],

    'user_email' => [
        'sent' => 'Correo enviado a :name.',
        'failed' => 'No se pudo enviar el correo. Inténtalo de nuevo.',
    ],

    'users' => [
        'frozen' => 'La cuenta de :name ha sido congelada.',
        'thawed' => 'La cuenta de :name ha sido descongelada.',
        'reset_sent' => 'Enlace de restablecimiento enviado a :email.',
        'reset_failed' => 'No se pudo enviar el enlace de restablecimiento a esa dirección.',
        'deleted' => 'La cuenta de :name ha sido eliminada.',
        'frozen_notice' => 'Tu cuenta está limitada por ahora. Comunícate con soporte para restaurar el acceso completo.',
    ],

    'email_template' => [
        'saved_activated' => 'Plantilla guardada y activada. Ahora se enviará con su disparador.',
        'saved_deactivated' => 'Plantilla guardada y desactivada. No se enviará hasta que se active.',
        'saved_draft' => 'Plantilla guardada como borrador.',
        'preview_empty_body' => 'No se puede previsualizar una plantilla con el cuerpo HTML vacío.',
        'preview_failed' => 'No se pudo enviar la vista previa. Revisa el registro de la aplicación.',
        'preview_sent' => 'Vista previa enviada a :email.',
    ],

    'auth' => [
        'verification_code_resent' => 'Se ha enviado un nuevo código.',
    ],

    'inventory' => [
        'sku_added' => 'Agregado a tu inventario.',
        'sku_removed' => 'Eliminado del inventario.',
        'override_saved' => 'Cambios guardados.',
        'added_to_list' => 'Agregado a ":list".',
        'transfer_done' => 'Existencias transferidas.',
        'transfer_nothing' => 'Ingresa al menos una bolsa para transferir.',
        'transfer_insufficient' => 'No hay suficientes existencias en el contenedor de origen. Disponible: :full llenas / :open abiertas.',
        'stock_adjusted' => 'Existencias actualizadas.',
        'bin_removed' => 'Contenedor eliminado para este artículo.',
        'bin_not_empty' => 'Ese contenedor aún tiene existencias — vacíalo antes de eliminarlo.',
        'feedback_submitted' => '¡Gracias! Tus comentarios se enviaron a nuestro equipo.',
    ],

    'onboarding' => [
        'completed' => '¡Tu tienda está lista! Bienvenido a bordo.',
        'samples_cleared' => 'Se eliminaron :count productos de muestra.',
    ],

    'lists' => [
        'created' => 'Lista ":list" creada.',
        'updated' => 'Lista ":list" actualizada.',
        'deleted' => 'Lista ":list" eliminada.',
        'item_added' => 'Agregado a ":list".',
        'item_removed' => 'Artículo eliminado de la lista.',
    ],
];
