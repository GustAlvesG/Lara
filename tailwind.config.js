import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Token definido em resources/css/app.css como canais RGB; o <alpha-value>
// deixa `bg-grena/20` e afins funcionarem.
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

const AREAS = ['portaria', 'reservas', 'externos', 'freela', 'placar', 'lara', 'info', 'compras', 'cartao', 'inicio'];

const areaColors = Object.fromEntries(
    AREAS.flatMap((area) => [
        [area, token(`a-${area}`)],
        [`${area}-ink`, token(`a-${area}-ink`)],
    ])
);

/** @type {import('tailwindcss').Config} */
export default {
    // O tema escuro segue a classe `dark` no <html>, que o script do tema liga
    // pela escolha da pessoa ou, sem escolha, pelo sistema — ver
    // resources/views/partials/theme-script.blade.php.
    darkMode: 'selector',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Scripts antigos que trocam classes em tempo de execução.
        './public/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Unbounded', '"Arial Rounded MT Bold"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                canvas: token('canvas'),
                surface: token('surface'),
                subtle: token('subtle'),
                line: {
                    DEFAULT: token('line'),
                    strong: token('line-strong'),
                },
                ink: {
                    DEFAULT: token('ink'),
                    2: token('ink-2'),
                    3: token('ink-3'),
                },
                grena: {
                    DEFAULT: token('grena'),
                    hover: token('grena-hover'),
                    ink: token('grena-ink'),
                    tint: token('grena-tint'),
                },
                carmim: token('carmim'),
                star: token('star'),
                ok: { DEFAULT: token('ok'), soft: token('ok-soft') },
                warn: { DEFAULT: token('warn'), soft: token('warn-soft') },
                danger: { DEFAULT: token('danger'), soft: token('danger-soft') },
                area: areaColors,
                'plate-band': token('plate-band'),
            },
            borderRadius: {
                card: '1.125rem',
            },
            boxShadow: {
                card: 'var(--shadow-card)',
                pop: 'var(--shadow-pop)',
            },
        },
    },

    plugins: [forms],
};
