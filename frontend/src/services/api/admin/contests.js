import api from '@/services/api'

export function listContests(params = {}) {
  return api.get('/admin/contests', { params })
}

export function createContest(payload) {
  return api.post('/admin/contests', payload)
}

export function updateContest(contestId, payload) {
  return api.patch(`/admin/contests/${contestId}`, payload)
}

export function selectContestWinner(contestId, postId) {
  return api.post(`/admin/contests/${contestId}/select-winner`, { post_id: postId })
}
