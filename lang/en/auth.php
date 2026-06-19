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

    'failed' => 'These credentials do not match our records.',
    'frozen' => 'This account has been frozen. Please contact support.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'login' => [
        'meta_title' => 'Log in',
        'email_label' => 'Email',
        'password_label' => 'Password',
        'remember_me' => 'Remember me',
        'forgot_password' => 'Forgot password?',
        'submit' => 'Log in',
        'no_account' => "Don't have an account?",
        'create_one' => 'Create one',
    ],

    'register' => [
        'meta_title' => 'Register',
        'name_label' => 'Name',
        'email_label' => 'Email',
        'password_label' => 'Password',
        'password_confirmation_label' => 'Confirm Password',
        'already_registered' => 'Already registered?',
        'submit' => 'Register',
    ],

    'forgot_password' => [
        'meta_title' => 'Forgot Password',
        'lead' => "Enter your email address and we'll send you a link to reset your password.",
        'email_label' => 'Email',
        'submit' => 'Send reset link',
        'remember_question' => 'Remember your password?',
        'log_in' => 'Log in',
    ],

    'reset_password' => [
        'meta_title' => 'Reset Password',
        'email_label' => 'Email',
        'password_label' => 'Password',
        'password_confirmation_label' => 'Confirm Password',
        'submit' => 'Reset Password',
    ],

    'verify_code' => [
        'meta_title' => 'Verify your email',
        'heading' => 'Check your email',
        'lead_before' => 'We sent a 6-digit code to',
        'lead_after' => '. Enter it below to verify your account.',
        'submit' => 'Verify account',
        'submitting' => 'Verifying…',
        'not_received' => "Didn't receive it?",
        'resend' => 'Resend code',
        'resend_cooldown' => 'Resend in :seconds s',
    ],

    'verify_email' => [
        'meta_title' => 'Email Verification',
        'lead' => "Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.",
        'link_sent' => 'A new verification link has been sent to the email address you provided during registration.',
        'submit' => 'Resend Verification Email',
        'log_out' => 'Log Out',
    ],

    'confirm_password' => [
        'meta_title' => 'Confirm Password',
        'lead' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'password_label' => 'Password',
        'submit' => 'Confirm',
    ],

];
