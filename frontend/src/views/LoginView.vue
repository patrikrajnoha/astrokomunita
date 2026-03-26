<template>
  <div class="loginView">
    <main class="mx-auto flex min-h-dvh w-full max-w-[480px] items-start justify-center px-3 py-3 sm:py-8">
      <section class="w-full rounded-[24px] bg-[#1c2736]/55 p-3.5 sm:p-6">
        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-[#0F73FF]">Prihlásenie</p>
        <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-[#FFFFFF]">Vitaj späť</h1>
        <p class="mt-0.5 text-xs text-[#ABB8C9]">Prihlás sa do Astrokomunity.</p>

        <form class="mt-4 space-y-3" @submit.prevent="submit" @keydown.enter="handleEnterSubmit" novalidate>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-[#ABB8C9]">E-mail</span>
            <div class="flex min-h-[42px] items-center gap-2 rounded-[18px] bg-[#222E3F] px-3">
              <svg class="h-4 w-4 flex-none text-[#ABB8C9]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3.5 7.5 12 13l8.5-5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="3.5" y="5.5" width="17" height="13" rx="2.8" stroke="currentColor" stroke-width="1.8" />
              </svg>
              <input
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                class="h-full w-full border-0 bg-transparent text-sm text-[#FFFFFF] outline-none placeholder:text-[#ABB8C9]/70"
                required
              />
            </div>
            <p v-if="emailError" class="mt-1 text-xs text-[#EB2452]">{{ emailError }}</p>
          </label>

          <label class="block">
            <div class="mb-1 flex items-center justify-between gap-3">
              <span class="block text-xs font-medium text-[#ABB8C9]">Heslo</span>
              <RouterLink :to="forgotPasswordLink" class="text-xs font-medium text-[#0F73FF] hover:text-[#FFFFFF]">
                Zabudnuté heslo?
              </RouterLink>
            </div>
            <div class="flex min-h-[42px] items-center gap-2 rounded-[18px] bg-[#222E3F] px-3">
              <svg class="h-4 w-4 flex-none text-[#ABB8C9]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M7.5 11V9.2A4.5 4.5 0 0 1 12 4.7a4.5 4.5 0 0 1 4.5 4.5V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="5" y="11" width="14" height="9.5" rx="2.7" stroke="currentColor" stroke-width="1.8" />
              </svg>
              <input
                v-model="password"
                type="password"
                autocomplete="current-password"
                placeholder="Zadajte heslo"
                class="h-full w-full border-0 bg-transparent text-sm text-[#FFFFFF] outline-none placeholder:text-[#ABB8C9]/70"
                required
              />
            </div>
            <p v-if="passwordError" class="mt-1 text-xs text-[#EB2452]">{{ passwordError }}</p>
          </label>

          <div v-if="error" class="rounded-[14px] bg-[#EB2452]/15 px-3 py-2 text-xs text-[#EB2452]">
            {{ error }}
          </div>

          <div v-else-if="isBannedState" class="rounded-[14px] bg-[#EB2452]/15 px-3 py-2 text-xs text-[#EB2452]">
            {{ bannedDetails }}
          </div>

          <p v-if="resetSuccessMessage" class="text-xs text-[#ABB8C9]">{{ resetSuccessMessage }}</p>

          <div class="grid grid-cols-2 gap-2 pt-0.5">
            <RouterLink
              :to="{ name: 'home' }"
              class="inline-flex min-h-[40px] items-center justify-center rounded-[999px] bg-[#222E3F] px-4 text-sm font-medium text-[#ABB8C9] transition-colors hover:bg-[#1c2736] hover:text-[#FFFFFF]"
            >
              Späť
            </RouterLink>
            <button
              type="submit"
              class="inline-flex min-h-[40px] items-center justify-center rounded-[999px] bg-[#0F73FF] px-4 text-sm font-medium text-[#FFFFFF] transition-colors hover:bg-[#0d65e6] disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="authBusy"
            >
              {{ authBusy ? 'Prihlasujem...' : 'Prihlásiť sa' }}
            </button>
          </div>

          <p class="pt-0.5 text-center text-xs text-[#ABB8C9]">
            Nemáte účet?
            <RouterLink class="font-medium text-[#0F73FF] hover:text-[#FFFFFF]" :to="registerLink">Vytvoriť účet</RouterLink>
          </p>
        </form>
      </section>
    </main>

    <Transition name="login-success-fade">
      <div
        v-if="showSuccessAnimation"
        class="loginSuccessOverlay"
        role="status"
        aria-live="polite"
        aria-label="Prihlasenie uspesne, pripravujem presmerovanie"
      >
        <div ref="rocketCanvasRef" class="loginSuccessOverlay__canvas"></div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { prefetchHomeFeed } from '@/services/feedPrefetch'
import { useAuthStore } from '@/stores/auth'
import { useLoginAnimation } from '@/composables/useLoginAnimation'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const { rocketCanvasRef, showSuccessAnimation, isAnimating, waitForSuccessAnimation, cancelAnimation } = useLoginAnimation()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const error = ref('')
const attempted = ref(false)

const redirect = computed(() => {
  const candidate = route.query.redirect
  return typeof candidate === 'string' && candidate.startsWith('/') ? candidate : '/'
})

const registerLink = computed(() => ({
  name: 'register',
  query: { redirect: redirect.value },
}))

const forgotPasswordLink = computed(() => ({
  name: 'forgot-password',
  query: email.value ? { email: email.value } : undefined,
}))

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinny.' : ''))
const passwordError = computed(() => (attempted.value && !password.value ? 'Heslo je povinne.' : ''))
const resetSuccessMessage = computed(() => (
  route.query.reset === '1' ? 'Heslo bolo zmenene. Mozete sa prihlasit novym heslom.' : ''
))
const authBusy = computed(() => auth.loading || isAnimating.value)

const isBannedState = computed(() => auth.error?.type === 'banned')
const bannedDetails = computed(() => {
  if (!isBannedState.value) return ''

  const reason = String(auth.error?.reason || '').trim()
  const bannedAtRaw = auth.error?.bannedAt
  let bannedAt = ''

  if (bannedAtRaw) {
    const parsed = new Date(bannedAtRaw)
    bannedAt = Number.isNaN(parsed.getTime()) ? String(bannedAtRaw) : parsed.toLocaleString()
  }

  if (reason && bannedAt) return `Dovod: ${reason}. Blokovane: ${bannedAt}.`
  if (reason) return `Dovod: ${reason}.`
  if (bannedAt) return `Blokovane: ${bannedAt}.`
  return 'Tento ucet je blokovany.'
})

function shouldPrefetchHomeFeed(destination) {
  if (typeof destination !== 'string') {
    return false
  }

  const [path] = destination.split('?')
  return path === '/'
}

async function submit() {
  if (authBusy.value) {
    return
  }

  attempted.value = true
  error.value = ''

  if (!email.value.trim() || !password.value) {
    return
  }

  try {
    await auth.login({
      email: email.value.trim(),
      password: password.value,
      remember: true,
    })

    const destination = redirect.value

    if (shouldPrefetchHomeFeed(destination)) {
      void prefetchHomeFeed(api)
    }

    await waitForSuccessAnimation()
    await router.push(destination)
  } catch (e) {
    cancelAnimation()
    error.value = e?.response?.data?.message || e?.authError?.message || e?.message || 'Prihlasenie zlyhalo.'
  }
}

function handleEnterSubmit(event) {
  const targetTag = String(event?.target?.tagName || '').toLowerCase()
  if (targetTag !== 'input') {
    return
  }

  event.preventDefault()
  void submit()
}
</script>

<style scoped>
.loginView {
  position: relative;
  min-height: 100dvh;
  background: #151d28;
  overflow-x: hidden;
}

.login-success-fade-enter-active,
.login-success-fade-leave-active {
  transition: opacity 240ms ease;
}

.login-success-fade-enter-from,
.login-success-fade-leave-to {
  opacity: 0;
}

.loginSuccessOverlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  pointer-events: none;
  background: rgb(21 29 40 / 0.86);
}

.loginSuccessOverlay__canvas {
  position: absolute;
  inset: 0;
}

.loginSuccessOverlay__canvas :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
  display: block;
}
</style>