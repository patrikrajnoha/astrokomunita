<template>
  <div class="forgotView">
    <main class="mx-auto flex min-h-dvh w-full max-w-[560px] items-start justify-center px-4 py-4 sm:py-8">
      <section class="w-full rounded-[28px] bg-[#1c2736]/55 p-4 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#0F73FF]">Zabudnute heslo</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-[#FFFFFF]">Obnova hesla</h1>
        <p class="mt-1 text-sm text-[#ABB8C9]">Posleme vam kod na e-mail.</p>

        <form class="mt-5 space-y-4" @submit.prevent="submit" novalidate>
          <label class="block">
            <span class="mb-1.5 block text-sm font-medium text-[#ABB8C9]">E-mail</span>
            <div class="flex min-h-[46px] items-center gap-2 rounded-[20px] bg-[#222E3F] px-3">
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
            <p v-else class="mt-1 text-xs text-[#ABB8C9]">Obnovovaci kod dostanete e-mailom.</p>
          </label>

          <div v-if="error" class="rounded-[18px] bg-[#EB2452]/15 px-3 py-2 text-sm text-[#EB2452]">
            {{ error }}
          </div>

          <div class="grid grid-cols-2 gap-2 pt-1">
            <RouterLink
              :to="{ name: 'login' }"
              class="inline-flex min-h-[44px] items-center justify-center rounded-[999px] bg-[#222E3F] px-4 text-sm font-medium text-[#ABB8C9] transition-colors hover:bg-[#1c2736] hover:text-[#FFFFFF]"
            >
              Spat
            </RouterLink>
            <button
              type="submit"
              class="inline-flex min-h-[44px] items-center justify-center rounded-[999px] bg-[#0F73FF] px-4 text-sm font-medium text-[#FFFFFF] transition-colors hover:bg-[#0d65e6] disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading"
            >
              {{ loading ? 'Odosielam...' : 'Dalej' }}
            </button>
          </div>

          <p class="pt-1 text-center text-sm text-[#ABB8C9]">
            Uz mate kod?
            <RouterLink class="font-medium text-[#0F73FF] hover:text-[#FFFFFF]" :to="resetLink">Pokracovat na reset</RouterLink>
          </p>
        </form>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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

<style scoped>
.forgotView {
  min-height: 100dvh;
  background: #151d28;
  overflow-x: hidden;
}
</style>