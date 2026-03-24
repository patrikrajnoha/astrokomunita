<template>
  <SettingsDetailShell
    title="E-mail"
    subtitle="Stav overenia, overovací kód a bezpečná zmena e-mailu."
  >
    <div class="email-status-row" data-testid="settings-email-status">
      <p class="email-value">{{ emailAccount.email || 'E-mail nie je nastavený' }}</p>
      <span class="email-badge" :class="emailAccount.verified ? 'is-verified' : 'is-unverified'">
        {{ emailAccount.verified ? 'Overený' : 'Neoverený' }}
      </span>
    </div>

    <InlineStatus
      v-if="emailState.success"
      variant="success"
      :message="emailState.success"
    />
    <InlineStatus
      v-if="emailState.error"
      variant="error"
      :message="emailState.error"
    />

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
            ? 'Odosielam...'
            : emailAccount.secondsToResend > 0
              ? `Opakovať o ${emailAccount.secondsToResend}s`
              : emailAccount.verified
                ? 'Už overené'
                : 'Odoslať overovací kód'
        }}
      </button>

      <label class="field-label" for="settings-email-code">Overovací kód</label>
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
        {{ emailState.confirming ? 'Potvrdzujem...' : 'Potvrdiť kód' }}
      </button>
    </form>

    <form class="settings-form" @submit.prevent="requestEmailChange">
      <label class="field-label" for="settings-email-new">Nový e-mail</label>
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
        {{ emailState.requestingChange ? 'Odosielam požiadavku...' : 'Požiadať o zmenu e-mailu' }}
      </button>
    </form>

    <div v-if="emailAccount.pendingEmailChange" class="pending-email-box">
      <p class="pending-email-title">
        Čakajúca zmena e-mailu:
        <strong>{{ emailAccount.pendingEmailChange.new_email }}</strong>
      </p>
      <p class="pending-email-hint">
        Najprv musíš potvrdiť kód z aktuálneho e-mailu.
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
            ? 'Odosielam...'
            : emailAccount.pendingEmailChange.seconds_to_resend_current > 0
              ? `Opakovať o ${emailAccount.pendingEmailChange.seconds_to_resend_current}s`
              : 'Odoslať kód na aktuálny e-mail'
        }}
      </button>

      <form class="settings-form" @submit.prevent="confirmCurrentEmailChangeCode">
        <label class="field-label" for="settings-email-current-code">Kód z aktuálneho e-mailu</label>
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
          {{ emailState.confirmingCurrent ? 'Potvrdzujem...' : 'Potvrdiť kód z aktuálneho e-mailu' }}
        </button>
      </form>

      <button
        id="settings-email-change-confirm-new"
        type="button"
        class="btn btn-primary"
        :disabled="emailState.applyingNew || !emailAccount.pendingEmailChange.current_email_confirmed_at"
        @click="applyNewEmailChange"
      >
        {{ emailState.applyingNew ? 'Ukladám...' : 'Použiť nový e-mail a odoslať overovací kód' }}
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
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
