<template>
  <div class="loginView" :class="{ isAnimationOnly: showSuccessAnimation }">
    <div class="loginStars" aria-hidden="true">
      <span
        v-for="star in stars"
        :key="star.id"
        class="loginStar"
        :style="star.style"
      ></span>
    </div>

    <AuthSplitLayout>
      <template #hero>
        <AuthHeroPanel
          eyebrow="Prihlasovanie"
          title="Prihlásenie"
          subtitle="Pokračujte do svojho Astrokomunita účtu bezpečným prihlásením."
        />
      </template>

      <AuthFormSection
        kicker="Účet"
        title="Vitajte späť"
        description="Použite e-mail a heslo pre prístup k profilu a komunitnému feedu."
      >
        <form class="authForm" @submit.prevent="submit" @keydown.enter="handleEnterSubmit" novalidate>
          <AuthField
            v-model="email"
            label="E-mail"
            type="email"
            autocomplete="email"
            placeholder="you@example.com"
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
              <RouterLink :to="forgotPasswordLink" class="authInlineLink">Zabudli ste heslo?</RouterLink>
            </template>
          </AuthField>

          <AuthAlert
            v-if="error"
            title="Nepodarilo sa prihlásiť"
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
            loading-label="Prihlasuje sa..."
            :loading="authBusy"
          />

          <p class="authFootnote">
            Potrebujete účet?
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
        <div class="loginSuccessOverlay__scene" aria-hidden="true">
          <div class="rain rain1"></div>
          <div class="rain rain2">
            <div class="drop drop2"></div>
          </div>
          <div class="rain rain3"></div>
          <div class="rain rain4"></div>
          <div class="rain rain5">
            <div class="drop drop5"></div>
          </div>
          <div class="rain rain6"></div>
          <div class="rain rain7"></div>
          <div class="rain rain8">
            <div class="drop drop8"></div>
          </div>
          <div class="rain rain9"></div>
          <div class="rain rain10"></div>
          <div class="drop drop11"></div>
          <div class="drop drop12"></div>
          <div ref="rocketCanvasRef" class="loginSuccessOverlay__canvas"></div>
        </div>
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
const stars = createStars(80)

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

// Lifecycle hooks for animation are managed inside useLoginAnimation.

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

function seededRandom(seed) {
  const value = Math.sin(seed * 9999.91) * 10000
  return value - Math.floor(value)
}

function createStars(count) {
  const generatedStars = []

  for (let i = 1; i <= count; i += 1) {
    const x = seededRandom(i * 1.37)
    const y = seededRandom(i * 2.17)
    const size = [1, 2, 3, 4][Math.floor(seededRandom(i * 3.31) * 4)]
    const delay = -(seededRandom(i * 4.13) * 4)

    generatedStars.push({
      id: i,
      style: {
        left: `${(x * 100).toFixed(2)}%`,
        top: `${(y * 100).toFixed(2)}%`,
        '--star-size': `${size}px`,
        '--blink-delay': `${delay.toFixed(2)}s`,
      },
    })
  }

  return generatedStars
}
</script>

<style scoped>
.loginView {
  position: relative;
  min-height: 100dvh;
  overflow: hidden;
  background:
    linear-gradient(164deg, rgb(18 24 34 / 1) 0%, rgb(21 29 40 / 1) 56%, rgb(17 23 33 / 1) 100%);
}

.loginView :deep(.authSplit) {
  position: relative;
  z-index: 1;
  background: transparent;
  transition: opacity 140ms ease, visibility 140ms ease;
}

.loginView :deep(.authSplit__hero) {
  justify-content: flex-end;
}

.loginView :deep(.authSplit__form) {
  justify-content: flex-start;
}

.loginView.isAnimationOnly :deep(.authSplit) {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
}

.loginStars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.loginStar {
  position: absolute;
  z-index: 0;
}

.loginStar::before,
.loginStar::after {
  position: absolute;
  content: '';
  background-color: #fff;
  border-radius: 10px;
  animation: loginStarBlink 1.5s infinite;
  animation-delay: var(--blink-delay);
}

