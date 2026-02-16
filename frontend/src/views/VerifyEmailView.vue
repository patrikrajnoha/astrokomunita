<template>
  <div class="verifyPage">
    <section class="verifyCard">
      <h1>Overenie emailu</h1>
      <p class="muted">
        {{ introText }}
      </p>

      <p v-if="statusMessage" class="status" :class="statusTone">{{ statusMessage }}</p>

      <div class="actions">
        <button type="button" :disabled="loading || resendLoading || !auth.isAuthed" @click="resend">
          {{ resendLoading ? 'Odosielam...' : 'Poslat overovaci email znova' }}
        </button>
        <button type="button" :disabled="loading || auth.loading" @click="refreshUser">Obnovit stav</button>
      </div>

      <p class="muted small">
        Ak je odkaz expirovany, poziadaj o novy overovaci email a otvor najnovsi odkaz.
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const resendLoading = ref(false)
const statusMessage = ref('')
const statusTone = ref('neutral')

const introText = computed(() => {
  if (auth.user?.email_verified_at) {
    return 'Email je overeny. Mozes pokracovat.'
  }

  return 'Pre pokracovanie je potrebne overit emailovu adresu.'
})

const redirectTarget = computed(() => {
  const value = route.query.redirect
  return typeof value === 'string' && value.startsWith('/') ? value : '/'
})

async function verifyFromSignedLinkIfPresent() {
  const id = route.params.id
  const hash = route.params.hash
  const expires = route.query.expires
  const signature = route.query.signature

  if (!id || !hash || !expires || !signature) {
    return
  }

  loading.value = true
  statusMessage.value = ''

  try {
    const response = await api.get(`/auth/verify-email/${id}/${hash}`, {
      params: { expires, signature },
      meta: { skipErrorToast: true },
    })

    statusTone.value = 'success'
    statusMessage.value = response?.data?.message || 'Email bol uspesne overeny.'
    await auth.fetchUser({ source: 'verify-email-link', retry: false, markBootstrap: true })
  } catch (error) {
    const message = error?.response?.data?.message || error?.userMessage || 'Overovaci odkaz je neplatny.'
    statusTone.value = 'error'
    statusMessage.value = message
  } finally {
    loading.value = false
  }
}

async function resend() {
  if (!auth.isAuthed) {
    statusTone.value = 'error'
    statusMessage.value = 'Najprv sa prihlas.'
    return
  }

  resendLoading.value = true

  try {
    const response = await api.post('/auth/email/verification-notification', {}, {
      meta: { skipErrorToast: true },
    })

    statusTone.value = 'success'
    statusMessage.value = response?.data?.message || 'Overovaci email bol odoslany.'
  } catch (error) {
    statusTone.value = 'error'
    statusMessage.value = error?.response?.data?.message || error?.userMessage || 'Nepodarilo sa odoslat overovaci email.'
  } finally {
    resendLoading.value = false
  }
}

async function refreshUser() {
  await auth.fetchUser({ source: 'verify-email-refresh', retry: true, markBootstrap: true })

  if (auth.user?.email_verified_at) {
    await router.push(redirectTarget.value)
  } else {
    statusTone.value = 'neutral'
    statusMessage.value = 'Email este nie je overeny.'
  }
}

onMounted(async () => {
  await verifyFromSignedLinkIfPresent()
})
</script>

<style scoped>
.verifyPage {
  min-height: calc(100vh - 120px);
  display: grid;
  place-items: center;
  padding: 16px;
}

.verifyCard {
  width: min(100%, 560px);
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 14px;
  padding: 18px;
  background: rgb(var(--color-bg-rgb) / 0.7);
}

.verifyCard h1 {
  margin: 0;
  font-size: 1.3rem;
}

.muted {
  margin-top: 8px;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.small {
  font-size: 0.9rem;
}

.status {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid transparent;
}

.status.success {
  border-color: rgb(34 197 94 / 0.35);
  background: rgb(34 197 94 / 0.12);
  color: rgb(21 128 61);
}

.status.error {
  border-color: rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.12);
  color: rgb(185 28 28);
}

.status.neutral {
  border-color: rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.42);
}

.actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.actions button {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  border-radius: 10px;
  padding: 8px 12px;
  background: rgb(var(--color-bg-rgb) / 0.55);
  color: inherit;
  cursor: pointer;
}

.actions button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
