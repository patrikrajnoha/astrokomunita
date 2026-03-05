import { execSync } from 'node:child_process'
import { existsSync, readFileSync, statSync } from 'node:fs'
import path from 'node:path'

const endpoint = process.argv[2] || 'http://127.0.0.1:5174/__build-fingerprint'

function readLocalCommit() {
  try {
    return execSync('git rev-parse --short=12 HEAD', {
      cwd: '..',
      stdio: ['ignore', 'pipe', 'ignore'],
      encoding: 'utf8',
    }).trim()
  } catch {
    return readCommitFromGitDirCandidates()
  }
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
    const lines = readFileSync(packedRefsPath, 'utf8').split(/\r?\n/)
    for (const line of lines) {
      if (!line || line.startsWith('#') || line.startsWith('^')) continue
      const [hash, ref] = line.split(' ')
      if (hash && ref === refName) return hash.trim()
    }
  } catch {
    return null
  }

  return null
}

function readCommitFromGitDir(gitDir) {
  try {
    const headPath = path.join(gitDir, 'HEAD')
    if (!existsSync(headPath)) return null

    const headValue = readFileSync(headPath, 'utf8').trim()
    if (!headValue) return null
    if (!headValue.startsWith('ref:')) return headValue.slice(0, 12)

    const refName = headValue.slice('ref:'.length).trim()
    const refPath = path.join(gitDir, ...refName.replace(/\\/g, '/').split('/'))

    if (existsSync(refPath)) {
      return readFileSync(refPath, 'utf8').trim().slice(0, 12)
    }

    const packed = readPackedRef(gitDir, refName)
    return packed ? packed.slice(0, 12) : null
  } catch {
    return null
  }
}

function readCommitFromGitDirCandidates() {
  const candidates = [
    process.env.GIT_DIR,
    path.resolve('..', '.git'),
    '/workspace/.git',
  ]

  for (const candidate of candidates) {
    const gitDir = resolveGitDir(candidate)
    if (!gitDir) continue

    const commit = readCommitFromGitDir(gitDir)
    if (commit) return commit
  }

  return 'unknown'
}

async function readServerFingerprint(url) {
  const response = await fetch(url, {
    headers: {
      'cache-control': 'no-cache',
    },
  })

  if (!response.ok) {
    throw new Error(`Fingerprint endpoint returned HTTP ${response.status}`)
  }

  return response.json()
}

function normalize(value) {
  return String(value || '').trim().toLowerCase()
}

async function main() {
  const localCommit = readLocalCommit()
  const server = await readServerFingerprint(endpoint)
  const serverCommit = normalize(server.gitCommit)
  const localCommitNormalized = normalize(localCommit)

  console.log(`[fingerprint] endpoint: ${endpoint}`)
  console.log(`[fingerprint] server: commit=${server.gitCommit} buildTime=${server.buildTime} mode=${server.mode}`)
  console.log(`[fingerprint] local: commit=${localCommit}`)

  if (serverCommit === 'unknown') {
    throw new Error('Server fingerprint commit is unknown. Check docker-compose .git mount for frontend.')
  }

  if (localCommitNormalized === 'unknown') {
    throw new Error('Local commit is unknown. Ensure this is a git checkout.')
  }

  if (serverCommit !== localCommitNormalized) {
    throw new Error(
      `Fingerprint mismatch. Server commit ${server.gitCommit} does not match local commit ${localCommit}.`
    )
  }

  console.log('[fingerprint] OK: frontend is serving current commit.')
}

main().catch((error) => {
  console.error(`[fingerprint] ERROR: ${error.message}`)
  process.exit(1)
})
