export function isOwner(post, currentUser) {
  const postUserId = Number(post?.user_id)
  const currentUserId = Number(currentUser?.id)
  if (!postUserId || !currentUserId) return false
  return postUserId === currentUserId
}

export function canDeletePost(post, currentUser) {
  if (!currentUser) return false
  if (isOwner(post, currentUser)) return true
  return Boolean(currentUser.is_admin || currentUser.role === 'admin')
}

export function canReportPost(post, currentUser) {
  const sourceName = String(post?.source_name || '').toLowerCase()
  if (sourceName === 'astrobot' || sourceName === 'nasa_rss') return false

  if (!currentUser) return true
  return !isOwner(post, currentUser)
}
