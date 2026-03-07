<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow="Password recovery"
        title="Reset Password"
        subtitle="Enter the reset code from your email and choose a new password to finish account recovery."
      />
    </template>

    <AuthFormSection
      kicker="Recovery"
      title="Enter code and new password"
      description="Check your inbox for the reset code, then submit it together with your new password."
    >
      <form class="authForm" @submit.prevent="submit" novalidate>
        <p v-if="prefilledEmailLabel" class="authPrefill">
          Resetting password for <strong>{{ prefilledEmailLabel }}</strong>.
        </p>

        <AuthField
          v-if="needsEmailField"
          v-model="email"
          label="Email"
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
          label="Reset code"
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
          label="New password"
          type="password"
          autocomplete="new-password"
          placeholder="Create a strong password"
          helper="Use at least 8 characters."
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
          title="Invalid reset code"
          :message="invalidCodeMessage"
        />

        <AuthAlert
          v-if="error && !invalidCodeMessage"
          title="Unable to reset password"
          :message="error"
        />

        <p v-if="sentMessage" class="authField__meta">{{ sentMessage }}</p>

        <AuthActions
          :back-to="{ name: 'forgot-password', query: emailQuery }"
          back-label="Back"
          submit-label="Next"
          loading-label="Updating..."
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
  route.query.sent === '1' ? 'We sent a reset code to your email. Enter it below.' : ''
))

const emailQuery = computed(() => (email.value.trim() ? { email: email.value.trim() } : undefined))
const emailError = computed(() => (attempted.value && !email.value.trim() ? 'Email is required.' : ''))
const codeError = computed(() => {
  if (!attempted.value) return ''
  if (!code.value.trim()) return 'Reset code is required.'
  if (!looksLikeCodeFormat(code.value)) {
    return 'Code format must look like XXXXX-XXXXX.'
  }
  return ''
})
const passwordError = computed(() => {
  if (!attempted.value) return ''
  if (!password.value) return 'New password is required.'
  if (password.value.length < 8) return 'Password must have at least 8 characters.'
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
    if (codeError.value.includes('format')) {
      invalidCodeMessage.value = 'You have entered an invalid code. It should look like XXXXX-XXXXX.'
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
    const fallback = 'Unable to reset password. Please try again.'

    if (backendCode === 'PASSWORD_RESET_CODE_INVALID' || backendMessage.includes('invalid code')) {
      invalidCodeMessage.value = backendMessage || 'You have entered an invalid code. It should look like XXXXX-XXXXX.'
      return
    }

    error.value = backendMessage || e?.message || fallback
  } finally {
    loading.value = false
  }
}
</script>
