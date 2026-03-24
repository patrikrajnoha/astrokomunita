import { ref } from 'vue'
import {
  botIdentity,
  canAdminEditBotPost as canAdminEditBotPostUtil,
  defaultBotVariant as defaultBotVariantUtil,
  isBotContentCollapsible as isBotContentCollapsibleUtil,
  resolvedBotVariant as resolvedBotVariantUtil,
  resolvedDisplayText as resolvedDisplayTextUtil,
  setBotContentVariant as setBotContentVariantUtil,
  showBotTranslationToggle,
} from './feedListBotContent.utils'
import {
  attachmentEntryUrl as attachmentEntryUrlUtil,
  attachmentSrc as attachmentSrcUtil,
  hasOriginalDownload,
  isAttachmentEntryImage,
  isImage,
  postGifUrl as postGifUrlUtil,
  sourceLink as sourceLinkUtil,
} from './feedListMedia.utils'

export function useFeedListPostDisplay({
  auth,
  apiBaseUrl,
  botContentPreviewLimit = 800,
  canDelete,
  canReport,
  isBotPost,
  downloadOriginalAttachment,
  openReport,
  confirmDelete,
  startInlineEdit,
  togglePin,
}) {
  const expandedPostIds = ref(new Set())
  const botContentVariantById = ref({})

  function canAdminEditBotPost(post) {
    return canAdminEditBotPostUtil(post, auth.user)
  }

  function canEditTranslatedVariant(post) {
    if (!canAdminEditBotPost(post)) return false
    if (!showBotTranslationToggle(post)) return true
    return (resolvedBotVariant(post) || defaultBotVariant(post)) === 'translated'
  }

  function sourceLink(post) {
    return sourceLinkUtil(post, apiBaseUrl)
  }

  function defaultBotVariant(post) {
    return defaultBotVariantUtil(post)
  }

  function resolvedBotVariant(post) {
    return resolvedBotVariantUtil(post, botContentVariantById.value)
  }

  function setBotContentVariant(post, variant) {
    botContentVariantById.value = setBotContentVariantUtil(post, variant, botContentVariantById.value)
  }

  function isBotVariantActive(post, variant) {
    return resolvedBotVariant(post) === variant
  }

  function resolvedDisplayText(post) {
    return resolvedDisplayTextUtil(post, botContentVariantById.value)
  }

  function isPostContentExpanded(post) {
    return expandedPostIds.value.has(post?.id)
  }

  function isBotContentCollapsible(post) {
    return isBotContentCollapsibleUtil(post, botContentVariantById.value, botContentPreviewLimit)
  }

  function togglePostContent(post) {
    const id = post?.id
    if (!id) return

    const next = new Set(expandedPostIds.value)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    expandedPostIds.value = next
  }

  function displayPostContent(post) {
    const content = resolvedDisplayText(post)
    if (!isBotContentCollapsible(post) || isPostContentExpanded(post)) return content
    return content.slice(0, botContentPreviewLimit).trimEnd() + '...'
  }

  function isStelaPost(post) {
    return botIdentity(post) === 'stela'
  }

  function attachmentEntryUrl(entry) {
    return attachmentEntryUrlUtil(entry, apiBaseUrl)
  }

  function stelaPreviewImageSrc(post) {
    if (!isStelaPost(post)) return ''

    if (Array.isArray(post?.attachments)) {
      const imageEntry = post.attachments.find(
        (entry) => isAttachmentEntryImage(entry) && attachmentEntryUrl(entry),
      )
      if (imageEntry) return attachmentEntryUrl(imageEntry)
    }

    if (post?.attachment_url && isImage(post)) {
      return attachmentSrcUtil(post, apiBaseUrl)
    }

    return ''
  }

  function postGifUrl(post) {
    return postGifUrlUtil(post, apiBaseUrl)
  }

  function attachedEventForPost(post) {
    const event = post?.attached_event
    if (event && typeof event === 'object') {
      return event
    }

    const fallbackId = Number(post?.meta?.event?.event_id || 0)
    if (!Number.isInteger(fallbackId) || fallbackId <= 0) {
      return null
    }

    return {
      id: fallbackId,
      title: `Udalosť #${fallbackId}`,
      start_at: null,
      end_at: null,
    }
  }

  function menuItemsForPost(post) {
    const items = []

    if (showBotTranslationToggle(post)) {
      items.push({
        key: 'variant_translated',
        label: isBotVariantActive(post, 'translated') ? 'Jazyk: SK (aktívne)' : 'Jazyk: SK',
        danger: false,
        icon: 'lang_sk',
        active: isBotVariantActive(post, 'translated'),
      })
      items.push({
        key: 'variant_original',
        label: isBotVariantActive(post, 'original') ? 'Jazyk: EN (aktívne)' : 'Jazyk: EN',
        danger: false,
        icon: 'lang_en',
        active: isBotVariantActive(post, 'original'),
      })
    }

    if (hasOriginalDownload(post)) {
      items.push({
        key: 'download_original',
        label: 'Stiahnu\u0165 v plnej kvalite',
        danger: false,
        icon: 'download',
      })
    }

    if (canReport(post)) {
      items.push({ key: 'report', label: 'Nahlásiť', danger: false, icon: 'report' })
    }

    if (canDelete(post)) {
      items.push({ key: 'delete', label: 'Zmazať', danger: true, icon: 'trash' })
    }

    if (canEditTranslatedVariant(post)) {
      items.push({ key: 'edit', label: 'Upraviť', danger: false, icon: 'edit' })
    }

    if (auth.user?.is_admin && !isBotPost(post)) {
      items.push({
        key: 'pin',
        label: post?.pinned_at ? 'Odopnúť' : 'Pripnúť',
        danger: false,
        icon: post?.pinned_at ? 'unpin' : 'pin',
      })
    }

    return items
  }

  function onMenuAction(item, post) {
    if (!item?.key || !post?.id) return

    if (item.key === 'variant_translated') {
      setBotContentVariant(post, 'translated')
      return
    }

    if (item.key === 'variant_original') {
      setBotContentVariant(post, 'original')
      return
    }

    if (item.key === 'download_original') {
      downloadOriginalAttachment(post)
      return
    }

    if (item.key === 'report') {
      openReport(post)
      return
    }

    if (item.key === 'delete') {
      void confirmDelete(post)
      return
    }

    if (item.key === 'edit') {
      startInlineEdit(post)
      return
    }

    if (item.key === 'pin') {
      togglePin(post)
    }
  }

  return {
    attachedEventForPost,
    canAdminEditBotPost,
    canEditTranslatedVariant,
    defaultBotVariant,
    displayPostContent,
    isBotContentCollapsible,
    isBotVariantActive,
    isPostContentExpanded,
    isStelaPost,
    menuItemsForPost,
    onMenuAction,
    postGifUrl,
    resolvedBotVariant,
    resolvedDisplayText,
    setBotContentVariant,
    sourceLink,
    stelaPreviewImageSrc,
    togglePostContent,
  }
}
