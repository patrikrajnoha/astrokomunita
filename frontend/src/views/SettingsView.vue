<template>
  <div class="settings-page">
    <div class="settings-glow settings-glow-1" aria-hidden="true"></div>
    <div class="settings-glow settings-glow-2" aria-hidden="true"></div>

    <header class="settings-header">
      <p class="eyebrow">Account</p>
      <h1 class="title">Settings</h1>
      <p class="subtitle">Manage your account details and security.</p>
    </header>

    <section class="settings-card">
      <h2 class="card-title">Change email</h2>
      <p class="card-subtitle">Update the email address associated with your account.</p>

      <div v-if="emailState.success" class="status status-success" role="status">
        {{ emailState.success }}
      </div>
      <div v-if="emailState.error" class="status status-error" role="alert">
        {{ emailState.error }}
      </div>

      <form class="settings-form" @submit.prevent="submitEmail">
        <label class="field-label" for="settings-email">New email</label>
        <input
          id="settings-email"
          v-model.trim="emailForm.email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          class="field-input"
          :aria-invalid="emailState.fieldError ? 'true' : 'false'"
          :aria-describedby="emailState.fieldError ? 'settings-email-error' : undefined"
          :disabled="emailState.loading"
          required
        />
        <p v-if="emailState.fieldError" id="settings-email-error" class="field-error">
          {{ emailState.fieldError }}
        </p>

        <button
          type="submit"
          class="btn btn-primary"
          :disabled="emailState.loading || !emailForm.email"
          aria-label="Save new email"
        >
          {{ emailState.loading ? 'Saving...' : 'Save email' }}
        </button>
      </form>
    </section>

    <section class="settings-card">
      <h2 class="card-title">Change password</h2>
      <p class="card-subtitle">Set a new password for your account.</p>

      <div v-if="passwordState.success" class="status status-success" role="status">
        {{ passwordState.success }}
      </div>
      <div v-if="passwordState.error" class="status status-error" role="alert">
        {{ passwordState.error }}
      </div>

      <form class="settings-form" @submit.prevent="submitPassword">
        <label class="field-label" for="current-password">Current password</label>
        <input
          id="current-password"
          v-model="passwordForm.current"
          type="password"
          autocomplete="current-password"
          placeholder="********"
          class="field-input"
          :disabled="passwordState.loading"
          required
        />

        <label class="field-label" for="new-password">New password</label>
        <input
          id="new-password"
          v-model="passwordForm.password"
          type="password"
          autocomplete="new-password"
          placeholder="New password"
          class="field-input"
          :disabled="passwordState.loading"
          required
          minlength="8"
        />

        <label class="field-label" for="confirm-password">Confirm new password</label>
        <input
          id="confirm-password"
          v-model="passwordForm.confirm"
          type="password"
          autocomplete="new-password"
          placeholder="Confirm new password"
          class="field-input"
          :disabled="passwordState.loading"
          required
          minlength="8"
        />

        <p v-if="passwordState.fieldError" class="field-error">
          {{ passwordState.fieldError }}
        </p>

        <button
          type="submit"
          class="btn btn-primary"
          :disabled="passwordState.loading"
          aria-label="Update password"
        >
          {{ passwordState.loading ? 'Updating...' : 'Update password' }}
        </button>
      </form>
    </section>

    <section class="settings-card settings-card-danger">
      <h2 class="card-title">Deactivate account</h2>
      <p class="card-subtitle">This action permanently removes your account and signs you out.</p>

      <div v-if="deactivateState.error" class="status status-error" role="alert">
        {{ deactivateState.error }}
      </div>

      <div class="settings-form">
        <label class="field-label" for="deactivate-confirm">Type DEACTIVATE to confirm</label>
        <input
          id="deactivate-confirm"
          v-model.trim="deactivateForm.confirm"
          type="text"
          placeholder="DEACTIVATE"
          class="field-input field-input-danger"
          :disabled="deactivateState.loading"
          aria-label="Confirm deactivation"
        />

        <button
          type="button"
          class="btn btn-danger"
          :disabled="deactivateState.loading || deactivateForm.confirm !== 'DEACTIVATE'"
          aria-label="Deactivate account"
          @click="submitDeactivate"
        >
          {{ deactivateState.loading ? 'Deactivating...' : 'Deactivate account' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

const auth = useAuthStore()
const router = useRouter()

const emailForm = reactive({
  email: '',
  name: '',
})

const emailState = reactive({
  loading: false,
  success: '',
  error: '',
  fieldError: '',
})

const passwordForm = reactive({
  current: '',
  password: '',
  confirm: '',
})

const passwordState = reactive({
  loading: false,
  success: '',
  error: '',
  fieldError: '',
})

const deactivateForm = reactive({
  confirm: '',
})

const deactivateState = reactive({
  loading: false,
  error: '',
})

const extractFirstError = (errorsObj, field) => {
  const value = errorsObj?.[field]
  return Array.isArray(value) && value.length ? String(value[0]) : ''
}

const resetEmailState = () => {
  emailState.success = ''
  emailState.error = ''
  emailState.fieldError = ''
}

const resetPasswordState = () => {
  passwordState.success = ''
  passwordState.error = ''
  passwordState.fieldError = ''
}

const submitEmail = async () => {
  resetEmailState()

  if (!auth.user) {
    emailState.error = 'You are not signed in.'
    return
  }

  if (!emailForm.email) {
    emailState.fieldError = 'Email is required.'
    return
  }

  if (!emailForm.name) {
    emailForm.name = auth.user.name || ''
  }

  if (!emailForm.name) {
    emailState.error = 'Your profile name is missing.'
    return
  }

  emailState.loading = true

  try {
    await auth.csrf()

    const { data } = await http.patch('/profile', {
      name: emailForm.name,
      email: emailForm.email,
    })

    auth.user = data
    emailState.success = 'Email updated.'
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 422 && data?.errors) {
      emailState.fieldError =
        extractFirstError(data.errors, 'email') || 'Check the highlighted field.'
    } else {
      emailState.error = data?.message || 'Email update failed.'
    }
  } finally {
    emailState.loading = false
  }
}

const submitPassword = async () => {
  resetPasswordState()

  if (!auth.user) {
    passwordState.error = 'You are not signed in.'
    return
  }

  if (!passwordForm.current || !passwordForm.password || !passwordForm.confirm) {
    passwordState.fieldError = 'All fields are required.'
    return
  }

  if (passwordForm.password.length < 8) {
    passwordState.fieldError = 'Password must be at least 8 characters.'
    return
  }

  if (passwordForm.password !== passwordForm.confirm) {
    passwordState.fieldError = 'Passwords do not match.'
    return
  }

  passwordState.loading = true

  try {
    await auth.csrf()
    await http.patch('/profile/password', {
      current_password: passwordForm.current,
      password: passwordForm.password,
      password_confirmation: passwordForm.confirm,
    })

    passwordForm.current = ''
    passwordForm.password = ''
    passwordForm.confirm = ''
    passwordState.success = 'Password updated.'
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 422 && data?.errors) {
      passwordState.fieldError =
        extractFirstError(data.errors, 'current_password') ||
        extractFirstError(data.errors, 'password') ||
        'Check the highlighted fields.'
    } else {
      passwordState.error = data?.message || 'Password update failed.'
    }
  } finally {
    passwordState.loading = false
  }
}

