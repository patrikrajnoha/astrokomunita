import { fileURLToPath, URL } from 'node:url'
import { existsSync } from 'node:fs'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

const isDockerRuntime = existsSync('/.dockerenv')
const backendProxyTarget = process.env.VITE_BACKEND_PROXY_TARGET
  || (isDockerRuntime ? 'http://backend:8001' : 'http://127.0.0.1:8001')

const allowedDevOrigins = [/^http:\/\/127\.0\.0\.1(?::\d+)?$/, /^http:\/\/localhost(?::\d+)?$/]

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
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          framework: ['vue', 'vue-router', 'pinia'],
          network: ['axios', 'laravel-echo'],
        },
      },
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5174,
    strictPort: true,
    cors: {
      origin: allowedDevOrigins,
      credentials: true,
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
      '/storage': {
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