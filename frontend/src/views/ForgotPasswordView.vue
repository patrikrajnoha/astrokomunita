<template>
  <div class="forgotView">
    <div class="forgotStars" aria-hidden="true">
      <span
        v-for="star in stars"
        :key="star.id"
        class="forgotStar"
        :style="star.style"
      ></span>
    </div>

    <AuthSplitLayout class="forgotSplit">
      <template #hero>
        <AuthHeroPanel
          eyebrow="Obnova hesla"
          title="Zabudnuté heslo"
          subtitle="Zadajte e-mail k účtu a pošleme vám obnovovací kód na nastavenie nového hesla."
        />
      </template>

      <AuthFormSection
        kicker="Obnova"
        title="Vyžiadať obnovovací kód"
        description="Zadajte e-mail účtu. Kód príde e-mailom a platí obmedzený čas."
      >
        <form class="authForm" @submit.prevent="submit" novalidate>
          <AuthField
            v-model="email"
            label="E-mail"
            type="email"
            autocomplete="email"
            placeholder="you@example.com"
            helper="Obnovovací kód dostanete e-mailom."
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

          <AuthAlert v-if="error" title="Neda sa pokracovat" :message="error" />

          <AuthActions
            :back-to="{ name: 'login' }"
            back-label="Späť"
            submit-label="Ďalej"
            loading-label="Odosielam..."
            :loading="loading"
          />

          <AuthDivider text="alebo" />

          <p class="authFootnote">
            Už máte kód?
            <RouterLink class="authInlineLink" :to="resetLink">Pokračovať na reset</RouterLink>
          </p>
        </form>
      </AuthFormSection>
    </AuthSplitLayout>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthActions from '@/components/auth/AuthActions.vue'
import AuthAlert from '@/components/auth/AuthAlert.vue'
import AuthDivider from '@/components/auth/AuthDivider.vue'
import AuthField from '@/components/auth/AuthField.vue'
import AuthFormSection from '@/components/auth/AuthFormSection.vue'
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue'
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const loading = ref(false)
const attempted = ref(false)
const error = ref('')

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

const stars = createStars(80)

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinný.' : ''))
const resetLink = computed(() => ({
  name: 'reset-password',
  query: email.value.trim() ? { email: email.value.trim() } : undefined,
}))

async function submit() {
  attempted.value = true
  error.value = ''

  if (!email.value.trim()) {
    return
  }

  loading.value = true
  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    await http.post('/auth/password/forgot', {
      email: email.value.trim(),
    }, {
      meta: { skipErrorToast: true },
    })

    await router.push({
      name: 'reset-password',
      query: {
        email: email.value.trim(),
        sent: '1',
      },
    })
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Nepodarilo sa odoslať obnovovací kód.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.forgotView {
  position: relative;
  min-height: 100dvh;
  overflow: hidden;
  background:
    linear-gradient(164deg, rgb(18 24 34 / 1) 0%, rgb(21 29 40 / 1) 56%, rgb(17 23 33 / 1) 100%);
}

.forgotSplit {
  position: relative;
  z-index: 1;
  background: transparent !important;
}

.forgotView :deep(.authSplit__hero) {
  justify-content: flex-end;
}

.forgotView :deep(.authSplit__form) {
  justify-content: flex-start;
}

.forgotStars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.forgotStar {
  position: absolute;
  z-index: 0;
}

.forgotStar::before,
.forgotStar::after {
  position: absolute;
  content: '';
  background-color: #fff;
  border-radius: 10px;
  animation: forgotStarBlink 1.5s infinite;
  animation-delay: var(--blink-delay);
}

.forgotStar::before {
  top: calc(var(--star-size) / 2);
  left: calc(var(--star-size) / -2);
  width: calc(3 * var(--star-size));
  height: var(--star-size);
}

.forgotStar::after {
  top: calc(var(--star-size) / -2);
  left: calc(var(--star-size) / 2);
  width: var(--star-size);
  height: calc(3 * var(--star-size));
}

@keyframes forgotStarBlink {
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
</style>
