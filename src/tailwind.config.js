import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                accent: {
                    DEFAULT: 'var(--accent)',
                    hover: 'var(--accent-hover)',
                    subtle: 'var(--accent-subtle)',
                },
                danger: 'var(--danger)',
                success: 'var(--success)',
                warning: 'var(--warning)',
            },
            backgroundColor: {
                base: 'var(--bg)',
                subtle: 'var(--bg-subtle)',
                muted: 'var(--bg-muted)',
            },
            textColor: {
                primary: 'var(--text)',
                secondary: 'var(--text-secondary)',
                muted: 'var(--text-muted)',
            },
            borderColor: {
                DEFAULT: 'var(--border)',
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
            },
            maxWidth: {
                content: '1400px',
                form: '560px',
                auth: '400px',
            },
        },
    },
    plugins: [],
};
