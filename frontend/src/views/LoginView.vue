<template>
  <div class="loginPage">
    <div class="loginShell">
      <header class="loginHeader">
        <h1 class="loginTitle">Prihlasenie</h1>
        <p class="loginSubtitle">Prihlas sa do Astrokomunity.</p>
      </header>

      <form class="panel" @submit.prevent="submit">
        <div class="field">
          <label class="label">Email</label>
          <input v-model="email" class="input" type="email" autocomplete="email" />
        </div>

        <div class="field">
          <label class="label">Heslo</label>
          <input v-model="password" class="input" type="password" autocomplete="current-password" />
        </div>

        <p v-if="error" class="errorText">{{ error }}</p>

        <button class="actionbtn" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Prihlasujem...' : 'Prihlasit' }}
        </button>

        <p class="registerHint">
          Nemas ucet?
          <router-link class="link" :to="registerLink">Registruj sa</router-link>
        </p>
      </form>
    </div>
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
      // nech sa aj register po registracii vrati tam, kam user chcel ist
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
        this.error = e?.response?.data?.message || 'Prihlasenie zlyhalo.'
      }
    },
  },
}
</script>

<style scoped>
.loginPage {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1rem;
  background:
    radial-gradient(1000px 500px at 10% -10%, rgb(var(--color-primary-rgb) / 0.16), transparent 60%),
    radial-gradient(900px 440px at 100% 120%, rgb(var(--color-success-rgb) / 0.1), transparent 65%);
}

.loginShell {
  width: min(100%, 440px);
  display: grid;
  gap: 1rem;
}

.loginHeader {
  text-align: center;
  display: grid;
  gap: 0.35rem;
}

.loginTitle {
  margin: 0;
  font-size: clamp(1.5rem, 2.3vw, 1.9rem);
  font-weight: 700;
  color: var(--color-surface);
}

.loginSubtitle {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.95rem;
}

.panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.72), rgb(var(--color-bg-rgb) / 0.56));
  border-radius: 1.1rem;
  padding: 1.25rem;
  box-shadow: 0 20px 40px rgb(var(--color-bg-rgb) / 0.36);
  backdrop-filter: blur(8px);
  display: grid;
  gap: 0.9rem;
}

.field {
  display: grid;
  gap: 0.35rem;
}

.label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  font-weight: 600;
}

.input {
  width: 100%;
  padding: 0.72rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  outline: none;
  transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease;
}

.input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.errorText {
  color: var(--color-danger);
  font-size: 0.86rem;
  margin: 0.1rem 0 0;
}

.actionbtn {
  width: 100%;
  padding: 0.78rem 0.95rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: linear-gradient(180deg, rgb(var(--color-primary-rgb) / 0.24), rgb(var(--color-primary-rgb) / 0.15));
  color: var(--color-surface);
  font-weight: 600;
  transition: transform 120ms ease, background-color 120ms ease;
}

.actionbtn:hover {
  background: linear-gradient(180deg, rgb(var(--color-primary-rgb) / 0.3), rgb(var(--color-primary-rgb) / 0.2));
}

.actionbtn:active {
  transform: translateY(1px);
}

.actionbtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.registerHint {
  margin: 0.2rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.88rem;
  text-align: center;
}

.link {
  color: var(--color-primary);
  font-weight: 600;
}

.link:hover {
  color: var(--color-surface);
}

@media (max-width: 480px) {
  .loginPage {
    padding: 0.75rem;
  }

  .panel {
    padding: 1rem;
    border-radius: 1rem;
  }
}
</style>
