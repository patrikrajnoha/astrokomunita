<template>
  <AuthSplitLayout>
    <template #hero>
      <AuthHeroPanel
        eyebrow=""
        title="Vitaj vo vesmírnej komunite"
        subtitle="Sleduj udalosti, zapisuj pozorovania a buduj profil medzi nadšencami astronómie."
      >
        <template #top>
          <div class="authHero__brand">
            <img src="/logo.png" alt="Astrokomunita" class="authHero__brandLogo" />
          </div>
        </template>

        <template #title>
          <span>Vitaj vo </span><span class="authHero__titleAccent">vesmírnej</span><br />
          <span>komunite</span>
        </template>
      </AuthHeroPanel>
    </template>

    <AuthFormSection
      kicker="ÚČET"
      title="Obnova hesla"
      description="Zadajte e-mail a pošleme vám obnovovací kód pre bezpečný reset hesla."
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

        <AuthAlert
          v-if="error"
          title="Kód sa nepodarilo odoslať"
          :message="error"
        />

        <AuthActions
          :back-to="{ name: 'login' }"
          back-label="Späť"
          submit-label="Pokračovať"
          loading-label="Odosielam..."
          :loading="loading"
        />

        <p class="authFootnote">
          Už máte kód?
          <RouterLink class="authInlineLink" :to="resetLink">Pokračovať na reset</RouterLink>
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
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const loading = ref(false)
const attempted = ref(false)
const error = ref('')

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
