import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/js/app.tsx',
            output: {
                entryFileNames: 'assets/app.js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: (asset) => asset.names?.some((name) => name.endsWith('.css')) ? 'assets/app.css' : 'assets/[name][extname]',
            },
        },
    },
    server: { cors: true, strictPort: true },
});
