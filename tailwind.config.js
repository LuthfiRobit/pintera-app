import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                ink: '#0F2547',
                paper: '#F7F9FC',
                slate: '#5B6478',
                brass: '#C9A227',
                'signal-red': '#C81E3A',
                'signal-green': '#1E8F63',
                'signal-amber': '#C9820F',
                'spmb-primary': '#1E3A8A',
                'spmb-accent': '#2563EB',
                'spmb-bg': '#F1F2FA',
                'spmb-tint': '#EFF4FF',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 37, 71, 0.06), 0 1px 1px rgba(15, 37, 71, 0.04)',
                elevated: '0 12px 32px rgba(15, 37, 71, 0.14)',
            },
        },
    },

    plugins: [forms],
};
