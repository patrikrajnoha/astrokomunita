<template>
  <div v-if="showInitError" class="appInitScreen appInitScreen--error">
    <div class="card">
      <h1>Aplikácia sa nepodarila spustiť</h1>
      <p>{{ initMessage }}</p>
      <pre v-if="showStack && initStack">{{ initStack }}</pre>
    </div>
  </div>

  <div v-else-if="showLoading" class="appInitScreen">
    <div class="splashCard">
      <div class="splashStars" aria-hidden="true">
        <span class="splashStar" style="top:14%;left:12%;animation-delay:0s"></span>
        <span class="splashStar" style="top:22%;left:78%;animation-delay:0.6s"></span>
        <span class="splashStar" style="top:68%;left:22%;animation-delay:1.1s"></span>
        <span class="splashStar" style="top:74%;left:81%;animation-delay:0.3s"></span>
        <span class="splashStar" style="top:44%;left:6%;animation-delay:1.5s"></span>
        <span class="splashStar" style="top:36%;left:91%;animation-delay:0.9s"></span>
      </div>
      <div class="splashLogo" aria-hidden="true">
        <svg width="52" height="52" viewBox="0 0 192 192" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="96" cy="96" r="52" fill="white" fill-opacity="0.07"/>
          <circle cx="96" cy="96" r="24" fill="#fbbf24"/>
          <circle cx="132" cy="56" r="6" fill="white"/>
          <circle cx="58" cy="64" r="4" fill="white" fill-opacity="0.85"/>
          <circle cx="52" cy="132" r="5" fill="white" fill-opacity="0.9"/>
        </svg>
      </div>
      <h1 class="splashTitle">Astrokomunita</h1>
      <p class="splashSub">Inicializujem reláciu a smerovanie</p>
      <div class="splashBar" aria-hidden="true"><div class="splashBarFill"></div></div>
    </div>
  </div>

  <template v-else>
    <RouterView v-if="auth.bootstrapDone" />
    <div v-else class="appInitScreen">
      <div class="splashCard">
        <div class="splashStars" aria-hidden="true">
          <span class="splashStar" style="top:14%;left:12%;animation-delay:0s"></span>
          <span class="splashStar" style="top:22%;left:78%;animation-delay:0.6s"></span>
          <span class="splashStar" style="top:68%;left:22%;animation-delay:1.1s"></span>
          <span class="splashStar" style="top:74%;left:81%;animation-delay:0.3s"></span>
        </div>
        <div class="splashLogo" aria-hidden="true">
          <svg width="52" height="52" viewBox="0 0 192 192" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="96" cy="96" r="52" fill="white" fill-opacity="0.07"/>
            <circle cx="96" cy="96" r="24" fill="#fbbf24"/>
            <circle cx="132" cy="56" r="6" fill="white"/>
            <circle cx="58" cy="64" r="4" fill="white" fill-opacity="0.85"/>
            <circle cx="52" cy="132" r="5" fill="white" fill-opacity="0.9"/>
          </svg>
        </div>
        <h1 class="splashTitle">Astrokomunita</h1>
        <p class="splashSub">Čakám na dokončenie prihlásenia</p>
        <div class="splashBar" aria-hidden="true"><div class="splashBarFill"></div></div>
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
/* ─── Screen wrapper ─── */
.appInitScreen {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 16px;
  background: #0b1220;
  color: #fff;
}

/* ─── Splash card ─── */
.splashCard {
  position: relative;
  width: min(360px, 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  padding: 2.4rem 2rem 2rem;
  border: 1px solid rgb(255 255 255 / 0.08);
  border-radius: 24px;
  background: rgb(255 255 255 / 0.04);
  backdrop-filter: blur(12px);
  overflow: hidden;
  animation: splashCardIn 500ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes splashCardIn {
  from { opacity: 0; transform: translateY(14px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ─── Background stars ─── */
.splashStars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.splashStar {
  position: absolute;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #fff;
  opacity: 0;
  animation: splashStarPulse 2.8s ease-in-out infinite;
}

.splashStar:nth-child(2) { width: 2px; height: 2px; }
.splashStar:nth-child(3) { width: 4px; height: 4px; }
.splashStar:nth-child(4) { width: 2px; height: 2px; }
.splashStar:nth-child(5) { width: 3px; height: 3px; }
.splashStar:nth-child(6) { width: 2px; height: 2px; }

@keyframes splashStarPulse {
  0%, 100% { opacity: 0; transform: scale(0.6); }
  50%       { opacity: 0.7; transform: scale(1); }
}

/* ─── Logo ─── */
.splashLogo {
  position: relative;
  z-index: 1;
  margin-bottom: 1rem;
  animation: splashLogoIn 600ms 120ms cubic-bezier(0.22, 1, 0.36, 1) both;
  filter: drop-shadow(0 0 18px rgb(251 191 36 / 0.35));
}

@keyframes splashLogoIn {
  from { opacity: 0; transform: scale(0.7); }
  to   { opacity: 1; transform: scale(1); }
}

/* ─── Title ─── */
.splashTitle {
  position: relative;
  z-index: 1;
  margin: 0 0 0.4rem;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: #fff;
  text-align: center;
  animation: splashFadeUp 500ms 200ms ease both;
}

/* ─── Subtitle ─── */
.splashSub {
  position: relative;
  z-index: 1;
  margin: 0 0 1.6rem;
  font-size: 0.84rem;
  color: rgb(171 184 201 / 0.85);
  text-align: center;
  animation: splashFadeUp 500ms 280ms ease both;
}

@keyframes splashFadeUp {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─── Loading bar ─── */
.splashBar {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 3px;
  border-radius: 999px;
  background: rgb(255 255 255 / 0.1);
  overflow: hidden;
  animation: splashFadeUp 400ms 360ms ease both;
}

.splashBarFill {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent 0%, #0f73ff 40%, #fbbf24 60%, transparent 100%);
  animation: splashProgress 1.6s ease-in-out infinite;
  transform: translateX(-100%);
}

@keyframes splashProgress {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(200%); }
}

/* ─── Error card (keep original .card for error state) ─── */
.card {
  width: min(720px, 100%);
  border: 1px solid rgb(235 36 82 / 0.45);
  border-radius: 16px;
  padding: 20px;
  background: rgb(255 255 255 / 0.04);
  backdrop-filter: blur(8px);
}

.card h1 {
  margin: 0 0 8px;
  font-size: 18px;
  color: #eb2452;
}

.card p {
  margin: 0;
  opacity: 0.9;
  font-size: 0.9rem;
}

.card pre {
  margin-top: 12px;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 46vh;
  overflow: auto;
  padding: 12px;
  border-radius: 8px;
  background: rgb(0 0 0 / 0.3);
  font-size: 0.72rem;
  line-height: 1.45;
  color: #abb8c9;
}
</style>
