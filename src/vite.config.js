import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'fs';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        https: {
            key: fs.readFileSync('./certs/sso-vite.microservices.local-key.pem'),
            cert: fs.readFileSync('./certs/sso-vite.microservices.local.pem'),
        },
        host: 'sso-vite.microservices.local',
        port: 5173,
        strictPort: true,
        allowedHosts: ['all'],
        hmr: {
            host: 'sso-vite.microservices.local',
            protocol: 'wss',
            port: 5173,
        },
    },
});
