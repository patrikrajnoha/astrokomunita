import { ref } from 'vue'

export function useFeedListInteractions({
  auth,
  api,
  bookmarks,
  currentFeed,
  loadFeed,
  canDelete,
  confirm,
  toastError,
  toastInfo,
  toastSuccess,
  attachmentDownloadSrc,
}) {
  const deleteLoadingId = ref(null)
  const likeLoadingIds = ref(new Set())
  const likeBumpId = ref(null)
  const pinLoadingId = ref(null)
  const shareTarget = ref(null)

  function isLikeLoading(post) {
    return likeLoadingIds.value.has(post?.id)
  }

  function setLikeLoading(id, on) {
    const next = new Set(likeLoadingIds.value)
    if (on) next.add(id)
    else next.delete(id)
    likeLoadingIds.value = next
  }

  function bumpLike(id) {
    likeBumpId.value = id
    window.setTimeout(() => {
      if (likeBumpId.value === id) likeBumpId.value = null
    }, 220)
  }

  function applyLikeResponse(post, res) {
    const data = res?.data
    if (!data || !post) return
    if (data.likes_count !== undefined) post.likes_count = data.likes_count
    if (data.liked_by_me !== undefined) post.liked_by_me = data.liked_by_me
  }

  function isBookmarkLoading(post) {
    return bookmarks.isLoading(post?.id)
  }

  async function toggleBookmark(post) {
    if (!post?.id || isBookmarkLoading(post)) return
    if (!auth.isAuthed) {
      currentFeed.value.err = 'Prihlas sa pre zalozky.'
      return
    }

    currentFeed.value.err = ''
    const prevBookmarked = !!post.is_bookmarked
    const prevBookmarkedAt = post.bookmarked_at || null
    const nextBookmarked = !prevBookmarked

    post.is_bookmarked = nextBookmarked
    post.bookmarked_at = nextBookmarked ? new Date().toISOString() : null
    bookmarks.setBookmarked(post.id, nextBookmarked)

    try {
      await auth.csrf()
      const state = await bookmarks.toggleBookmark(post.id, prevBookmarked)
      post.is_bookmarked = state
      post.bookmarked_at = state ? post.bookmarked_at || new Date().toISOString() : null
    } catch (e) {
      post.is_bookmarked = prevBookmarked
      post.bookmarked_at = prevBookmarkedAt
      bookmarks.setBookmarked(post.id, prevBookmarked)
      currentFeed.value.err = e?.response?.data?.message || 'Ulozenie zalozky zlyhalo.'
      toastError('Ulozenie zalozky zlyhalo.')
    }
  }

  async function toggleLike(post) {
    if (!post?.id || isLikeLoading(post)) return
    if (!auth.isAuthed) {
      currentFeed.value.err = 'Prihlas sa pre lajkovanie.'
      return
    }

    currentFeed.value.err = ''
    const prevLiked = !!post.liked_by_me
    const prevCount = Number(post.likes_count ?? 0) || 0

    post.liked_by_me = !prevLiked
    post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
    bumpLike(post.id)
    setLikeLoading(post.id, true)

    try {
      await auth.csrf()
      const res = prevLiked
        ? await api.delete(`/posts/${post.id}/like`)
        : await api.post(`/posts/${post.id}/like`)
      applyLikeResponse(post, res)
    } catch (e) {
      post.liked_by_me = prevLiked
      post.likes_count = prevCount
      const status = e?.response?.status
      if (status === 401) currentFeed.value.err = 'Prihlas sa.'
      else currentFeed.value.err = e?.response?.data?.message || 'Lajk zlyhal.'
    } finally {
      setLikeLoading(post.id, false)
    }
  }

  async function deletePost(post) {
    if (!post?.id || deleteLoadingId.value) return
    if (!canDelete(post)) return

    currentFeed.value.err = ''
    deleteLoadingId.value = post.id

    try {
      await auth.csrf()
      await api.delete(`/posts/${post.id}`)
      currentFeed.value.items = currentFeed.value.items.filter((x) => x.id !== post.id)
      toastSuccess('Príspevok bol zmazaný.')
    } catch (e) {
      const status = e?.response?.status
      if (status === 401) currentFeed.value.err = 'Prihlas sa.'
      else if (status === 403) currentFeed.value.err = 'Nemas opravnenie.'
      else currentFeed.value.err = e?.response?.data?.message || 'Mazanie zlyhalo.'
    } finally {
      deleteLoadingId.value = null
    }
  }

  async function confirmDelete(post) {
    if (!post?.id || !canDelete(post) || deleteLoadingId.value) return

    const approved = await confirm({
      title: 'Zmazať príspevok?',
      message: 'Túto akciu už nie je možné vrátiť.',
      confirmText: 'Zmazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })

    if (!approved) return
    await deletePost(post)
  }

  function downloadOriginalAttachment(post) {
    const url = attachmentDownloadSrc(post)
    if (!url) return

    toastInfo('S\u0165ahujem...')
    try {
      window.open(url, '_blank', 'noopener')
    } catch {
      toastError('Stiahnutie zlyhalo.')
    }
  }

  async function togglePin(post) {
    if (!post?.id || pinLoadingId.value) return
    if (!auth.user?.is_admin) {
      currentFeed.value.err = 'Akcia je dostupná len pre admina.'
      return
    }

    currentFeed.value.err = ''
    const wasPinned = !!post.pinned_at
    pinLoadingId.value = post.id

    try {
      await auth.csrf()
      if (wasPinned) {
        await api.patch(`/admin/posts/${post.id}/unpin`)
      } else {
        await api.patch(`/admin/posts/${post.id}/pin`)
      }

      if (wasPinned) {
        post.pinned_at = null
      } else {
        post.pinned_at = new Date().toISOString()
      }

      loadFeed(true)
      currentFeed.value.err = ''
      toastSuccess(wasPinned ? 'Príspevok bol odopnutý.' : 'Príspevok bol pripnutý.')
    } catch (e) {
      const status = e?.response?.status
      if (status === 401) currentFeed.value.err = 'Prihlas sa.'
      else if (status === 403) currentFeed.value.err = 'Nemáš oprávnenie.'
      else currentFeed.value.err = e?.response?.data?.message || 'Zmena pripnutia zlyhala.'
    } finally {
      pinLoadingId.value = null
    }
  }

  function openShareModal(post) {
    if (!post?.id) return
    shareTarget.value = post
  }

  function closeShareModal() {
    shareTarget.value = null
  }

  return {
    closeShareModal,
    confirmDelete,
    deleteLoadingId,
    downloadOriginalAttachment,
    isBookmarkLoading,
    isLikeLoading,
    likeBumpId,
    openShareModal,
    pinLoadingId,
    shareTarget,
    toggleBookmark,
    toggleLike,
    togglePin,
  }
}
