<template>
  <div class="max-w-md mx-auto space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-indigo-400">Registrácia</h1>
      <p class="mt-1 text-slate-300 text-sm">
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

      <p class="text-sm text-slate-300">
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
  padding: 1rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.6);
}
.field { display: grid; gap: 0.35rem; }
.label { color: rgb(203 213 225); font-size: 0.875rem; }
.input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.4);
  color: white;
}
.input:focus { outline: none; border-color: rgb(99 102 241); }
.btn {
  width: 100%;
  padding: 0.65rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(99 102 241);
  background: rgba(99, 102, 241, 0.15);
  color: white;
}
.btn:hover { background: rgba(99, 102, 241, 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.link { color: rgb(129 140 248); }
.link:hover { text-decoration: underline; }
.alert {
  padding: 0.6rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgba(248, 113, 113, 0.35);
  background: rgba(248, 113, 113, 0.12);
  color: rgb(254, 202, 202);
  font-size: 0.875rem;
}
</style>
