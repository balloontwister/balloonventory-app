<?php

return [
    'settings' => [
        'preferences_updated' => 'Preferencias actualizadas.',
        'business_name_updated' => 'Nombre del negocio actualizado.',
        'business_logo_updated' => 'Logo del negocio actualizado.',
        'business_color_updated' => 'Color de acento actualizado.',
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
        'password_set' => 'Contraseña actualizada para :name.',
        'password_set_notified' => 'Contraseña actualizada para :name y se envió un correo de notificación.',
        'password_set_no_email' => 'Contraseña actualizada para :name, pero no hay correo registrado — no se envió notificación.',
        'password_set_notify_unavailable' => 'Contraseña actualizada para :name, pero la plantilla de correo de notificación está inactiva — no se envió notificación.',
    ],

    'businesses' => [
        'suspended' => ':name ha sido suspendido.',
        'unsuspended' => ':name ha sido reactivado.',
        'deleted' => ':name ha sido eliminado.',
        'frozen_notice' => 'Este negocio está suspendido. Contacta con soporte para restaurar el acceso.',
        'view_as_started' => 'Ahora viendo :name como administrador. Tus cambios afectan a este negocio.',
        'view_as_stopped' => 'Saliste del negocio — de vuelta a tu vista de administrador.',
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
        'item_added_to_bin' => 'Artículo agregado a este contenedor.',
        'item_not_available' => 'Ese artículo no está disponible para este negocio.',
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

    'memberships' => [
        'invited' => ':name ha sido invitado.',
        'invite_sent_no_email' => ':name ha sido invitado pero la plantilla de correo de notificación está inactiva — no se envió correo.',
        'unknown_email' => 'No se encontró ninguna cuenta de Balloonventory para esa dirección de correo.',
        'self_invite' => 'No puedes invitarte a ti mismo.',
        'already_member' => 'Esa persona ya es miembro de este negocio.',
        'role_updated' => 'El rol de :name ha sido actualizado.',
        'removed' => ':name ha sido eliminado del negocio.',
        'invite_revoked' => 'Invitación revocada.',
        'left' => 'Has salido de :business.',
        'last_owner_leave' => 'No puedes salir porque eres el único propietario. Transfiere la propiedad primero.',
    ],

    'invitations' => [
        'accepted' => '¡Bienvenido a :business!',
        'declined' => 'Invitación rechazada.',
        'invalid_link' => 'Este enlace de invitación no es válido o ha expirado.',
        'wrong_account' => 'Esta invitación es para una cuenta diferente. Por favor inicia sesión con la cuenta correcta.',
    ],

    'business' => [
        'switched' => 'Cambiado a :name.',
    ],

    'impersonation' => [
        'started' => 'Ahora estás viendo la aplicación como :name.',
        'stopped' => 'Has vuelto a tu cuenta de administrador.',
    ],

    'magic_login' => [
        'invalid' => 'Este enlace de acceso no es válido, ha expirado o ya se ha utilizado.',
        'signed_in' => 'Has iniciado sesión.',
    ],
];
