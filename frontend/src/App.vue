<template>
  <div v-if="showInitError" class="appInitScreen appInitScreen--error">
    <div class="card">
      <h1>Aplikácia sa nepodarila spustiť</h1>
      <p>{{ initMessage }}</p>
      <pre v-if="showStack && initStack">{{ initStack }}</pre>
    </div>
  </div>

  <div v-else-if="showLoading" class="appInitScreen">
    <div class="card">
      <h1>Načítavam aplikáciu...</h1>
      <p>Inicializujem reláciu a smerovanie.</p>
    </div>
  </div>

  <template v-else>
    <RouterView v-if="auth.bootstrapDone" />
    <div v-else class="appInitScreen">
      <div class="card">
        <h1>NaÄŤĂ­tavam aplikĂˇciu...</h1>
        <p>ÄŚakĂˇm na dokonÄŤenie prihlĂˇsenia.</p>
      </div>
    </div>
    <EmailVerificationGateModal
      v-if="auth.bootstrapDone"
      :open="showEmailVerificationGate"
      @verified="handleEmailVerified"
    />
    <Toaster />
    <ConfirmModal />
  </template>
</template>

<script setup>
import { computed } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import EmailVerificationGateModal from '@/components/auth/EmailVerificationGateModal.vue'
import Toaster from '@/components/ui/Toaster.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { appInitState } from '@/bootstrap/appInitState'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'

const showInitError = computed(() => Boolean(appInitState.initError))
const showLoading = computed(() => appInitState.initializing && !showInitError.value)
const showStack = computed(() => import.meta.env.DEV)
const initMessage = computed(() => appInitState.initError?.message || 'Neznáma chyba pri štarte')
const initStack = computed(() => appInitState.initError?.stack || '')

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const preferences = useEventPreferencesStore()

const showEmailVerificationGate = computed(() => {
  if (!auth.isAuthed || auth.isAdmin) return false
  if (!auth.user?.email) return false
  return !auth.user?.email_verified_at
})

function resolveOnboardingRedirectTarget() {
  const currentPath = typeof route.fullPath === 'string' ? route.fullPath : '/'
  if (!currentPath.startsWith('/') || currentPath.startsWith('/onboarding')) {
    return '/'
  }

  return currentPath
}

async function handleEmailVerified() {
  try {
    await auth.fetchUser({
      source: 'email-gate-verified',
      retry: false,
      markBootstrap: false,
      preserveStateOnError: true,
    })
  } catch {
    // Non-fatal; onboarding redirect can still proceed.
  }

  if (!auth.isAuthed || auth.isAdmin || !auth.user?.email_verified_at) {
    return
  }

  let onboardingCompleted = preferences.isOnboardingCompleted
  if (!preferences.loaded && !preferences.loading) {
    try {
      await preferences.fetchPreferences()
      onboardingCompleted = preferences.isOnboardingCompleted
    } catch {
      onboardingCompleted = false
    }
  }

  if (!onboardingCompleted && route.name !== 'onboarding') {
    await router.push({
      name: 'onboarding',
      query: {
        redirect: resolveOnboardingRedirectTarget(),
        start_tour: '1',
      },
    })
  }
}
</script>

<style scoped>
.appInitScreen {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 16px;
  background: var(--bg-app);
  color: var(--text-primary);
  transition: background-color 700ms;
}

.card {
  width: min(720px, 100%);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  background: var(--bg-surface-1);
}

.card h1 {
  margin: 0 0 8px;
  font-size: 18px;
}

.card p {
  margin: 0;
  opacity: 0.9;
}

.card pre {
  margin-top: var(--space-3);
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 46vh;
  overflow: auto;
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--bg-surface-2);
  font-size: var(--font-size-xs);
  line-height: 1.45;
}

.appInitScreen--error .card {
  border-color: rgb(var(--danger-rgb) / 0.62);
}
</style>
