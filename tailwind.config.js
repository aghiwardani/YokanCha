import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: '#4A6B4F',
                cta: '#C2783B',
                oat: '#F8F6F0',
                surface: '#FFFFFF',
                charcoal: '#222521',
            },
            borderRadius: {
                brand: '16px',
            },
        },
    },
    plugins: [],
};
