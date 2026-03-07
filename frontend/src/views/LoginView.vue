<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow="Account access"
        title="Sign in"
        subtitle="Continue to your Astrokomunita account with a clean and secure sign-in flow."
      />
    </template>

    <AuthFormSection
      kicker="Account"
      title="Welcome back"
      description="Use your account email and password to access your profile and community feed."
    >
      <form class="authForm" @submit.prevent="submit" novalidate>
        <AuthField
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
          v-model="password"
          label="Password"
          type="password"
          autocomplete="current-password"
          placeholder="Enter password"
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
            <RouterLink :to="forgotPasswordLink" class="authInlineLink">Forgot?</RouterLink>
          </template>
        </AuthField>

        <AuthAlert
          v-if="error"
          title="Unable to sign in"
          :message="error"
        />

        <AuthAlert
          v-if="isBannedState"
          title="Account blocked"
          :message="bannedDetails"
        />

        <p v-if="resetSuccessMessage" class="authField__meta">{{ resetSuccessMessage }}</p>

        <AuthActions
          :back-to="{ name: 'home' }"
          back-label="Back"
          submit-label="Sign in"
          loading-label="Signing in..."
          :loading="auth.loading"
        />

        <p class="authFootnote">
          Need an account?
          <RouterLink class="authInlineLink" :to="registerLink">Create one</RouterLink>
        </p>
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
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

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

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'Email is required.' : ''))
const passwordError = computed(() => (attempted.value && !password.value ? 'Password is required.' : ''))
const resetSuccessMessage = computed(() => (
  route.query.reset === '1' ? 'Password updated. You can sign in with your new password.' : ''
))

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

  if (reason && bannedAt) return `Reason: ${reason}. Blocked: ${bannedAt}.`
  if (reason) return `Reason: ${reason}.`
  if (bannedAt) return `Blocked: ${bannedAt}.`
  return 'This account is blocked.'
})

async function submit() {
  attempted.value = true
  error.value = ''

  if (!email.value.trim() || !password.value) {
    return
  }

  try {
    await auth.login({
      email: email.value.trim(),
      password: password.value,
    })

    if (
      !auth.isAdmin &&
      auth.user?.requires_email_verification &&
      !auth.user?.email_verified_at
    ) {
      await router.push({ name: 'settings.email', query: { redirect: redirect.value } })
      return
    }

    await router.push(redirect.value)
  } catch (e) {
    error.value = e?.response?.data?.message || e?.authError?.message || e?.message || 'Sign in failed.'
  }
}
</script>
