import { computed, reactive, ref } from 'vue'
import api from '@/services/api'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  hashAvatarString,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import { normalizeAvatarUrl, resolveAvatarState } from '@/utils/avatar'
import { resolveUserCoverMedia } from '@/utils/profileMedia'
import { formatIconLabel, resolveMediaErrorMessage } from '../adminUserDetailView.utils'

const PROFILE_MEDIA_TARGET_MAX_BYTES = 3072 * 1024
const PROFILE_MEDIA_UPLOAD_MAX_BYTES = 20480 * 1024

function normalizeAvatarIndex(value, max) {
  const index = coerceAvatarIndex(value, max)
  return index === null ? null : index
}

function buildAvatarSnapshot(sourceUser) {
  const imageUrl = normalizeAvatarUrl(sourceUser?.avatar_url || sourceUser?.avatarUrl || '')

  return {
    mode: imageUrl ? 'image' : normalizeAvatarMode(sourceUser?.avatar_mode || sourceUser?.avatarMode),
    color: normalizeAvatarIndex(sourceUser?.avatar_color ?? sourceUser?.avatarColor, AVATAR_COLORS.length - 1),
    icon: normalizeAvatarIndex(sourceUser?.avatar_icon ?? sourceUser?.avatarIcon, AVATAR_ICONS.length - 1),
    seed: String(sourceUser?.avatar_seed || sourceUser?.avatarSeed || '').trim(),
  }
}

function applyAvatarSnapshot(draft, snapshot) {
  draft.mode = snapshot.mode
  draft.color = snapshot.color
  draft.icon = snapshot.icon
  draft.seed = snapshot.seed
}

function buildRandomAvatarSeed(sourceUser) {
  const base = `${sourceUser?.id || 'user'}:${Date.now()}:${Math.random()}`
  return `rnd-${hashAvatarString(base).toString(36)}`
}

