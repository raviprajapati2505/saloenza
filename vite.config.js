import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.jsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Use IPv4 on Windows so saloon-management.test can load dev assets reliably.
        host: '127.0.0.1',
        port: 5173,
        strictPort: false,
        hmr: {
            host: '127.0.0.1',
        },
    },
    resolve: {
        dedupe: [
            '@fullcalendar/core',
            '@fullcalendar/daygrid',
            '@fullcalendar/timegrid',
            '@fullcalendar/interaction',
            '@fullcalendar/react',
        ],
    },
});
