import { defineConfig } from 'vite';
import { resolve } from 'path';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  base: './',
  root: './src',
  publicDir: '../public',
  define: {
    'import.meta.env.VITE_WP_BASE_URL': JSON.stringify(process.env.VITE_WP_BASE_URL || ''),
  },
  build: {
    outDir: '../app',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/index.html'),
      },
    },
    target: 'es2015',
    minify: 'terser',
    sourcemap: true,
  },
  server: {
    port: 5173,
    host: true, // Allow external connections
    cors: true,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
      '@types': resolve(__dirname, './src/types'),
      '@utils': resolve(__dirname, './src/utils'),
      '@components': resolve(__dirname, './src/components'),
      '@services': resolve(__dirname, './src/services'),
    },
  },
});
