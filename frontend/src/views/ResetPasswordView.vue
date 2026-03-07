<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow="Obnova hesla"
        title="Reset hesla"
        subtitle="Zadajte obnovovaci kod z emailu a nastavte nove heslo na dokoncnie obnovy uctu."
      />
    </template>

    <AuthFormSection
      kicker="Obnova"
      title="Zadajte kod a nove heslo"
      description="Skontrolujte emailovu schranku pre obnovovaci kod a odošlite ho spolu s novym heslom."
    >
      <form class="authForm" @submit.prevent="submit" novalidate>
        <p v-if="prefilledEmailLabel" class="authPrefill">
          Obnovujete heslo pre <strong>{{ prefilledEmailLabel }}</strong>.
        </p>

        <AuthField
          v-if="needsEmailField"
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
          v-model="code"
          label="Obnovovaci kod"
          placeholder="XXXXX-XXXXX"
          autocomplete="one-time-code"
          :error="codeError"
          required
        >
          <template #icon>
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M7 9.2V7.9A4.9 4.9 0 0 1 11.9 3 4.9 4.9 0 0 1 16.8 7.9v1.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <rect x="5" y="9.2" width="14" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8" />
              <circle cx="12" cy="14.7" r="1.2" fill="currentColor" />
            </svg>
          </template>
        </AuthField>

        <AuthField
          v-model="password"
          label="Nove heslo"
          type="password"
          autocomplete="new-password"
          placeholder="Vytvorte silne heslo"
          helper="Pouzite aspon 8 znakov."
          :error="passwordError"
          required
        >
          <template #icon>
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M7.5 11V9.2A4.5 4.5 0 0 1 12 4.7a4.5 4.5 0 0 1 4.5 4.5V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <rect x="5" y="11" width="14" height="9.5" rx="2.7" stroke="currentColor" stroke-width="1.8" />
            </svg>
          </template>
        </AuthField>

        <AuthAlert
          v-if="invalidCodeMessage"
          title="Neplatny obnovovaci kod"
          :message="invalidCodeMessage"
        />

        <AuthAlert
          v-if="error && !invalidCodeMessage"
          title="Heslo sa nepodarilo resetovat"
          :message="error"
        />

        <p v-if="sentMessage" class="authField__meta">{{ sentMessage }}</p>

        <AuthActions
          :back-to="{ name: 'forgot-password', query: emailQuery }"
          back-label="Spat"
          submit-label="Dalej"
          loading-label="Aktualizujem..."
          :loading="loading"
        />
      </form>
    </AuthFormSection>
  </AuthSplitLayout>
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
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const initialEmail = typeof route.query.email === 'string' ? route.query.email : ''
const email = ref(initialEmail)
const code = ref('')
const password = ref('')
const loading = ref(false)
const attempted = ref(false)
const error = ref('')
const invalidCodeMessage = ref('')

const needsEmailField = computed(() => !initialEmail)
const prefilledEmailLabel = computed(() => (needsEmailField.value ? '' : email.value.trim()))
const sentMessage = computed(() => (
  route.query.sent === '1' ? 'Poslali sme obnovovaci kod na vas e-mail. Zadajte ho nizsie.' : ''
))

const emailQuery = computed(() => (email.value.trim() ? { email: email.value.trim() } : undefined))
const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinny.' : ''))
const codeError = computed(() => {
  if (!attempted.value) return ''
  if (!code.value.trim()) return 'Obnovovaci kod je povinny.'
  if (!looksLikeCodeFormat(code.value)) {
    return 'Format kodu musi byt XXXXX-XXXXX.'
  }
  return ''
})
const passwordError = computed(() => {
  if (!attempted.value) return ''
  if (!password.value) return 'Nove heslo je povinne.'
  if (password.value.length < 8) return 'Heslo musi mat aspon 8 znakov.'
  return ''
})

function looksLikeCodeFormat(value) {
  return /^[A-Z0-9]{5}-[A-Z0-9]{5}$/.test(String(value || '').trim().toUpperCase())
}

async function submit() {
  attempted.value = true
  error.value = ''
  invalidCodeMessage.value = ''

  if (emailError.value || codeError.value || passwordError.value) {
    if (codeError.value.toLowerCase().includes('format')) {
      invalidCodeMessage.value = 'Zadali ste neplatny kod. Mal by mat tvar XXXXX-XXXXX.'
    }
    return
  }

  loading.value = true
  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    await http.post('/auth/password/reset', {
      email: email.value.trim(),
      code: code.value.trim().toUpperCase(),
      password: password.value,
      password_confirmation: password.value,
    }, {
      meta: { skipErrorToast: true },
    })

    await router.push({
      name: 'login',
      query: {
        email: email.value.trim(),
        reset: '1',
      },
    })
  } catch (e) {
    const backendCode = String(e?.response?.data?.error_code || '')
    const backendMessage = String(e?.response?.data?.message || '')
    const fallback = 'Heslo sa nepodarilo resetovat. Skuste to znova.'

    if (backendCode === 'PASSWORD_RESET_CODE_INVALID' || backendMessage.includes('invalid code')) {
      invalidCodeMessage.value = backendMessage || 'Zadali ste neplatny kod. Mal by mat tvar XXXXX-XXXXX.'
      return
    }

    error.value = backendMessage || e?.message || fallback
  } finally {
    loading.value = false
  }
}
</script>
