<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow="Password recovery"
        title="Forgot Password"
        subtitle="Enter your account email and we will send a reset code to help you set a new password."
      />
    </template>

    <AuthFormSection
      kicker="Recovery"
      title="Request a reset code"
      description="Type your account email below. The code arrives by email and works for a limited time."
    >
      <form class="authForm" @submit.prevent="submit" novalidate>
        <AuthField
          v-model="email"
          label="Email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          helper="You will receive a reset code by email."
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

        <AuthAlert v-if="error" title="Unable to continue" :message="error" />

        <AuthActions
          :back-to="{ name: 'login' }"
          back-label="Back"
          submit-label="Next"
          loading-label="Sending..."
          :loading="loading"
        />

        <AuthDivider text="or" />

        <p class="authFootnote">
          Already have a code?
          <RouterLink class="authInlineLink" :to="resetLink">Continue to reset</RouterLink>
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

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'Email is required.' : ''))
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
    error.value = e?.response?.data?.message || e?.message || 'Unable to send reset code.'
  } finally {
    loading.value = false
  }
}
</script>
