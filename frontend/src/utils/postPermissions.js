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

function isBotAuthoredPost(post) {
  const sourceName = String(post?.source_name || '').trim().toLowerCase()
  const authorKind = String(post?.author_kind || '').trim().toLowerCase()
  const userRole = String(post?.user?.role || '').trim().toLowerCase()
  const postRole = String(post?.role || '').trim().toLowerCase()
  const botIdentity = String(post?.bot_identity || post?.meta?.bot_identity || '').trim().toLowerCase()

  if (Boolean(post?.user?.is_bot) || Boolean(post?.is_bot)) return true
  if (userRole === 'bot' || postRole === 'bot') return true
  if (authorKind === 'bot') return true
  if (botIdentity !== '') return true
  if (sourceName === 'astrobot' || sourceName === 'nasa_rss') return true

  return false
}

export function canReportPost(post, currentUser) {
  if (isBotAuthoredPost(post)) return false

  if (!currentUser) return true
  return !isOwner(post, currentUser)
}
