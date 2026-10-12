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
                sans: ['Inter', '"Noto Sans TC"', '"PingFang TC"', '"Microsoft JhengHei"', ...defaultTheme.fontFamily.sans],
            },
            // 小字級：原先專案使用但未定義 (無效)，補上並保持 ≥ 11px 的可讀性
            fontSize: {
                '3xs': ['0.6875rem', { lineHeight: '1rem' }],
                '2xs': ['0.75rem', { lineHeight: '1.125rem' }],
            },
            // 全站色彩：以重映射取代逐檔改色。indigo = 品牌主色 (群青)，gray = 偏冷的石墨中性色
            colors: {
                indigo: {
                    50: '#eef3ff',
                    100: '#dce6ff',
                    200: '#bfd0ff',
                    300: '#93aeff',
                    400: '#5f83fa',
                    500: '#3a5ef0',
                    600: '#2547d9',
                    700: '#1d38b0',
                    800: '#1b318c',
                    900: '#1a2c6e',
                    950: '#111b45',
                },
                gray: {
                    50: '#f7f8fa',
                    100: '#f0f2f5',
                    200: '#e4e7ec',
                    300: '#d0d5dd',
                    400: '#98a2b3',
                    500: '#667085',
                    600: '#475467',
                    700: '#344054',
                    750: '#262e40',
                    800: '#1c2333',
                    900: '#111726',
                    950: '#0a0e1a',
                },
            },
            borderRadius: {
                lg: '0.625rem',
                xl: '0.875rem',
                '2xl': '1.125rem',
            },
            boxShadow: {
                sm: '0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.05)',
                DEFAULT: '0 1px 3px 0 rgb(16 24 40 / 0.07), 0 1px 2px -1px rgb(16 24 40 / 0.05)',
                md: '0 4px 12px -2px rgb(16 24 40 / 0.08), 0 2px 4px -2px rgb(16 24 40 / 0.04)',
                lg: '0 12px 24px -6px rgb(16 24 40 / 0.12), 0 4px 8px -4px rgb(16 24 40 / 0.05)',
                xl: '0 24px 48px -12px rgb(16 24 40 / 0.18)',
            },
        },
    },

    plugins: [forms],
};
