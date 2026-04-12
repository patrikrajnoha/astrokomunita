<template>
  <div>
    <AuthSplitLayout>
      <template #hero>
        <AuthHeroPanel
          eyebrow=""
          title="Prihlásenie"
          subtitle="Pokračujte do svojho Astrokomunita účtu bezpečným prihlásením."
        >
          <template #top>
            <div class="authHero__brand">
              <img src="/logo.png" alt="Astrokomunita" class="authHero__brandLogo" />
            </div>
          </template>

          <template #title>
            <span class="authHero__titleAccent">Prihlásenie</span>
          </template>
        </AuthHeroPanel>
      </template>

      <AuthFormSection
        kicker=""
        title="Vitajte späť"
        description="Použite e-mail a heslo pre prístup k profilu a komunitnému feedu."
      >
        <form class="authForm" @submit.prevent="submit" @keydown.enter="handleEnterSubmit" novalidate>
          <AuthField
            v-model="email"
            label="E-mail"
            type="email"
            autocomplete="email"
            placeholder="vas@email.sk"
            :error="emailError"
            required
          >
            <template #icon>
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M3.5 7.5 12 13l8.5-5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="3.5" y="5.5" width="17" height="13" rx="2.8" stroke="currentColor" stroke-width="1.8" />
              </svg>
            </template>
          </AuthField>

          <AuthField
            v-model="password"
            label="Heslo"
            type="password"
            autocomplete="current-password"
            placeholder="Zadajte heslo"
            :error="passwordError"
            required
          >
            <template #icon>
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M7.5 11V9.2A4.5 4.5 0 0 1 12 4.7a4.5 4.5 0 0 1 4.5 4.5V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="5" y="11" width="14" height="9.5" rx="2.7" stroke="currentColor" stroke-width="1.8" />
              </svg>
            </template>
            <template #labelAction>
              <RouterLink :to="forgotPasswordLink" class="authInlineLink">
                Zabudli ste heslo?
              </RouterLink>
            </template>
          </AuthField>

          <AuthAlert
            v-if="error"
            title="Prihlásenie zlyhalo"
            :message="error"
          />

          <AuthAlert
            v-else-if="isBannedState"
            title="Účet je blokovaný"
            :message="bannedDetails"
          />

          <p v-if="resetSuccessMessage" class="authField__meta">{{ resetSuccessMessage }}</p>

          <AuthActions
            :back-to="{ name: 'home' }"
            back-label="Späť"
            submit-label="Prihlásiť sa"
            loading-label="Prihlasujem..."
            :loading="authBusy"
          />

          <p class="authFootnote">
            Nemáte účet?
            <RouterLink class="authInlineLink" :to="registerLink">Vytvoriť účet</RouterLink>
          </p>
        </form>
      </AuthFormSection>
    </AuthSplitLayout>

    <Transition name="login-success-fade">
      <div
        v-if="showSuccessAnimation"
        class="loginSuccessOverlay"
        role="status"
        aria-live="polite"
        aria-label="Prihlásenie úspešné, pripravujem presmerovanie"
      >
        <div ref="rocketCanvasRef" class="loginSuccessOverlay__canvas"></div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthActions from '@/components/auth/AuthActions.vue'
import AuthAlert from '@/components/auth/AuthAlert.vue'
import AuthField from '@/components/auth/AuthField.vue'
import AuthFormSection from '@/components/auth/AuthFormSection.vue'
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue'
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue'
import { useLoginAnimation } from '@/composables/useLoginAnimation'
import api from '@/services/api'
import { prefetchHomeFeed } from '@/services/feedPrefetch'
import { useAuthStore } from '@/stores/auth'

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

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinný.' : ''))
const passwordError = computed(() => (attempted.value && !password.value ? 'Heslo je povinné.' : ''))
const resetSuccessMessage = computed(() => (
  route.query.reset === '1' ? 'Heslo bolo zmenené. Môžete sa prihlásiť novým heslom.' : ''
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

  if (reason && bannedAt) return `Dôvod: ${reason}. Blokované: ${bannedAt}.`
  if (reason) return `Dôvod: ${reason}.`
  if (bannedAt) return `Blokované: ${bannedAt}.`
  return 'Tento účet je blokovaný.'
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
    error.value = e?.response?.data?.message || e?.authError?.message || e?.message || 'Prihlásenie zlyhalo.'
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
