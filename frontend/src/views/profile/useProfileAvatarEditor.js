import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { normalizeAvatarUrl, resolveAvatarState } from '@/utils/avatar'
import { avatarDebug } from '@/utils/avatarDebug'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  hashAvatarString,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'
import { extractFirstError } from '../profileView.utils'

const PROFILE_MEDIA_TARGET_MAX_BYTES = 3072 * 1024
const PROFILE_MEDIA_UPLOAD_MAX_BYTES = 20480 * 1024

function normalizeAvatarIndex(value, max) {
  const index = coerceAvatarIndex(value, max)
  return index === null ? null : index
}

function buildAvatarSnapshot(user) {
  const imageUrl = normalizeAvatarUrl(user?.avatar_url || user?.avatarUrl || '')

  return {
    mode: imageUrl ? 'image' : normalizeAvatarMode(user?.avatar_mode || user?.avatarMode),
    color: normalizeAvatarIndex(user?.avatar_color ?? user?.avatarColor, AVATAR_COLORS.length - 1),
    icon: normalizeAvatarIndex(user?.avatar_icon ?? user?.avatarIcon, AVATAR_ICONS.length - 1),
    seed: String(user?.avatar_seed || user?.avatarSeed || '').trim(),
  }
}

function applyAvatarSnapshot(draft, snapshot) {
  draft.mode = snapshot.mode
  draft.color = snapshot.color
  draft.icon = snapshot.icon
  draft.seed = snapshot.seed
}

function formatIconLabel(iconKey) {
  const map = {
    planet: 'Planeta',
    star: 'Hviezda',
    comet: 'Kometa',
    constellation: 'Suhvezdie',
    moon: 'Mesiac',
  }
  return map[iconKey] || iconKey
}

function buildRandomAvatarSeed(userId) {
  const base = `${userId || 'user'}:${Date.now()}:${Math.random()}`
  return `rnd-${hashAvatarString(base).toString(36)}`
}

