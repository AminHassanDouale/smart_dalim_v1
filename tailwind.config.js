/** @type {import('tailwindcss').Config} */
export default {
    // Remove the darkMode: 'class' line
    content: [
        "./resources/**/**/*.blade.php",
        "./resources/**/**/*.js",
        "./app/View/Components/**/*.php",
        "./app/Traits/**/*.php",
        "./app/Livewire/**/**/*.php",
        "./vendor/robsontenorio/mary/src/View/Components/**/*.php"
    ],
    theme: {
        extend: {
            fontFamily: {
                'arabic': ['Traditional Arabic', 'serif'],
            },
        },
    },
    safelist: [{
        pattern: /badge-|(bg-primary|bg-success|bg-info|bg-error|bg-warning|bg-neutral|bg-purple)/
    }],
    plugins: [require("daisyui")]
}
