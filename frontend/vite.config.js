import { fileURLToPath, URL } from 'node:url'
import { existsSync } from 'node:fs'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

const isDockerRuntime = existsSync('/.dockerenv')
const allowedDevOrigins = [/^http:\/\/127\.0\.0\.1(?::\d+)?$/, /^http:\/\/localhost(?::\d+)?$/]

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const configuredProxyTarget = env.VITE_BACKEND_PROXY_TARGET
    || env.VITE_API_BASE_URL
    || env.VITE_API_URL
    || process.env.VITE_BACKEND_PROXY_TARGET
    || process.env.VITE_API_BASE_URL
    || process.env.VITE_API_URL
    || ''
  const normalizedProxyTarget = String(configuredProxyTarget)
    .trim()
    .replace(/\/api\/?$/i, '')
    .replace(/\/+$/, '')
  const dockerProxyTarget = 'http://backend:8001'
  const localProxyTarget = 'http://127.0.0.1:8001'
  const backendProxyTarget = normalizedProxyTarget || (isDockerRuntime ? dockerProxyTarget : localProxyTarget)
  const shouldSwapDockerLoopbackTarget = isDockerRuntime
    && /^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?$/i.test(backendProxyTarget)
  const effectiveBackendProxyTarget = shouldSwapDockerLoopbackTarget ? dockerProxyTarget : backendProxyTarget

  return {
    define: {
      __VITE_PROXY_TARGET__: JSON.stringify(effectiveBackendProxyTarget),
    },
    plugins: [
      vue(),
      ...(process.env.VITEST || env.VITE_ENABLE_DEVTOOLS !== 'true' ? [] : [vueDevTools()]),
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
          target: effectiveBackendProxyTarget,
          changeOrigin: true,
        },
        '/sanctum': {
          target: effectiveBackendProxyTarget,
          changeOrigin: true,
        },
        '/broadcasting': {
          target: effectiveBackendProxyTarget,
          changeOrigin: true,
        },
        '/storage': {
          target: effectiveBackendProxyTarget,
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
  }
})
