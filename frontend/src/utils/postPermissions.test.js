import test from 'node:test'
import assert from 'node:assert/strict'
import { canDeletePost, canReportPost, isOwner } from './postPermissions.js'

test('owner cannot report own post', () => {
  const user = { id: 10, is_admin: false }
  const post = { id: 1, user_id: 10 }

  assert.equal(isOwner(post, user), true)
  assert.equal(canReportPost(post, user), false)
})

test('non-owner can report post', () => {
  const user = { id: 99, is_admin: false }
  const post = { id: 1, user_id: 10 }

  assert.equal(canReportPost(post, user), true)
})

test('guest can see report action', () => {
  const post = { id: 1, user_id: 10 }
  assert.equal(canReportPost(post, null), true)
})

test('astrobot post cannot be reported by logged user', () => {
  const user = { id: 99, is_admin: false }
  const post = { id: 1, user_id: 10, source_name: 'astrobot' }
  assert.equal(canReportPost(post, user), false)
})

test('astrobot post cannot be reported by guest', () => {
  const post = { id: 1, user_id: 10, source_name: 'nasa_rss' }
  assert.equal(canReportPost(post, null), false)
})

test('delete visibility follows owner/admin rules', () => {
  const owner = { id: 10, is_admin: false }
  const admin = { id: 99, is_admin: true }
  const stranger = { id: 12, is_admin: false }
  const post = { id: 1, user_id: 10 }

  assert.equal(canDeletePost(post, owner), true)
  assert.equal(canDeletePost(post, admin), true)
  assert.equal(canDeletePost(post, stranger), false)
})
