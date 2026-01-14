<template>
  <div class="max-w-md mx-auto space-y-6">
    <header class="text-center">
      <h1 class="text-2xl font-extrabold text-white">Prihlásenie</h1>
      <p class="mt-2 text-slate-300 text-sm">Prihlás sa do Astrokomunity.</p>
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

      <p v-if="error" class="text-red-200 text-sm">{{ error }}</p>

      <button class="actionbtn w-full" :disabled="auth.loading" @click="submit">
        {{ auth.loading ? 'Prihlasujem…' : 'Prihlásiť' }}
      </button>

      <p class="text-slate-300 text-sm text-center">
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
  border: 1px solid rgb(51 65 85);
  background: rgba(2, 6, 23, 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
}
.label {
  display: block;
  font-size: 0.8rem;
  color: rgb(203 213 225);
  margin-bottom: 0.35rem;
}
.input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.35);
  color: rgb(226 232 240);
  outline: none;
}
.input:focus {
  border-color: rgb(99 102 241);
}
.actionbtn {
  padding: 0.7rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(99 102 241);
  background: rgba(99, 102, 241, 0.15);
  color: white;
}
.actionbtn:hover {
  background: rgba(99, 102, 241, 0.25);
}
.actionbtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.link {
  color: rgb(199, 210, 254);
}
.link:hover {
  color: white;
}
</style>
