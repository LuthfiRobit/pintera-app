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
                // token lama — jangan dihapus, masih dipakai halaman yang belum di-migrasi
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

                // token baru — redesign TailAdmin (lihat docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md)
                brand: {
                    50: '#ECF3FF',
                    100: '#DDE9FF',
                    300: '#9CB9FF',
                    500: '#465FFF',
                    600: '#3641F5',
                },
                portal: {
                    50: '#E7EEF5',
                    500: '#1E3A5F',
                    600: '#16324F',
                },
                gray: {
                    50: '#F9FAFB',
                    100: '#F2F4F7',
                    200: '#E4E7EC',
                    300: '#D0D5DD',
                    400: '#98A2B3',
                    500: '#667085',
                    600: '#475467',
                    700: '#344054',
                    800: '#1D2939',
                    900: '#101828',
                },
                success: {
                    50: '#ECFDF3',
                    500: '#12B76A',
                    600: '#039855',
                    700: '#027A48',
                },
                error: {
                    50: '#FEF3F2',
                    500: '#F04438',
                    600: '#D92D20',
                    700: '#B42318',
                },
                warning: {
                    50: '#FFFAEB',
                    500: '#F79009',
                    600: '#DC6803',
                    700: '#B54708',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0px 1px 3px 0px rgba(16, 24, 40, 0.10)',
                elevated: '0 12px 28px rgba(16, 24, 40, 0.16)',
                panel: '0 24px 48px rgba(16, 30, 50, 0.24)',
            },
        },
    },

    plugins: [forms],
};
