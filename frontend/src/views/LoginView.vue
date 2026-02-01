<template>
  <div class="max-w-md mx-auto min-h-[70vh] flex flex-col justify-center space-y-6">
    <header class="text-center">
      <h1 class="text-2xl font-semibold text-[var(--color-surface)]">Prihlásenie</h1>
      <p class="mt-2 text-[var(--color-text-secondary)] text-sm">Prihlás sa do Astrokomunity.</p>
    </header>

    <section class="panel space-y-4">
      <div>
        <label class="label">Email</label>
        <input v-model="email" class="input" type="email" autocomplete="email" />
      </div>

      <div>
        <label class="label">Heslo</label>
        <input v-model="password" class="input" type="password" autocomplete="current-password" />
      </div>

      <p v-if="error" class="text-[var(--color-danger)] text-sm">{{ error }}</p>

      <button class="actionbtn w-full" :disabled="auth.loading" @click="submit">
        {{ auth.loading ? 'Prihlasujem…' : 'Prihlásiť' }}
      </button>

      <p class="text-[var(--color-text-secondary)] text-sm text-center">
        Nemáš účet?
        <router-link class="link" :to="registerLink">Registruj sa</router-link>
      </p>
    </section>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'LoginView',
  data() {
    return {
      email: '',
      password: '',
      error: null,
    }
  },
  computed: {
    auth() {
      return useAuthStore()
    },
    redirect() {
      const r = this.$route.query.redirect
      return typeof r === 'string' && r.startsWith('/') ? r : '/'
    },
    registerLink() {
      // nech sa aj register po registrácii vráti tam, kam user chcel ísť
      return { name: 'register', query: { redirect: this.redirect } }
    },
  },
  methods: {
    async submit() {
      this.error = null
      try {
        await this.auth.login({ email: this.email, password: this.password })
        this.$router.push(this.redirect)
      } catch (e) {
        this.error = e?.response?.data?.message || 'Prihlásenie zlyhalo.'
      }
    },
  },
}
</script>

<style scoped>
.panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.6);
  border-radius: 1.25rem;
  padding: 1.25rem;
  box-shadow: 0 18px 36px rgb(var(--color-bg-rgb) / 0.35);
}
.label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  margin-bottom: 0.35rem;
}
.input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  outline: none;
}
.input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgb(var(--color-primary-rgb) / 0.2);
}
.actionbtn {
  padding: 0.7rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: var(--color-surface);
  font-weight: 600;
}
.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.25);
}
.actionbtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.link {
  color: var(--color-primary);
}
.link:hover {
  color: var(--color-surface);
}
</style>
