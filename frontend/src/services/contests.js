import api from '@/services/api'

export async function getActiveContests() {
  const response = await api.get('/contests/active')
  return response.data
}

export async function getContestParticipants(contestId, limit = 100) {
  const response = await api.get(`/contests/${contestId}/participants`, {
    params: { limit },
  })

  return response.data
}
