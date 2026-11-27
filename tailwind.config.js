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
                // Themed tokens are CSS variables (RGB channels, see app.css)
                // so the menu can turn from matcha green to coffee brown.
                brand: 'rgb(var(--yc-brand) / <alpha-value>)', // Matcha Sage / Dark Roast
                cta: '#C2783B', // Roasted Maple Amber
                oat: 'rgb(var(--yc-oat) / <alpha-value>)', // Warm Oat Milk
                surface: '#FFFFFF', // Pure White Cream
                charcoal: 'rgb(var(--yc-charcoal) / <alpha-value>)', // Deep Roast Charcoal
                // Supporting tokens from the Figma variables.
                'sage-soft': 'rgb(var(--yc-sage-soft) / <alpha-value>)',
                'amber-soft': '#F4E1CE',
                'amber-text': '#9A5A25',
                'oat-deep': 'rgb(var(--yc-oat-deep) / <alpha-value>)',
                muted: 'rgb(var(--yc-muted) / <alpha-value>)',
                line: 'rgb(var(--yc-line) / <alpha-value>)',
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