.loginStar::before {
  top: calc(var(--star-size) / 2);
  left: calc(var(--star-size) / -2);
  width: calc(3 * var(--star-size));
  height: var(--star-size);
}

.loginStar::after {
  top: calc(var(--star-size) / -2);
  left: calc(var(--star-size) / 2);
  width: var(--star-size);
  height: calc(3 * var(--star-size));
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
  overflow: hidden;
  pointer-events: none;
  background: transparent;
}

.loginSuccessOverlay__scene {
  position: absolute;
  inset: 0;
  overflow: hidden;
  perspective: 10rem;
  isolation: isolate;
}

.loginSuccessOverlay__canvas {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.loginSuccessOverlay__canvas :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
  display: block;
}

.rain {
  position: absolute;
  width: 16px;
  height: 160px;
  border-radius: 999px;
  background: rgb(198 200 215 / 0.38);
  z-index: 0;
}

.rain1 {
  left: 3rem;
  top: 20rem;
  animation: raining 4s linear infinite both -2s;
}

.rain2 {
  left: 14rem;
  top: 8rem;
  animation: raining 4s linear infinite both -4s;
}

.rain3 {
  right: 17rem;
  top: 5rem;
  animation: raining 4s linear infinite both -4s;
}

.rain4 {
  left: 50rem;
  top: 1rem;
  animation: raining 4s linear infinite both -4.5s;
}

.rain5 {
  right: 35rem;
  top: 25rem;
  animation: raining 4s linear infinite both -1s;
}

.rain6 {
  left: 45rem;
  top: 40rem;
  animation: raining 4s linear infinite both -2.5s;
}

.rain7 {
  right: 15rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1s;
}

.rain8 {
  left: 22rem;
  top: 35rem;
  animation: raining 4s linear infinite both -1s;
}

.rain9 {
  right: 45rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1.5s;
}

.rain10 {
  right: 15rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1s;
}

.drop {
  position: absolute;
  width: 14px;
  height: 50px;
  border-radius: 999px;
  background: rgb(198 200 215 / 0.38);
  z-index: 0;
}

.drop2 {
  left: 45rem;
  top: 32rem;
  animation: raining 4s linear infinite both -1s;
}

.drop5 {
  left: 70rem;
  top: 30rem;
  animation: raining 4s linear infinite both -3.4s;
}

.drop8 {
  left: 15rem;
  top: 38rem;
  animation: raining 4s linear infinite both -2.4s;
}

.drop11 {
  left: 45rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1.4s;
}

.drop12 {
  left: 30rem;
  top: 55rem;
  animation: raining 4s linear infinite both -3.4s;
}

@keyframes raining {
  from {
    transform: translateY(-60rem);
  }
  to {
    transform: translateY(6rem);
  }
}

@keyframes loginStarBlink {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }

  50% {
    transform: scale(0.4);
    opacity: 0.5;
  }
}

@media (max-width: 900px) {
  .rain,
  .drop {
    opacity: 0.52;
  }
}

@media (max-width: 480px) {
  .rain,
  .drop {
    display: none;
  }
}

@media (max-width: 768px) {
  .rain {
    width: 10px;
    height: 110px;
    opacity: 0.28;
  }

  .drop {
    width: 9px;
    height: 34px;
    opacity: 0.24;
  }

  .rain4,
  .rain5,
  .rain6,
  .rain7,
  .rain9,
  .rain10,
  .drop5,
  .drop11,
  .drop12 {
    display: none;
  }

  .rain1 {
    left: 1.2rem;
    top: 8.4rem;
  }

  .rain2 {
    left: 6.8rem;
    top: 2.2rem;
  }

  .rain3 {
    right: 2.4rem;
    top: 1rem;
  }

  .rain8 {
    left: 12rem;
    top: 10.5rem;
  }

  .drop2 {
    left: 9.2rem;
    top: 13.5rem;
  }

  .drop8 {
    left: 5.8rem;
    top: 17rem;
  }
}
</style>
