export function canUseNotificationFeatures({ auth, preferences } = {}) {
  const userId = Number(auth?.user?.id || 0)

  if (!auth?.bootstrapDone || !auth?.isAuthed || !Number.isInteger(userId) || userId <= 0) {
    return false
  }

  if (!auth?.isAdmin && !auth?.user?.email_verified_at) {
    return false
  }

  if (auth?.isAdmin) {
    return true
  }

  if (!preferences?.loaded || preferences?.loading) {
    return false
  }

  return Boolean(preferences?.isOnboardingCompleted)
}
