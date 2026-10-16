import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        outDir: fileURLToPath(new URL('./dist', import.meta.url)),
        emptyOutDir: true,
        rollupOptions: {
            input: fileURLToPath(new URL('./resources/css/filament-kanban.css', import.meta.url)),
            output: {
                assetFileNames: '[name][extname]',
            },
        },
    },
});
