import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

function read(relativePath) {
  return readFileSync(resolve(process.cwd(), relativePath), 'utf8')
}

test('SettingsView does not prefix /api for http profile endpoints', () => {
  const content = read('src/views/SettingsView.vue')
  assert.equal(content.includes("http.patch('/api/profile'"), false)
  assert.equal(content.includes("http.patch('/api/profile/password'"), false)
  assert.equal(content.includes("http.delete('/api/profile'"), false)
})

test('ProfileEdit does not prefix /api for http profile endpoint', () => {
  const content = read('src/views/ProfileEdit.vue')
  assert.equal(content.includes("http.patch('/api/profile'"), false)
})