export function useAdminUserBotMediaEditor({
  user,
  userLoading,
  canUploadBotMedia,
  updateUser,
  toast,
}) {
  const mediaError = ref('')
  const avatarErr = ref('')
  const avatarModalOpen = ref(false)
  const coverModalOpen = ref(false)
  const avatarSaving = ref(false)
  const avatarUploading = ref(false)
  const avatarRemoving = ref(false)
  const coverSaving = ref(false)
  const coverUploading = ref(false)
  const coverRemoving = ref(false)
  const avatarInput = ref(null)
  const coverInput = ref(null)
  const avatarPreview = ref('')
  const coverPreview = ref('')
  const avatarRemoveRequested = ref(false)
  const coverRemoveRequested = ref(false)
  const pendingAvatarFile = ref(null)
  const pendingCoverFile = ref(null)
  const avatarSnapshot = ref({
    mode: 'image',
    color: null,
    icon: null,
    seed: '',
  })
  const avatarDraft = reactive({
    mode: 'image',
    color: null,
    icon: null,
    seed: '',
  })

  const avatarSrc = computed(() =>
    avatarPreview.value || normalizeAvatarUrl(user.value?.avatar_url || user.value?.avatarUrl || ''),
  )
  const persistedAvatarMode = computed(() => {
    const persistedImage = normalizeAvatarUrl(user.value?.avatar_url || user.value?.avatarUrl || '')
    const hasImage = String(avatarPreview.value || persistedImage || '').trim() !== '' && !avatarRemoveRequested.value
    if (hasImage) return 'image'
    return normalizeAvatarMode(user.value?.avatar_mode || user.value?.avatarMode)
  })
  const avatarResolved = computed(() =>
    resolveAvatarState(user.value, {
      avatarUrl: avatarSrc.value,
      mode: avatarDraft.mode,
      colorIndex: avatarDraft.color,
      iconIndex: avatarDraft.icon,
      seed: avatarDraft.seed,
    }),
  )
  const iconOptions = computed(() =>
    AVATAR_ICONS.map((iconKey, index) => ({
      key: iconKey,
      index,
      label: formatIconLabel(iconKey),
    })),
  )
  const botCoverMedia = computed(() => resolveUserCoverMedia(user.value))
  const coverEditorMedia = computed(() => {
    if (coverPreview.value) {
      return {
        ...botCoverMedia.value,
        imageUrl: coverPreview.value,
        hasImage: true,
        isBotFallback: false,
      }
    }
    if (coverRemoveRequested.value) {
      return {
        ...botCoverMedia.value,
        imageUrl: '',
        hasImage: false,
        isBotFallback: true,
      }
    }

    return botCoverMedia.value
  })
  const mediaActionBusy = computed(() =>
    avatarSaving.value ||
    avatarUploading.value ||
    avatarRemoving.value ||
    coverSaving.value ||
    coverUploading.value ||
    coverRemoving.value ||
    userLoading.value,
  )

  function syncAvatarDraftFromUser() {
    const snapshot = buildAvatarSnapshot(user.value)
    avatarSnapshot.value = snapshot
    applyAvatarSnapshot(avatarDraft, snapshot)
  }

  function clearMediaPreview(type) {
    if (type === 'avatar') {
      if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value)
      }
      avatarPreview.value = ''
      return
    }

    if (coverPreview.value) {
      URL.revokeObjectURL(coverPreview.value)
    }
    coverPreview.value = ''
  }

  function setMediaPreview(type, file) {
    const previewUrl = URL.createObjectURL(file)
    if (type === 'avatar') {
      clearMediaPreview('avatar')
      avatarPreview.value = previewUrl
      return
    }

    clearMediaPreview('cover')
    coverPreview.value = previewUrl
  }

  function clearPendingMedia(type) {
    if (type === 'avatar') {
      pendingAvatarFile.value = null
      avatarRemoveRequested.value = false
      clearMediaPreview('avatar')
      return
    }

    pendingCoverFile.value = null
    coverRemoveRequested.value = false
    clearMediaPreview('cover')
  }

  function openBotMediaPicker(type) {
    if (!canUploadBotMedia.value || mediaActionBusy.value) return
    const input = type === 'avatar' ? avatarInput.value : coverInput.value
    if (input) {
      input.click()
    }
  }

  function openAvatarEditor() {
    if (!user.value || !canUploadBotMedia.value) return
    mediaError.value = ''
    avatarErr.value = ''
    clearPendingMedia('avatar')
    syncAvatarDraftFromUser()
    avatarModalOpen.value = true
  }

  function closeAvatarEditor() {
    avatarModalOpen.value = false
  }

  function openCoverEditor() {
    if (!user.value || !canUploadBotMedia.value) return
    mediaError.value = ''
    clearPendingMedia('cover')
    coverModalOpen.value = true
  }

  function closeCoverEditor() {
    coverModalOpen.value = false
  }

  function setAvatarMode(mode) {
    avatarDraft.mode = mode === 'generated' ? 'generated' : 'image'
    avatarErr.value = ''
    if (avatarDraft.mode === 'generated') {
      clearPendingMedia('avatar')
    }
  }

  function selectAvatarColor(index) {
    avatarDraft.color = normalizeAvatarIndex(index, AVATAR_COLORS.length - 1)
    avatarErr.value = ''
  }

  function selectAvatarIcon(index) {
    avatarDraft.icon = normalizeAvatarIndex(index, AVATAR_ICONS.length - 1)
    avatarErr.value = ''
  }

  function resetGeneratedAvatar() {
    avatarDraft.color = null
    avatarDraft.icon = null
    avatarDraft.seed = ''
    avatarErr.value = ''
  }

  function randomizeAvatar() {
    const seed = buildRandomAvatarSeed(user.value)
    avatarDraft.seed = seed
    avatarDraft.color = pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)
    avatarDraft.icon = pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)
  }

  function markAvatarImageForRemoval() {
    avatarRemoveRequested.value = true
    pendingAvatarFile.value = null
    clearMediaPreview('avatar')
  }

  function markCoverForRemoval() {
    coverRemoveRequested.value = true
    pendingCoverFile.value = null
    clearMediaPreview('cover')
  }

  async function compressProfileMedia(file) {
    let uploadFile = file
    try {
      uploadFile = await compressImageFileToMaxBytes(file, {
        maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
      })
    } catch {
      uploadFile = file
    }

    if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
      throw new Error('Vybraný obrázok je príliš veľký. Maximálna veľkosť je 20 MB.')
    }

    return uploadFile
  }

  async function onBotMediaChange(type, event) {
    const selectedFile = event?.target?.files?.[0]
    if (!selectedFile || !canUploadBotMedia.value) return
    event.target.value = ''

    mediaError.value = ''
    avatarErr.value = ''

    try {
      const uploadFile = await compressProfileMedia(selectedFile)
      setMediaPreview(type, uploadFile)
      if (type === 'avatar') {
        pendingAvatarFile.value = uploadFile
        avatarRemoveRequested.value = false
        avatarDraft.mode = 'image'
      } else {
        pendingCoverFile.value = uploadFile
        coverRemoveRequested.value = false
      }
    } catch (error) {
      const message = String(error?.message || 'Aktualizacia media zlyhala.')
      mediaError.value = message
      if (type === 'avatar') {
        avatarErr.value = message
      }
      toast.error(message)
    }
  }

  async function uploadBotMedia(type, file) {
    const form = new FormData()
    form.append('file', file)
    const response = await api.patch(`/admin/users/${user.value.id}/${type}`, form)
    updateUser(response.data)
  }

  async function saveAvatarPreferences() {
    if (!user.value || !canUploadBotMedia.value || avatarSaving.value) return

    avatarSaving.value = true
    mediaError.value = ''
    avatarErr.value = ''

    try {
      if (avatarRemoveRequested.value) {
        avatarRemoving.value = true
        const removeResponse = await api.delete(`/admin/users/${user.value.id}/avatar`)
        updateUser(removeResponse.data)
      }

      if (pendingAvatarFile.value) {
        avatarUploading.value = true
        await uploadBotMedia('avatar', pendingAvatarFile.value)
      }

      const payload = {
        avatar_mode: avatarDraft.mode,
        avatar_color: avatarDraft.color,
        avatar_icon: avatarDraft.icon,
        avatar_seed: avatarDraft.seed || null,
      }
      const response = await api.patch(`/admin/users/${user.value.id}/avatar/preferences`, payload)
      updateUser(response.data)

      syncAvatarDraftFromUser()
      clearPendingMedia('avatar')
      avatarModalOpen.value = false
      toast.success('Avatar bota bol aktualizovany.')
    } catch (error) {
      const message = resolveMediaErrorMessage(error, 'Aktualizacia avatara zlyhala.')
      avatarErr.value = message
      mediaError.value = message
      toast.error(message)
    } finally {
      avatarSaving.value = false
      avatarUploading.value = false
      avatarRemoving.value = false
    }
  }

  async function saveCoverEditor() {
    if (!user.value || !canUploadBotMedia.value || coverSaving.value) return

    coverSaving.value = true
    mediaError.value = ''

    try {
      if (coverRemoveRequested.value) {
        coverRemoving.value = true
        const removeResponse = await api.delete(`/admin/users/${user.value.id}/cover`)
        updateUser(removeResponse.data)
      } else if (pendingCoverFile.value) {
        coverUploading.value = true
        await uploadBotMedia('cover', pendingCoverFile.value)
      }

      clearPendingMedia('cover')
      coverModalOpen.value = false
      toast.success('Titulna fotka bota bola aktualizovana.')
    } catch (error) {
      const message = resolveMediaErrorMessage(error, 'Aktualizacia titulnej fotky zlyhala.')
      mediaError.value = message
      toast.error(message)
    } finally {
      coverSaving.value = false
      coverUploading.value = false
      coverRemoving.value = false
    }
  }

  function handleAvatarModalToggle(isOpen, wasOpen) {
    if (!isOpen && wasOpen) {
      syncAvatarDraftFromUser()
      clearPendingMedia('avatar')
      avatarErr.value = ''
    }
  }

  function handleCoverModalToggle(isOpen, wasOpen) {
    if (!isOpen && wasOpen) {
      clearPendingMedia('cover')
      mediaError.value = ''
    }
  }

  function resetBotMediaEditorState() {
    mediaError.value = ''
    avatarErr.value = ''
    avatarModalOpen.value = false
    coverModalOpen.value = false
    clearPendingMedia('avatar')
    clearPendingMedia('cover')
  }

  function cleanupBotMediaEditor() {
    clearMediaPreview('avatar')
    clearMediaPreview('cover')
  }

  return {
    avatarDraft,
    avatarErr,
    avatarInput,
    avatarModalOpen,
    avatarRemoving,
    avatarResolved,
    avatarSaving,
    avatarSrc,
    avatarUploading,
    botCoverMedia,
    clearPendingMedia,
    cleanupBotMediaEditor,
    closeAvatarEditor,
    closeCoverEditor,
    coverEditorMedia,
    coverInput,
    coverModalOpen,
    coverRemoving,
    coverSaving,
    coverUploading,
    handleAvatarModalToggle,
    handleCoverModalToggle,
    iconOptions,
    markAvatarImageForRemoval,
    markCoverForRemoval,
    mediaActionBusy,
    mediaError,
    onBotMediaChange,
    openAvatarEditor,
    openBotMediaPicker,
    openCoverEditor,
    persistedAvatarMode,
    randomizeAvatar,
    resetBotMediaEditorState,
    resetGeneratedAvatar,
    saveAvatarPreferences,
    saveCoverEditor,
    selectAvatarColor,
    selectAvatarIcon,
    setAvatarMode,
    syncAvatarDraftFromUser,
  }
}
