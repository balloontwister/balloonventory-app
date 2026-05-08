import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                display: ['Inter Tight', 'Inter', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                'ink-primary':   'var(--color-ink-primary)',
                'ink-secondary': 'var(--color-ink-secondary)',
                'ink-tertiary':  'var(--color-ink-tertiary)',
                'surface':       'var(--color-surface)',
                'background':    'var(--color-background)',
                'border':        'var(--color-border)',
                'border-strong': 'var(--color-border-strong)',
                'accent':        'var(--color-accent)',
                'accent-hover':  'var(--color-accent-hover)',
                'accent-soft':   'var(--color-accent-soft)',
                'accent-on':     'var(--color-accent-on)',
                'success':       'var(--color-success)',
                'success-soft':  'var(--color-success-soft)',
                'warning':       'var(--color-warning)',
                'warning-soft':  'var(--color-warning-soft)',
                'danger':        'var(--color-danger)',
                'danger-soft':   'var(--color-danger-soft)',
            },
            borderRadius: {
                'sm':   '6px',
                'md':   '10px',
                'lg':   '14px',
                'pill': '9999px',
            },
            boxShadow: {
                'pop':   '0 4px 12px rgba(0,0,0,0.08)',
                'modal': '0 20px 50px rgba(0,0,0,0.18)',
            },
            letterSpacing: {
                'display-xl': '-0.025em',
                'h1':         '-0.02em',
                'h2':         '-0.015em',
                'h3':         '-0.01em',
                'eyebrow':    '0.08em',
            },
        },
    },

    plugins: [forms],
};