export function useProfileAvatarEditor({ auth, confirm, http, toast }) {
  const mediaErr = ref('')
  const avatarUploading = ref(false)
  const avatarRemoving = ref(false)
  const avatarSaving = ref(false)
  const avatarErr = ref('')
  const avatarModalOpen = ref(false)
  const coverUploading = ref(false)
  const coverLoadFailed = ref(false)
  const avatarPreview = ref('')
  const coverPreview = ref('')
  const avatarInput = ref(null)
  const coverInput = ref(null)
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
    avatarPreview.value || normalizeAvatarUrl(auth.user?.avatar_url || auth.user?.avatarUrl || '')
  )
  const coverSrc = computed(() =>
    coverPreview.value || normalizeAvatarUrl(auth.user?.cover_url || auth.user?.coverUrl || '')
  )
  const avatarResolved = computed(() =>
    resolveAvatarState(auth.user, {
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

  function logAvatarProfileState(scope, extra = {}) {
    avatarDebug(`ProfileView:${scope}`, {
      userId: auth.user?.id ?? null,
      username: auth.user?.username ?? null,
      persisted: auth.user
        ? {
            avatar_mode: auth.user?.avatar_mode ?? auth.user?.avatarMode ?? null,
            avatar_path: auth.user?.avatar_path ?? null,
            avatar_url: auth.user?.avatar_url ?? auth.user?.avatarUrl ?? null,
            avatar_color: auth.user?.avatar_color ?? auth.user?.avatarColor ?? null,
            avatar_icon: auth.user?.avatar_icon ?? auth.user?.avatarIcon ?? null,
            avatar_seed: auth.user?.avatar_seed ?? auth.user?.avatarSeed ?? null,
          }
        : null,
      draft: {
        mode: avatarDraft?.mode ?? null,
        color: avatarDraft?.color ?? null,
        icon: avatarDraft?.icon ?? null,
        seed: avatarDraft?.seed ?? null,
      },
      avatarSrc: avatarSrc?.value ?? null,
      ...extra,
    })
  }

  function syncAvatarDraftFromUser() {
    const snapshot = buildAvatarSnapshot(auth.user)
    avatarSnapshot.value = snapshot
    applyAvatarSnapshot(avatarDraft, snapshot)
  }

  function openAvatarEditor() {
    if (!auth.user) return
    syncAvatarDraftFromUser()
    avatarErr.value = ''
    logAvatarProfileState('open-editor')
    avatarModalOpen.value = true
  }

  function setAvatarMode(mode) {
    avatarDraft.mode = mode === 'generated' ? 'generated' : 'image'
    avatarErr.value = ''
    logAvatarProfileState('set-mode', { nextMode: avatarDraft.mode })
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

  async function saveAvatarPreferences(successMessage = 'Avatar ulozeny.') {
    if (!auth.user || avatarSaving.value) return

    avatarErr.value = ''
    avatarSaving.value = true
    const previousSnapshot = { ...avatarSnapshot.value }
    logAvatarProfileState('save-preferences:start')

    try {
      await auth.csrf()

      const payload = {
        avatar_mode: avatarDraft.mode,
        avatar_color: avatarDraft.color,
        avatar_icon: avatarDraft.icon,
        avatar_seed: avatarDraft.seed || null,
      }

      const { data } = await http.patch('/me/avatar', payload)
      avatarDebug('ProfileView:save-preferences:response', {
        payload,
        response: data,
      })

      auth.user = {
        ...auth.user,
        ...data,
        activity: auth.user?.activity || null,
      }

      syncAvatarDraftFromUser()
      logAvatarProfileState('save-preferences:success')
      toast.success(successMessage)
    } catch (e) {
      avatarDebug('ProfileView:save-preferences:error', {
        status: e?.response?.status ?? null,
        response: e?.response?.data ?? null,
        message: e?.message ?? null,
      })
      applyAvatarSnapshot(avatarDraft, previousSnapshot)
      const status = e?.response?.status
      const data = e?.response?.data

      if (status === 422 && data?.errors) {
        avatarErr.value =
          extractFirstError(data.errors, 'avatar_mode') ||
          extractFirstError(data.errors, 'avatar_color') ||
          extractFirstError(data.errors, 'avatar_icon') ||
          extractFirstError(data.errors, 'avatar_seed') ||
          'Skontroluj nastavenia avatara.'
      } else if (status === 401) {
        avatarErr.value = 'Prihlas sa.'
      } else {
        avatarErr.value = data?.message || 'Ukladanie avatara zlyhalo.'
      }

      toast.error('Avatar sa nepodarilo ulozit.')
    } finally {
      avatarSaving.value = false
    }
  }

  async function randomizeAvatar() {
    if (!auth.user || avatarSaving.value) return

    const seed = buildRandomAvatarSeed(auth.user?.id)
    avatarDraft.seed = seed
    avatarDraft.color = pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)
    avatarDraft.icon = pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)

    await saveAvatarPreferences('Nahodny avatar ulozeny.')
  }

  async function removeAvatarImage() {
    if (!auth.user || avatarRemoving.value || avatarUploading.value) return

    const approved = await confirm({
      title: 'Odstranit profilovu fotku',
      message: 'Naozaj chces odstranit profilovu fotku?',
      confirmText: 'Odstranit',
      cancelText: 'Zrusit',
      variant: 'danger',
    })

    if (!approved) return

    avatarErr.value = ''
    mediaErr.value = ''
    avatarRemoving.value = true
    logAvatarProfileState('remove-image:start')

    try {
      await auth.csrf()
      const { data } = await http.delete('/me/avatar-image')
      avatarDebug('ProfileView:remove-image:response', { response: data })

      if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value)
        avatarPreview.value = ''
      }

      if (data && typeof data === 'object') {
        auth.user = {
          ...auth.user,
          ...data,
          activity: auth.user?.activity || null,
        }
      }

      syncAvatarDraftFromUser()
      logAvatarProfileState('remove-image:success')
      toast.success('Profilova fotka bola odstranena.')
    } catch (e) {
      avatarDebug('ProfileView:remove-image:error', {
        status: e?.response?.status ?? null,
        response: e?.response?.data ?? null,
        message: e?.message ?? null,
      })
      const status = e?.response?.status
      const data = e?.response?.data
      if (status === 401) {
        avatarErr.value = 'Prihlas sa.'
      } else {
        avatarErr.value = data?.message || 'Odstranenie fotky zlyhalo.'
      }
    } finally {
      avatarRemoving.value = false
    }
  }

  function openPicker(type) {
    const input = type === 'avatar' ? avatarInput.value : coverInput.value
    if (input && !avatarUploading.value && !avatarRemoving.value && !coverUploading.value) {
      input.click()
    }
  }

  function setPreview(type, file) {
    const url = URL.createObjectURL(file)
    if (type === 'avatar') {
      if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
      avatarPreview.value = url
    } else {
      if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
      coverPreview.value = url
    }
  }

  function clearPreview(type) {
    if (type === 'avatar') {
      if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
      avatarPreview.value = ''
      return
    }

    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
    coverPreview.value = ''
  }

  async function uploadMedia(type, file) {
    if (!auth.user) {
      mediaErr.value = 'Prihlas sa.'
      return
    }

    mediaErr.value = ''
    avatarErr.value = ''
    if (type === 'avatar') avatarUploading.value = true
    else coverUploading.value = true
    logAvatarProfileState('upload-media:start', {
      type,
      fileName: file?.name ?? null,
      fileSize: file?.size ?? null,
      fileType: file?.type ?? null,
    })

    try {
      await auth.csrf()

      const form = new FormData()
      form.append('file', file)
      let response = null

      if (type === 'avatar') {
        response = await http.post('/me/avatar-image', form)
      } else {
        form.append('type', type)
        response = await http.post('/profile/media', form)
      }
      avatarDebug('ProfileView:upload-media:response', {
        type,
        response: response?.data ?? null,
      })

      clearPreview(type)

      const nextUser = response?.data
      if (nextUser && typeof nextUser === 'object') {
        auth.user = {
          ...auth.user,
          ...nextUser,
          activity: auth.user?.activity || null,
        }
      }

      syncAvatarDraftFromUser()
      logAvatarProfileState('upload-media:success', { type })

      if (type === 'avatar') {
        toast.success('Profilova fotka bola ulozena.')
      }
    } catch (e) {
      avatarDebug('ProfileView:upload-media:error', {
        type,
        status: e?.response?.status ?? null,
        response: e?.response?.data ?? null,
        message: e?.message ?? null,
      })
      const status = e?.response?.status
      const data = e?.response?.data

      if (status === 401) {
        mediaErr.value = 'Prihlas sa.'
      } else if (status === 422 && data?.errors) {
        mediaErr.value =
          extractFirstError(data.errors, 'file') ||
          extractFirstError(data.errors, 'type') ||
          'Skontroluj subor.'
      } else {
        mediaErr.value = data?.message || 'Upload zlyhal.'
      }

      if (type === 'avatar') {
        avatarErr.value = mediaErr.value
      }

      // Never keep local preview after failed upload; show only persisted server state.
      clearPreview(type)
    } finally {
      if (type === 'avatar') avatarUploading.value = false
      else coverUploading.value = false
    }
  }

  async function onMediaChange(type, event) {
    const selectedFile = event?.target?.files?.[0]
    if (!selectedFile) return
    event.target.value = ''

    mediaErr.value = ''
    if (type === 'avatar') {
      avatarErr.value = ''
    }

    let uploadFile = selectedFile

    try {
      uploadFile = await compressImageFileToMaxBytes(selectedFile, {
        maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
      })
    } catch {
      // Fallback to original file when browser-side compression is unavailable.
      uploadFile = selectedFile
    }

    if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
      mediaErr.value = 'Subor je prilis velky. Maximalna velkost je 20 MB.'
      if (type === 'avatar') {
        avatarErr.value = mediaErr.value
      }
      return
    }

    setPreview(type, uploadFile)
    uploadMedia(type, uploadFile)
  }

  function onCoverImageError() {
    coverLoadFailed.value = true
  }

  watch(
    () => [
      auth.user?.id,
      auth.user?.avatar_mode,
      auth.user?.avatar_color,
      auth.user?.avatar_icon,
      auth.user?.avatar_seed,
      auth.user?.avatar_url,
      auth.user?.avatar_path,
    ],
    () => {
      if (!auth.user) return
      syncAvatarDraftFromUser()
      logAvatarProfileState('watch:user-avatar-fields')
    },
    { immediate: true },
  )

  watch(
    () => coverSrc.value,
    () => {
      coverLoadFailed.value = false
    },
    { immediate: true },
  )

  watch(
    () => avatarModalOpen.value,
    (isOpen, wasOpen) => {
      if (!isOpen && wasOpen) {
        syncAvatarDraftFromUser()
        avatarErr.value = ''
      }
    },
  )

  onBeforeUnmount(() => {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
  })

  return {
    AVATAR_COLORS,
    avatarDraft,
    avatarErr,
    avatarInput,
    avatarModalOpen,
    avatarRemoving,
    avatarResolved,
    avatarSaving,
    avatarSrc,
    avatarUploading,
    coverInput,
    coverLoadFailed,
    coverSrc,
    coverUploading,
    iconOptions,
    logAvatarProfileState,
    mediaErr,
    onCoverImageError,
    onMediaChange,
    openAvatarEditor,
    openPicker,
    randomizeAvatar,
    removeAvatarImage,
    resetGeneratedAvatar,
    saveAvatarPreferences,
    selectAvatarColor,
    selectAvatarIcon,
    setAvatarMode,
    syncAvatarDraftFromUser,
  }
}
