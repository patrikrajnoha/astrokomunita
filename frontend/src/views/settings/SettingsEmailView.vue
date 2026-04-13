<template>
  <SettingsDetailShell
    title="E-mail"
    subtitle="Stav overenia, overovací kód a bezpečná zmena e-mailu."
  >
    <div class="settingsEmail">
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

      <section class="email-card">
        <h3 class="email-card-title">Overenie aktuálneho e-mailu</h3>

        <form class="settings-form" @submit.prevent="confirmEmailCode">
          <button
            id="settings-email-send"
            type="button"
            class="btn btn-primary"
            :disabled="emailState.sending || emailAccount.verified || emailAccount.secondsToResend > 0"
            @click="sendEmailCode"
          >
            {{ sendEmailCodeLabel }}
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
      </section>

      <section class="email-card">
        <h3 class="email-card-title">Zmena e-mailu</h3>

        <form class="settings-form" @submit.prevent="requestEmailChange">
          <label class="field-label" for="settings-email-new">Nový e-mail</label>
          <input
            id="settings-email-new"
            v-model.trim="emailForm.newEmail"
            type="email"
            autocomplete="email"
            placeholder="vas@priklad.sk"
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
      </section>

      <section v-if="emailAccount.pendingEmailChange" class="pending-email-box email-card">
        <p class="pending-email-title">
          Čakajúca zmena e-mailu:
          <strong>{{ emailAccount.pendingEmailChange.new_email }}</strong>
        </p>
        <p class="pending-email-hint">
          Najprv potvrď kód z aktuálneho e-mailu.
        </p>

        <button
          id="settings-email-change-send-current"
          type="button"
          class="btn btn-secondary"
          :disabled="emailState.confirmingCurrent || emailAccount.pendingEmailChange.seconds_to_resend_current > 0"
          @click="sendCurrentEmailChangeCode"
        >
          {{ sendCurrentCodeLabel }}
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
          {{ emailState.applyingNew ? 'Ukladám...' : 'Použiť nový e-mail a poslať overovací kód' }}
        </button>
      </section>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import { computed } from 'vue'
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

const sendEmailCodeLabel = computed(() => {
  if (emailState.sending) return 'Odosielam...'
  if (emailAccount.secondsToResend > 0) return `Opakovať za ${emailAccount.secondsToResend}s`
  if (emailAccount.verified) return 'Už overené'
  return 'Poslať overovací kód'
})

const sendCurrentCodeLabel = computed(() => {
  if (emailState.confirmingCurrent) return 'Odosielam...'
  if (emailAccount.pendingEmailChange?.seconds_to_resend_current > 0) {
    return `Opakovať za ${emailAccount.pendingEmailChange.seconds_to_resend_current}s`
  }
  return 'Poslať kód na aktuálny e-mail'
})
</script>

<style scoped>
.settingsEmail {
  --email-bg: #151d28;
  --email-heading: #ffffff;
  --email-muted: #abb8c9;
  --email-hover: #1c2736;
  --email-primary: #0f73ff;
  --email-secondary: #222e3f;
  --email-danger: #eb2452;
  --email-success: #73df84;

  margin-top: 0.95rem;
  display: grid;
  gap: 0.85rem;
}

.email-card {
  border: 0;
  border-radius: 1.15rem;
  background: var(--email-bg);
  padding: 0.92rem;
}

.email-card-title {
  margin: 0 0 0.68rem;
  color: var(--email-heading);
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.25;
}

.settingsEmail .email-status-row {
  margin-top: 0;
  border: 0;
  border-radius: 1.15rem;
  background: var(--email-bg);
  padding: 0.82rem 0.9rem;
}

.settingsEmail .email-value {
  margin: 0;
  color: var(--email-heading);
  font-size: 0.93rem;
  font-weight: 600;
  line-height: 1.35;
}

.settingsEmail .email-badge {
  border: 0;
  border-radius: 999px;
  padding: 0.28rem 0.64rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  text-transform: none;
}

.settingsEmail .email-badge.is-verified {
  color: var(--email-success);
  background: rgb(115 223 132 / 16%);
}

.settingsEmail .email-badge.is-unverified {
  color: #ffffff;
  background: rgb(235 36 82 / 22%);
}

.settingsEmail .settings-form {
  margin-top: 0;
  display: grid;
  gap: 0.62rem;
}

.settingsEmail .field-label {
  color: var(--email-muted);
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.35;
}

.settingsEmail .field-input {
  width: 100%;
  min-height: 3.05rem;
  border: 0;
  border-radius: 1.1rem;
  background: var(--email-hover);
  color: var(--email-heading);
  padding: 0 0.95rem;
  outline: 0;
  box-shadow: none;
  transition: background-color 150ms ease, opacity 150ms ease;
}

.settingsEmail .field-input::placeholder {
  color: rgb(171 184 201 / 68%);
}

.settingsEmail .field-input:focus-visible {
  outline: 2px solid rgb(15 115 255 / 55%);
  outline-offset: 0;
  box-shadow: none;
}

.settingsEmail .field-input:disabled {
  opacity: 0.56;
  cursor: not-allowed;
}

.settingsEmail .field-error {
  margin: 0;
  color: var(--email-danger);
  font-size: 0.82rem;
}

.settingsEmail .btn {
  appearance: none;
  width: 100%;
  min-height: 3.1rem;
  border: 0;
  border-radius: 999px;
  box-shadow: none;
  padding: 0 1rem;
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.2;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease, opacity 140ms ease;
}

.settingsEmail .btn:hover {
  transform: none;
}

.settingsEmail .btn:focus-visible {
  outline: 2px solid rgb(17 133 254 / 55%);
  outline-offset: 1px;
}

.settingsEmail .btn:disabled {
  opacity: 0.54;
  cursor: not-allowed;
}

.settingsEmail .btn-primary {
  background: var(--email-primary);
  color: #ffffff;
}

.settingsEmail .btn-primary:hover {
  background: #1185fe;
}

.settingsEmail .btn-secondary {
  background: var(--email-secondary);
  color: var(--email-muted);
}

.settingsEmail .btn-secondary:hover {
  background: var(--email-hover);
  color: var(--email-muted);
}

.settingsEmail .pending-email-box {
  margin-top: 0;
  border: 0;
  border-radius: 1.15rem;
  background: var(--email-bg);
  padding: 0.92rem;
}

.settingsEmail .pending-email-title {
  margin: 0;
  color: var(--email-heading);
  line-height: 1.45;
}

.settingsEmail .pending-email-hint {
  margin: 0.34rem 0 0.7rem;
  color: var(--email-muted);
  font-size: 0.84rem;
  line-height: 1.45;
}

@media (max-width: 520px) {
  .settingsEmail {
    gap: 0.72rem;
  }

  .email-card,
  .settingsEmail .pending-email-box,
  .settingsEmail .email-status-row {
    border-radius: 1rem;
    padding: 0.78rem;
  }

  .settingsEmail .btn,
  .settingsEmail .field-input {
    min-height: 2.95rem;
  }
}
</style>
