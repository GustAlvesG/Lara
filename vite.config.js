import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/sass/app.scss',
                'resources/js/app.js',
                // Tablet dos freelancers (/kiosk): página própria, fora do layout.
                'resources/css/kiosk.css',
                'resources/js/kiosk.js',
                // Tablet de assinatura (/assinatura/kiosk): só o CSS; o JS fica no Blade.
                'resources/css/quiosque.css',
                // Tablet da Gerência (/assinatura/kiosk/gerencia).
                'resources/css/quiosque-gerencia.css',
                'resources/js/quiosque-gerencia.js',
            ],
            refresh: true,
        }),
    ],
});