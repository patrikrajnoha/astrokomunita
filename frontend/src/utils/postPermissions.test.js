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

test('delete visibility follows owner/admin rules', () => {
  const owner = { id: 10, is_admin: false }
  const admin = { id: 99, is_admin: true }
  const stranger = { id: 12, is_admin: false }
  const post = { id: 1, user_id: 10 }

  assert.equal(canDeletePost(post, owner), true)
  assert.equal(canDeletePost(post, admin), true)
  assert.equal(canDeletePost(post, stranger), false)
})
