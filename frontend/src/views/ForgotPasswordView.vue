<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow="Obnova hesla"
        title="Zabudnute heslo"
        subtitle="Zadajte e-mail k uctu a posleme vam obnovovaci kod na nastavenie noveho hesla."
      />
    </template>

    <AuthFormSection
      kicker="Obnova"
      title="Vyziadat obnovovaci kod"
      description="Zadajte e-mail uctu. Kod pride e-mailom a plati obmedzeny cas."
    >
      <form class="authForm" @submit.prevent="submit" novalidate>
        <AuthField
          v-model="email"
          label="E-mail"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          helper="Obnovovaci kod dostanete e-mailom."
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
          back-label="Spat"
          submit-label="Dalej"
          loading-label="Odosielam..."
          :loading="loading"
        />

        <AuthDivider text="alebo" />

        <p class="authFootnote">
          Uz mate kod?
          <RouterLink class="authInlineLink" :to="resetLink">Pokracovat na reset</RouterLink>
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

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinny.' : ''))
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
    error.value = e?.response?.data?.message || e?.message || 'Nepodarilo sa odoslat obnovovaci kod.'
  } finally {
    loading.value = false
  }
}
</script>
