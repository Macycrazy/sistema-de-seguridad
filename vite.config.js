import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Fijado a IPv4 a propósito. Por defecto Vite escucha en [::1], y entonces las
        // etiquetas que Laravel escribe en la página apuntan a «http://[::1]:5173». Eso
        // rompe la prueba que comprueba que ninguna pantalla pide nada fuera del propio
        // servidor —porque «[::1]» no es el host de APP_URL—, y hace que la aplicación se
        // vea sin estilos si se abre por 127.0.0.1 en vez de por localhost.
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
