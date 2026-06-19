<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | Top-level keys ('failed', 'password', 'throttle') are referenced by the
    | Laravel auth scaffolding (Auth\SessionGuard, password broker, etc.) and
    | must keep their names. UI strings live under nested keys below.
    |
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'frozen' => 'Esta cuenta ha sido congelada. Comunícate con soporte.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Inténtalo de nuevo en :seconds segundos.',

    'login' => [
        'meta_title' => 'Iniciar sesión',
        'email_label' => 'Correo electrónico',
        'password_label' => 'Contraseña',
        'remember_me' => 'Recordarme',
        'forgot_password' => '¿Olvidaste tu contraseña?',
        'submit' => 'Iniciar sesión',
        'no_account' => '¿No tienes cuenta?',
        'create_one' => 'Crear una',
    ],

    'register' => [
        'meta_title' => 'Registrarse',
        'name_label' => 'Nombre',
        'email_label' => 'Correo electrónico',
        'password_label' => 'Contraseña',
        'password_confirmation_label' => 'Confirmar contraseña',
        'already_registered' => '¿Ya tienes cuenta?',
        'submit' => 'Registrarse',
    ],

    'forgot_password' => [
        'meta_title' => 'Olvidé mi contraseña',
        'lead' => 'Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.',
        'email_label' => 'Correo electrónico',
        'submit' => 'Enviar enlace',
        'remember_question' => '¿Recordaste tu contraseña?',
        'log_in' => 'Iniciar sesión',
    ],

    'reset_password' => [
        'meta_title' => 'Restablecer contraseña',
        'email_label' => 'Correo electrónico',
        'password_label' => 'Contraseña',
        'password_confirmation_label' => 'Confirmar contraseña',
        'submit' => 'Restablecer contraseña',
    ],

    'verify_code' => [
        'meta_title' => 'Verifica tu correo',
        'heading' => 'Revisa tu correo',
        'lead_before' => 'Enviamos un código de 6 dígitos a',
        'lead_after' => '. Ingrésalo aquí para verificar tu cuenta.',
        'submit' => 'Verificar cuenta',
        'submitting' => 'Verificando…',
        'not_received' => '¿No lo recibiste?',
        'resend' => 'Reenviar código',
        'resend_cooldown' => 'Reenviar en :seconds s',
    ],

    'verify_email' => [
        'meta_title' => 'Verificación de correo',
        'lead' => '¡Gracias por registrarte! Antes de comenzar, verifica tu correo electrónico haciendo clic en el enlace que te enviamos. Si no recibiste el correo, te enviaremos otro con gusto.',
        'link_sent' => 'Se ha enviado un nuevo enlace de verificación a la dirección de correo que proporcionaste durante el registro.',
        'submit' => 'Reenviar correo de verificación',
        'log_out' => 'Cerrar sesión',
    ],

    'confirm_password' => [
        'meta_title' => 'Confirmar contraseña',
        'lead' => 'Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar.',
        'password_label' => 'Contraseña',
        'submit' => 'Confirmar',
    ],

];
