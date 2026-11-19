import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                siger: {
                    50: '#f3f6f8',
                    100: '#e5ebf0',
                    200: '#cbd7e1',
                    300: '#a5bccd',
                    400: '#799cb6',
                    500: '#59809d',
                    600: '#446681',
                    700: '#38536b',
                    800: '#31475a',
                    900: '#294458',
                },
            },
        },
    },

    plugins: [forms, typography],
};
