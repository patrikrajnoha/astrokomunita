<template>
  <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <header>
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--color-text-secondary)]">
        Account
      </p>
      <h1 class="mt-2 text-3xl font-semibold text-[var(--color-surface)]">Settings</h1>
      <p class="mt-2 text-sm text-[var(--color-text-secondary)]">
        Manage your account details and security.
      </p>
    </header>

    <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
      <h2 class="text-lg font-semibold text-[var(--color-surface)]">Change email</h2>
      <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
        Update the email address associated with your account.
      </p>

      <div v-if="emailState.success" class="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100" role="status">
        {{ emailState.success }}
      </div>
      <div v-if="emailState.error" class="mt-4 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
        {{ emailState.error }}
      </div>

      <form class="mt-4 space-y-3" @submit.prevent="submitEmail">
        <label class="block text-sm font-medium text-[var(--color-surface)]" for="settings-email">
          New email
        </label>
        <input
          id="settings-email"
          v-model.trim="emailForm.email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm text-[var(--color-surface)] placeholder:text-[var(--color-surface)]0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          :aria-invalid="emailState.fieldError ? 'true' : 'false'"
          :aria-describedby="emailState.fieldError ? 'settings-email-error' : undefined"
          :disabled="emailState.loading"
          required
        />
        <p v-if="emailState.fieldError" id="settings-email-error" class="text-sm text-rose-200">
          {{ emailState.fieldError }}
        </p>

        <button
          type="submit"
          class="inline-flex items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.4)] hover:bg-[var(--color-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="emailState.loading || !emailForm.email"
          aria-label="Save new email"
        >
          {{ emailState.loading ? 'Saving...' : 'Save email' }}
        </button>
      </form>
    </section>

    <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
      <h2 class="text-lg font-semibold text-[var(--color-surface)]">Change password</h2>
      <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
        Set a new password for your account.
      </p>

      <div v-if="passwordState.success" class="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100" role="status">
        {{ passwordState.success }}
      </div>
      <div v-if="passwordState.error" class="mt-4 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
        {{ passwordState.error }}
      </div>

      <form class="mt-4 space-y-3" @submit.prevent="submitPassword">
        <label class="block text-sm font-medium text-[var(--color-surface)]" for="current-password">
          Current password
        </label>
        <input
          id="current-password"
          v-model="passwordForm.current"
          type="password"
          autocomplete="current-password"
          placeholder="••••••••"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm text-[var(--color-surface)] placeholder:text-[var(--color-surface)]0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          :disabled="passwordState.loading"
          required
        />

        <label class="block text-sm font-medium text-[var(--color-surface)]" for="new-password">
          New password
        </label>
        <input
          id="new-password"
          v-model="passwordForm.password"
          type="password"
          autocomplete="new-password"
          placeholder="New password"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm text-[var(--color-surface)] placeholder:text-[var(--color-surface)]0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          :disabled="passwordState.loading"
          required
          minlength="8"
        />

        <label class="block text-sm font-medium text-[var(--color-surface)]" for="confirm-password">
          Confirm new password
        </label>
        <input
          id="confirm-password"
          v-model="passwordForm.confirm"
          type="password"
          autocomplete="new-password"
          placeholder="Confirm new password"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm text-[var(--color-surface)] placeholder:text-[var(--color-surface)]0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          :disabled="passwordState.loading"
          required
          minlength="8"
        />

        <p v-if="passwordState.fieldError" class="text-sm text-rose-200">
          {{ passwordState.fieldError }}
        </p>

        <button
          type="submit"
          class="inline-flex items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.4)] hover:bg-[var(--color-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="passwordState.loading"
          aria-label="Update password"
        >
          {{ passwordState.loading ? 'Updating...' : 'Update password' }}
        </button>
      </form>
    </section>

    <section class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-6">
      <h2 class="text-lg font-semibold text-rose-100">Deactivate account</h2>
      <p class="mt-1 text-sm text-rose-200/80">
        This action permanently removes your account and signs you out.
      </p>

      <div v-if="deactivateState.error" class="mt-4 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
        {{ deactivateState.error }}
      </div>

      <div class="mt-4 space-y-3">
        <label class="block text-sm font-medium text-rose-100" for="deactivate-confirm">
          Type DEACTIVATE to confirm
        </label>
        <input
          id="deactivate-confirm"
          v-model.trim="deactivateForm.confirm"
          type="text"
          placeholder="DEACTIVATE"
          class="w-full rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm text-rose-100 placeholder:text-rose-200/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-300"
          :disabled="deactivateState.loading"
          aria-label="Confirm deactivation"
        />

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl border border-rose-400/40 bg-rose-500/20 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-300 disabled:cursor-not-allowed disabled:opacity-60"
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
import { http } from '@/lib/http'

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

    const { data } = await http.patch('/api/profile', {
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
    await http.patch('/api/profile/password', {
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
    await http.delete('/api/profile')
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
