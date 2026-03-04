<template>
  <SettingsDetailShell
    title="Email"
    subtitle="Verification status, verification code, and secure email change flow."
  >
    <div class="email-status-row" data-testid="settings-email-status">
      <p class="email-value">{{ emailAccount.email || 'No email set' }}</p>
      <span class="email-badge" :class="emailAccount.verified ? 'is-verified' : 'is-unverified'">
        {{ emailAccount.verified ? 'Verified' : 'Not verified' }}
      </span>
    </div>

    <div v-if="emailState.success" class="status status-success" role="status">
      {{ emailState.success }}
    </div>
    <div v-if="emailState.error" class="status status-error" role="alert">
      {{ emailState.error }}
    </div>

    <form class="settings-form" @submit.prevent="confirmEmailCode">
      <button
        id="settings-email-send"
        type="button"
        class="btn btn-primary"
        :disabled="emailState.sending || emailAccount.verified || emailAccount.secondsToResend > 0"
        @click="sendEmailCode"
      >
        {{
          emailState.sending
            ? 'Sending...'
            : emailAccount.secondsToResend > 0
              ? `Resend in ${emailAccount.secondsToResend}s`
              : emailAccount.verified
                ? 'Already verified'
                : 'Send verification code'
        }}
      </button>

      <label class="field-label" for="settings-email-code">Verification code</label>
      <input
        id="settings-email-code"
        v-model.trim="emailForm.code"
        type="text"
        autocomplete="one-time-code"
        placeholder="12345-67890"
        class="field-input"
        :disabled="emailState.confirming || emailAccount.verified"
      />
      <p v-if="emailState.fieldError" class="field-error">{{ emailState.fieldError }}</p>

      <button
        id="settings-email-confirm"
        type="submit"
        class="btn btn-primary"
        :disabled="emailState.confirming || emailAccount.verified || !emailForm.code"
      >
        {{ emailState.confirming ? 'Confirming...' : 'Confirm code' }}
      </button>
    </form>

    <form class="settings-form" @submit.prevent="requestEmailChange">
      <label class="field-label" for="settings-email-new">New email</label>
      <input
        id="settings-email-new"
        v-model.trim="emailForm.newEmail"
        type="email"
        autocomplete="email"
        placeholder="you@example.com"
        class="field-input"
        :disabled="emailState.requestingChange"
      />
      <button
        id="settings-email-change-request"
        type="submit"
        class="btn btn-primary"
        :disabled="emailState.requestingChange || !emailForm.newEmail"
      >
        {{ emailState.requestingChange ? 'Requesting...' : 'Request email change' }}
      </button>
    </form>

    <div v-if="emailAccount.pendingEmailChange" class="pending-email-box">
      <p class="pending-email-title">
        Pending email change:
        <strong>{{ emailAccount.pendingEmailChange.new_email }}</strong>
      </p>
      <p class="pending-email-hint">
        Current email must be confirmed before applying the new one.
      </p>

      <button
        id="settings-email-change-send-current"
        type="button"
        class="btn btn-secondary"
        :disabled="emailState.confirmingCurrent || emailAccount.pendingEmailChange.seconds_to_resend_current > 0"
        @click="sendCurrentEmailChangeCode"
      >
        {{
          emailState.confirmingCurrent
            ? 'Sending...'
            : emailAccount.pendingEmailChange.seconds_to_resend_current > 0
              ? `Resend in ${emailAccount.pendingEmailChange.seconds_to_resend_current}s`
              : 'Send code to current email'
        }}
      </button>

      <form class="settings-form" @submit.prevent="confirmCurrentEmailChangeCode">
        <label class="field-label" for="settings-email-current-code">Current email confirmation code</label>
        <input
          id="settings-email-current-code"
          v-model.trim="emailForm.currentCode"
          type="text"
          autocomplete="one-time-code"
          placeholder="12345-67890"
          class="field-input"
          :disabled="emailState.confirmingCurrent"
        />
        <button
          id="settings-email-change-confirm-current"
          type="submit"
          class="btn btn-secondary"
          :disabled="emailState.confirmingCurrent || !emailForm.currentCode"
        >
          {{ emailState.confirmingCurrent ? 'Confirming...' : 'Confirm current email code' }}
        </button>
      </form>

      <button
        id="settings-email-change-confirm-new"
        type="button"
        class="btn btn-primary"
        :disabled="emailState.applyingNew || !emailAccount.pendingEmailChange.current_email_confirmed_at"
        @click="applyNewEmailChange"
      >
        {{ emailState.applyingNew ? 'Applying...' : 'Apply new email and send verification code' }}
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const {
  applyNewEmailChange,
  confirmCurrentEmailChangeCode,
  confirmEmailCode,
  emailAccount,
  emailForm,
  emailState,
  requestEmailChange,
  sendCurrentEmailChangeCode,
  sendEmailCode,
} = useSettingsContext()
</script>
