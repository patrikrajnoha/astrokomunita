import { fileURLToPath, URL } from 'node:url'
import { existsSync, readFileSync, statSync } from 'node:fs'
import path from 'node:path'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

function normalizePath(filePath) {
  return filePath.replace(/\\/g, '/')
}

function resolveGitDir(candidatePath) {
  if (!candidatePath || !existsSync(candidatePath)) return null

  try {
    const stats = statSync(candidatePath)
    if (stats.isDirectory()) return candidatePath

    if (!stats.isFile()) return null

    const pointer = readFileSync(candidatePath, 'utf8').trim()
    if (!pointer.startsWith('gitdir:')) return null

    const relativeGitDir = pointer.slice('gitdir:'.length).trim()
    return path.resolve(path.dirname(candidatePath), relativeGitDir)
  } catch {
    return null
  }
}

function readPackedRef(gitDir, refName) {
  const packedRefsPath = path.join(gitDir, 'packed-refs')
  if (!existsSync(packedRefsPath)) return null

  try {
    const packedRefs = readFileSync(packedRefsPath, 'utf8').split(/\r?\n/)
    for (const line of packedRefs) {
      if (!line || line.startsWith('#') || line.startsWith('^')) continue
      const [hash, ref] = line.split(' ')
      if (hash && ref === refName) return hash.trim()
    }
  } catch {
    return null
  }

  return null
}

function readGitHead(gitDir) {
  try {
    const headPath = path.join(gitDir, 'HEAD')
    if (!existsSync(headPath)) return null

    const headValue = readFileSync(headPath, 'utf8').trim()
    if (!headValue) return null

    if (!headValue.startsWith('ref:')) return headValue.slice(0, 12)

    const refName = headValue.slice('ref:'.length).trim()
    const refPath = path.join(gitDir, ...normalizePath(refName).split('/'))

    if (existsSync(refPath)) {
      return readFileSync(refPath, 'utf8').trim().slice(0, 12)
    }

    const packed = readPackedRef(gitDir, refName)
    return packed ? packed.slice(0, 12) : null
  } catch {
    return null
  }
}

function resolveGitCommit() {
  const candidates = [
    process.env.GIT_DIR,
    path.resolve(__dirname, '../.git'),
    '/workspace/.git',
  ]

  for (const candidate of candidates) {
    const gitDir = resolveGitDir(candidate)
    if (!gitDir) continue

    const commit = readGitHead(gitDir)
    if (commit) return commit
  }

  return 'unknown'
}

function createBuildFingerprint(mode) {
  return {
    gitCommit: resolveGitCommit(),
    buildTime: new Date().toISOString(),
    mode,
  }
}

function fingerprintMiddleware(fingerprint) {
  return (_req, res) => {
    res.statusCode = 200
    res.setHeader('Content-Type', 'application/json; charset=utf-8')
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
    res.end(JSON.stringify(fingerprint))
  }
}

function buildFingerprintPlugin(fingerprint) {
  const middleware = fingerprintMiddleware(fingerprint)

  return {
    name: 'build-fingerprint',
    configureServer(server) {
      server.middlewares.use('/__build-fingerprint', middleware)
    },
    configurePreviewServer(server) {
      server.middlewares.use('/__build-fingerprint', middleware)
    },
  }
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const buildFingerprint = createBuildFingerprint(mode)

  return {
    define: {
      'import.meta.env.VITE_BUILD_COMMIT': JSON.stringify(buildFingerprint.gitCommit),
      'import.meta.env.VITE_BUILD_TIME': JSON.stringify(buildFingerprint.buildTime),
    },
    plugins: [
      vue(),
      buildFingerprintPlugin(buildFingerprint),
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