const submitDeactivate = async () => {
  deactivateState.error = ''
  deactivateState.loading = true

  try {
    if (!auth.user) {
      throw new Error('You are not signed in.')
    }

    await auth.csrf()
    await http.delete('/profile')
    await auth.logout()
    router.push({ name: 'login' })
  } catch (e) {
    const data = e?.response?.data
    deactivateState.error = data?.message || e?.message || 'Account deactivation failed.'
  } finally {
    deactivateState.loading = false
  }
}

onMounted(async () => {
  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (auth.user) {
    emailForm.email = auth.user.email || ''
    emailForm.name = auth.user.name || ''
  }
})
</script>

<style scoped>
.settings-page {
  position: relative;
  width: 100%;
  max-width: 860px;
  margin: 0 auto;
  display: grid;
  gap: 1.25rem;
}

.settings-header {
  position: relative;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 1.25rem;
  padding: 1.4rem 1.25rem;
  background:
    linear-gradient(145deg, rgb(var(--color-bg-rgb) / 0.88), rgb(var(--color-bg-rgb) / 0.64)),
    radial-gradient(circle at top right, rgb(var(--color-primary-rgb) / 0.2), transparent 60%);
  box-shadow: 0 24px 60px rgb(0 0 0 / 0.26);
}

.eyebrow {
  margin: 0;
  font-size: 0.72rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.title {
  margin: 0.35rem 0 0;
  font-size: clamp(1.8rem, 4vw, 2.35rem);
  line-height: 1.08;
  color: var(--color-surface);
}

.subtitle {
  margin: 0.6rem 0 0;
  font-size: 0.95rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.settings-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 1.15rem;
  padding: 1.2rem;
  background:
    linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.82), rgb(var(--color-bg-rgb) / 0.58));
  box-shadow: 0 16px 42px rgb(0 0 0 / 0.2);
  backdrop-filter: blur(10px);
}

