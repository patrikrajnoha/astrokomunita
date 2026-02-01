<template>
  <div class="max-w-md mx-auto min-h-[70vh] flex flex-col justify-center space-y-6">
    <header>
      <h1 class="text-2xl font-semibold text-[var(--color-surface)]">Registrácia</h1>
      <p class="mt-1 text-[var(--color-text-secondary)] text-sm">
        Vytvor si účet (dev režim).
      </p>
    </header>

    <section class="card space-y-4">
      <div v-if="formError" class="alert">
        {{ formError }}
      </div>

      <label class="field">
        <span class="label">Meno</span>
        <input v-model.trim="name" type="text" class="input" autocomplete="name" />
      </label>

      <label class="field">
        <span class="label">Email</span>
        <input v-model.trim="email" type="email" class="input" autocomplete="email" />
      </label>

      <label class="field">
        <span class="label">Heslo</span>
        <input v-model="password" type="password" class="input" autocomplete="new-password" />
      </label>

      <label class="field">
        <span class="label">Potvrdenie hesla</span>
        <input v-model="passwordConfirmation" type="password" class="input" autocomplete="new-password" />
      </label>

      <button class="btn" :disabled="auth.loading" @click="submit">
        {{ auth.loading ? 'Registrujem…' : 'Zaregistrovať' }}
      </button>

      <p class="text-sm text-[var(--color-text-secondary)]">
        Už máš účet?
        <router-link class="link" :to="loginLink">Prihlás sa</router-link>
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const name = ref('Patrik')
const email = ref('patrik2@test.local')
const password = ref('password123')
const passwordConfirmation = ref('password123')
const formError = ref(null)

const redirect = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') ? r : '/'
})

const loginLink = computed(() => ({
  name: 'login',
  query: { redirect: redirect.value },
}))

const submit = async () => {
  formError.value = null
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.push(redirect.value)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errors = e?.response?.data?.errors
    if (errors) {
      const firstKey = Object.keys(errors)[0]
      formError.value = errors[firstKey]?.[0] || msg || 'Registrácia zlyhala.'
    } else {
      formError.value = msg || 'Registrácia zlyhala.'
    }
  }
}
</script>

<style scoped>
.card {
  padding: 1.25rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.6);
  box-shadow: 0 18px 36px rgb(var(--color-bg-rgb) / 0.35);
}
.field { display: grid; gap: 0.35rem; }
.label { color: var(--color-text-secondary); font-size: 0.85rem; }
.input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
}
.input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 2px rgb(var(--color-primary-rgb) / 0.2); }
.btn {
  width: 100%;
  padding: 0.7rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: var(--color-surface);
  font-weight: 600;
}
.btn:hover { background: rgb(var(--color-primary-rgb) / 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.link { color: var(--color-primary); }
.link:hover { text-decoration: underline; }
.alert {
  padding: 0.6rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.35);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
  font-size: 0.875rem;
}
</style>
