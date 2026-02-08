import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'fs';
import path from 'path';

const certKey = path.resolve(__dirname, 'certs/sso-vite.microservices.local-key.pem');
const certPem = path.resolve(__dirname, 'certs/sso-vite.microservices.local.pem');
const hasCerts = fs.existsSync(certKey) && fs.existsSync(certPem);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    ...(hasCerts && {
        server: {
            https: {
                key: fs.readFileSync(certKey),
                cert: fs.readFileSync(certPem),
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
    }),
});
