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
            // Figma "Tokens & Komponen": Gloock for editorial headings,
            // Plus Jakarta Sans for body, DM Mono for labels and small numbers.
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Gloock', ...defaultTheme.fontFamily.serif],
                mono: ['"DM Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Moku palette (60 : 30 : 10).
                brand: '#4A6B4F', // Matcha Sage
                cta: '#C2783B', // Roasted Maple Amber
                oat: '#F8F6F0', // Warm Oat Milk
                surface: '#FFFFFF', // Pure White Cream
                charcoal: '#222521', // Deep Roast Charcoal
                // Supporting tokens from the Figma variables.
                'sage-soft': '#E1E9DE',
                'amber-soft': '#F4E1CE',
                'amber-text': '#9A5A25',
                'oat-deep': '#EFEADC',
                muted: '#5B5F56',
                line: '#E2DDCF',
            },
            borderRadius: {
                brand: '16px',
                card: '24px',
                pill: '22px',
                chip: '14px',
                tag: '12px',
            },
        },
    },
    plugins: [],
};
