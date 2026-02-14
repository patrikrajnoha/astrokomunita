import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    ...(process.env.VITEST ? [] : [vueDevTools()]),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5173,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    },
    hmr: {
      overlay: false
    },
    // Fix for 431 Request Header Fields Too Large
    maxHeaderSize: 16384, // 16KB
    // Increase timeout if needed
    watch: {
      usePolling: false,
      interval: 100
    }
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.test.js'],
    exclude: ['node_modules/**', '**/tests-node/**'],
  },
})
