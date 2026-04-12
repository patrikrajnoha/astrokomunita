import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

export function useAccountSettings() {
  const auth = useAuthStore()
  const router = useRouter()

  const passwordForm = reactive({
    current: '',
    password: '',
    confirm: '',
  })

  const passwordState = reactive({
    loading: false,
    success: '',
    error: '',
    fieldError: '',
  })

  const deactivateForm = reactive({
    password: '',
  })

  const deactivateState = reactive({
    loading: false,
    error: '',
    fieldError: '',
  })

  const logoutState = reactive({
    loading: false,
    error: '',
  })

  const activity = ref(null)
  const activityLoading = ref(false)
  const activityError = ref('')
  const activityExpanded = ref(false)

  const extractFirstError = (errorsObj, field) => {
    const value = errorsObj?.[field]
    return Array.isArray(value) && value.length ? String(value[0]) : ''
  }

  const resetPasswordState = () => {
    passwordState.success = ''
    passwordState.error = ''
    passwordState.fieldError = ''
  }

  const submitPassword = async () => {
    resetPasswordState()

    if (!auth.user) {
      passwordState.error = 'Nie ste prihlásený.'
      return
    }

    if (!passwordForm.current || !passwordForm.password || !passwordForm.confirm) {
      passwordState.fieldError = 'Všetky polia sú povinné.'
      return
    }

    if (passwordForm.password.length < 8) {
      passwordState.fieldError = 'Heslo musí mať aspoň 8 znakov.'
      return
    }

    if (passwordForm.password !== passwordForm.confirm) {
      passwordState.fieldError = 'Heslá sa nezhodujú.'
      return
    }

    passwordState.loading = true

    try {
      await auth.csrf()
      await http.patch('/profile/password', {
        current_password: passwordForm.current,
        password: passwordForm.password,
        password_confirmation: passwordForm.confirm,
      })

      passwordForm.current = ''
      passwordForm.password = ''
      passwordForm.confirm = ''
      passwordState.success = 'Heslo bolo zmenené.'
    } catch (error) {
      const status = error?.response?.status
      const data = error?.response?.data

      if (status === 422 && data?.errors) {
        passwordState.fieldError =
          extractFirstError(data.errors, 'current_password') ||
          extractFirstError(data.errors, 'password') ||
          'Skontrolujte zvýraznené polia.'
      } else {
        passwordState.error = data?.message || 'Zmena hesla zlyhala.'
      }
    } finally {
      passwordState.loading = false
    }
  }

  const submitDeactivate = async () => {
    deactivateState.error = ''
    deactivateState.fieldError = ''
    deactivateState.loading = true

    try {
      if (!auth.user) {
        throw new Error('Nie ste prihlásený.')
      }

      if (!deactivateForm.password) {
        deactivateState.fieldError = 'Aktuálne heslo je povinné.'
        return
      }

      await auth.csrf()
      await http.delete('/profile', {
        data: {
          current_password: deactivateForm.password,
        },
      })

      deactivateForm.password = ''
      await auth.logout()
      router.push({ name: 'login' })
    } catch (error) {
      const status = error?.response?.status
      const data = error?.response?.data

      if (status === 422 && data?.errors) {
        deactivateState.fieldError =
          extractFirstError(data.errors, 'current_password') ||
          'Skontrolujte zadané heslo.'
      } else {
        deactivateState.error = data?.message || error?.message || 'Deaktivácia účtu zlyhala.'
      }
    } finally {
      deactivateState.loading = false
    }
  }

  const submitLogout = async () => {
    logoutState.error = ''
    logoutState.loading = true

    try {
      await auth.logout()
      await router.push({ name: 'login' })
    } catch (error) {
      logoutState.error = error?.message || 'Odhlásenie zlyhalo.'
    } finally {
      logoutState.loading = false
    }
  }

  const normalizeCount = (value) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 0) return 0
    return Math.floor(parsed)
  }

  const normalizeActivity = (payload) => {
    if (!payload || typeof payload !== 'object') return null

    return {
      last_login_at: payload.last_login_at || null,
      posts_count: normalizeCount(payload.posts_count),
      event_participations_count: normalizeCount(payload.event_participations_count),
    }
  }

  const loadUserActivity = async () => {
    if (!auth.user) return

    const fromAuth = normalizeActivity(auth.user.activity)
    if (fromAuth && !activity.value) {
      activity.value = fromAuth
    }

    activityLoading.value = true
    activityError.value = ''

    try {
      const { data } = await http.get('/me/activity', {
        meta: { skipErrorToast: true },
      })
      const normalized = normalizeActivity(data)
      activity.value = normalized
      if (auth.user && normalized) {
        auth.user = {
          ...auth.user,
          activity: normalized,
        }
      }
    } catch (error) {
      if (!activity.value && fromAuth) {
        activity.value = fromAuth
      } else if (!activity.value) {
        activityError.value = error?.response?.data?.message || 'Načítavanie aktivity zlyhalo.'
      }
    } finally {
      activityLoading.value = false
    }
  }

  const toggleActivitySection = async () => {
    activityExpanded.value = !activityExpanded.value
    if (activityExpanded.value && !activity.value && !activityLoading.value) {
      await loadUserActivity()
    }
  }

  onMounted(async () => {
    if (!auth.initialized) {
      await auth.fetchUser()
    }

    if (auth.user) {
      activity.value = normalizeActivity(auth.user.activity)
    }
  })

  return {
    activity,
    activityError,
    activityExpanded,
    activityLoading,
    deactivateForm,
    deactivateState,
    logoutState,
    passwordForm,
    passwordState,
    submitDeactivate,
    submitLogout,
    submitPassword,
    toggleActivitySection,
  }
}
