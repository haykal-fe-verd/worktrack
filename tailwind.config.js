import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                pln: {
                    blue: '#00AFF0',
                    'blue-dark': '#0090C9',
                    navy: '#0B3B5C',
                    yellow: '#FFC107',
                    teal: '#00A2B9',
                },
            },
        },
    },

    plugins: [forms],
};
