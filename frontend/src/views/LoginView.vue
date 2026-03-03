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
        <div v-if="isBannedState" class="bannedNotice">
          <p class="bannedTitle">Tento ucet je zablokovany.</p>
          <p v-if="bannedReason" class="bannedDetail"><strong>Dovod:</strong> {{ bannedReason }}</p>
          <p v-if="bannedAtLabel" class="bannedDetail"><strong>Zablokovane:</strong> {{ bannedAtLabel }}</p>
        </div>

        <button class="actionbtn ui-pill ui-pill--primary ui-pill--full" type="submit" :disabled="auth.loading">
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
    isBannedState() {
      return this.auth.error?.type === 'banned'
    },
    bannedReason() {
      return this.auth.error?.reason || ''
    },
    bannedAtLabel() {
      const value = this.auth.error?.bannedAt
      if (!value) return ''
      const date = new Date(value)
      if (Number.isNaN(date.getTime())) return String(value)
      return date.toLocaleString()
    },
  },
  methods: {
    async submit() {
      this.error = null
      try {
        await this.auth.login({ email: this.email, password: this.password })
        if (
          !this.auth.isAdmin &&
          this.auth.user?.requires_email_verification &&
          !this.auth.user?.email_verified_at
        ) {
          this.$router.push({ name: 'settings', query: { section: 'email', redirect: this.redirect } })
          return
        }
        this.$router.push(this.redirect)
      } catch (e) {
        this.error = e?.response?.data?.message || e?.authError?.message || e?.message || 'Prihlasenie zlyhalo.'
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
  background: transparent;
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
  color: var(--text-primary);
}

.loginSubtitle {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.95rem;
}

.panel {
  border: 1px solid var(--border);
  background: var(--bg-surface);
  border-radius: 1rem;
  padding: 1.25rem;
  box-shadow: 0 20px 40px rgb(var(--bg-app-rgb) / 0.26);
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
  color: var(--text-secondary);
  font-weight: 600;
}

.input {
  width: 100%;
  padding: 0.72rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.34);
  color: var(--text-primary);
  outline: none;
  transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease;
}

.input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.22);
  background: rgb(var(--bg-app-rgb) / 0.5);
}

.errorText {
  color: var(--primary-active);
  font-size: 0.86rem;
  margin: 0.1rem 0 0;
}

.bannedNotice {
  border: 1px solid var(--primary-active);
  background: rgb(var(--primary-active-rgb) / 0.12);
  border-radius: 0.9rem;
  padding: 0.65rem 0.75rem;
  display: grid;
  gap: 0.25rem;
}

.bannedTitle {
  margin: 0;
  color: var(--primary-active);
  font-weight: 700;
  font-size: 0.9rem;
}

.bannedDetail {
  margin: 0;
  color: var(--text-primary);
  font-size: 0.82rem;
}

.actionbtn {
  width: 100%;
}

.registerHint {
  margin: 0.2rem 0 0;
  color: var(--text-secondary);
  font-size: 0.88rem;
  text-align: center;
}

.link {
  color: var(--primary);
  font-weight: 600;
}

.link:hover {
  color: var(--text-primary);
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
