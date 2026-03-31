export function buildBasePostMenuItems({
  hasOriginalDownload = false,
  canReport = false,
  canDelete = false,
}) {
  const items = []

  if (hasOriginalDownload) {
    items.push({
      key: 'download_original',
      label: 'Stiahnuť v plnej kvalite',
      danger: false,
      icon: 'download',
    })
  }

  if (canReport) {
    items.push({
      key: 'report',
      label: 'Nahlásiť',
      danger: false,
      icon: 'report',
    })
  }

  if (canDelete) {
    items.push({
      key: 'delete',
      label: 'Zmazať',
      danger: true,
      icon: 'trash',
    })
  }

  return items
}

export function buildPinPostMenuItem(post) {
  if (!post?.id) return null

  return {
    key: 'pin',
    label: post?.pinned_at ? 'Odopnúť' : 'Pripnúť',
    danger: false,
    icon: post?.pinned_at ? 'unpin' : 'pin',
  }
}

export function handleCommonPostMenuAction(item, post, handlers = {}) {
  if (!item?.key || !post?.id) return false

  if (item.key === 'download_original') {
    handlers.downloadOriginalAttachment?.(post)
    return true
  }

  if (item.key === 'report') {
    handlers.openReport?.(post)
    return true
  }

  if (item.key === 'delete') {
    handlers.confirmDelete?.(post)
    return true
  }

  if (item.key === 'pin') {
    handlers.togglePin?.(post)
    return true
  }

  return false
}