.settings-card-danger {
  border-color: rgb(244 63 94 / 0.35);
  background:
    linear-gradient(165deg, rgb(127 29 29 / 0.35), rgb(var(--color-bg-rgb) / 0.72));
}

.card-title {
  margin: 0;
  font-size: 1.05rem;
  color: var(--color-surface);
  font-weight: 700;
}

.card-subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.92rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.settings-form {
  margin-top: 0.95rem;
  display: grid;
  gap: 0.7rem;
}

.field-label {
  font-size: 0.86rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
  font-weight: 600;
}

.field-input {
  width: 100%;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.75rem;
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  padding: 0.62rem 0.85rem;
  outline: none;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.field-input::placeholder {
  color: rgb(var(--color-text-secondary-rgb) / 0.58);
}

.field-input:focus-visible {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.18);
}

.field-input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.field-input-danger {
  border-color: rgb(251 113 133 / 0.5);
}

.field-input-danger:focus-visible {
  border-color: rgb(251 113 133 / 0.9);
  box-shadow: 0 0 0 3px rgb(251 113 133 / 0.2);
}

.field-error {
  margin: 0;
  color: rgb(254 205 211);
  font-size: 0.88rem;
}

.status {
  margin-top: 0.9rem;
  border-radius: 0.75rem;
  border: 1px solid transparent;
  padding: 0.68rem 0.82rem;
  font-size: 0.88rem;
}

.status-success {
  border-color: rgb(16 185 129 / 0.45);
  background: rgb(5 150 105 / 0.12);
  color: rgb(209 250 229);
}

.status-error {
  border-color: rgb(251 113 133 / 0.45);
  background: rgb(225 29 72 / 0.14);
  color: rgb(255 228 230);
}

.btn {
  appearance: none;
  border-radius: 0.78rem;
  border: 1px solid transparent;
  padding: 0.58rem 0.95rem;
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.2;
  cursor: pointer;
  transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}

.btn:hover {
  transform: translateY(-1px);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-primary {
  color: var(--color-surface);
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  background: linear-gradient(
    145deg,
    rgb(var(--color-primary-rgb) / 0.24),
    rgb(var(--color-bg-rgb) / 0.7)
  );
  box-shadow: 0 10px 28px rgb(var(--color-primary-rgb) / 0.2);
}

.btn-primary:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.62);
  background: linear-gradient(
    145deg,
    rgb(var(--color-primary-rgb) / 0.34),
    rgb(var(--color-bg-rgb) / 0.64)
  );
}

.btn-danger {
  color: rgb(255 228 230);
  border-color: rgb(251 113 133 / 0.55);
  background: linear-gradient(145deg, rgb(190 24 93 / 0.38), rgb(127 29 29 / 0.35));
  box-shadow: 0 10px 26px rgb(190 24 93 / 0.18);
}

.btn-danger:hover {
  border-color: rgb(251 113 133 / 0.8);
  background: linear-gradient(145deg, rgb(225 29 72 / 0.45), rgb(127 29 29 / 0.4));
}

.settings-glow {
  position: absolute;
  z-index: -1;
  border-radius: 999px;
  filter: blur(36px);
  pointer-events: none;
}

.settings-glow-1 {
  top: -52px;
  right: -10px;
  width: 220px;
  height: 220px;
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.settings-glow-2 {
  bottom: -45px;
  left: -12px;
  width: 160px;
  height: 160px;
  background: rgb(244 63 94 / 0.2);
}

@media (max-width: 640px) {
  .settings-page {
    gap: 1rem;
  }

  .settings-header,
  .settings-card {
    padding: 1rem;
    border-radius: 1rem;
  }
}
</style>
