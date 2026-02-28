import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

const backendProxyTarget = process.env.VITE_BACKEND_PROXY_TARGET || 'http://backend:8001'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    ...(process.env.VITEST || process.env.VITE_ENABLE_DEVTOOLS !== 'true' ? [] : [vueDevTools()]),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5174,
    strictPort: true,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    },
    hmr: {
      overlay: false
    },
    proxy: {
      '/api': {
        target: backendProxyTarget,
        changeOrigin: true,
      },
      '/sanctum': {
        target: backendProxyTarget,
        changeOrigin: true,
      },
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
