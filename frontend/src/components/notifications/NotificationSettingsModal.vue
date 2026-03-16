<template>
  <teleport to="body">
    <transition name="prefs-fade">
      <div v-if="open" class="prefs-backdrop" @click.self="close">
        <transition name="prefs-pop">
          <section v-if="open" class="prefs-card" role="dialog" aria-modal="true" aria-labelledby="prefs-title">
            <header class="prefs-header">
              <h2 id="prefs-title" class="prefs-title">Nastavenia notifikácií</h2>
              <button type="button" class="prefs-close" aria-label="Zavriet" @click="close">
                <svg viewBox="0 0 20 20" fill="none">
                  <path d="M5 5l10 10" />
                  <path d="M15 5 5 15" />
                </svg>
              </button>
            </header>

            <p class="prefs-helper">
              Vyberte typy notifikácií, ktoré budete dostávať o svojich aktivitách, záujmoch a odporúčaniach.
            </p>
            <p class="prefs-helper">Vyberte, ktoré notifikácie chcete vidieť a ktoré nie.</p>

            <div v-if="error" class="prefs-error" role="alert">{{ error }}</div>

            <div v-if="loading" class="prefs-loading">Načítavam...</div>
            <div v-else class="prefs-list">
              <label
                v-for="item in notificationTypes"
                :key="item.key"
                class="prefs-row"
                :for="`in-app-${item.key}`"
              >
                <span class="prefs-label">{{ item.label }}</span>
                <input
                  :id="`in-app-${item.key}`"
                  v-model="form.in_app[item.key]"
                  class="prefs-checkbox"
                  type="checkbox"
                  :disabled="saving"
                />
              </label>

              <label class="prefs-row" for="email-enabled">
                <span class="prefs-label">Posielať aj na email</span>
                <input
                  id="email-enabled"
                  v-model="form.email_enabled"
                  class="prefs-checkbox"
                  type="checkbox"
                  :disabled="saving"
                />
              </label>
            </div>

            <footer class="prefs-actions">
              <button type="button" class="prefs-btn prefs-btn-secondary" :disabled="saving" @click="close">
                Zrušiť
              </button>
              <button type="button" class="prefs-btn prefs-btn-primary" :disabled="loading || saving" @click="save">
                {{ saving ? 'Ukladám...' : 'Uložiť' }}
              </button>
            </footer>
          </section>
        </transition>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  ALL_NOTIFICATION_PREFERENCE_KEYS,
  buildNotificationPreferenceMap,
  normalizeNotificationPreferenceMap,
} from '@/constants/notificationPreferences'
import { getNotificationPreferences, updateNotificationPreferences } from '@/services/notificationPreferences'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'saved'])

const auth = useAuthStore()
const toast = useToast()

const notificationTypes = ALL_NOTIFICATION_PREFERENCE_KEYS.map((key) => ({
  key,
  label: key,
}))

const defaultInApp = () => buildNotificationPreferenceMap(true)

const form = reactive({
  in_app: defaultInApp(),
  email_enabled: false,
})

const state = reactive({
  loading: false,
  saving: false,
  error: '',
})

const close = () => emit('close')

const normalizeInApp = (raw) => {
  return normalizeNotificationPreferenceMap(raw, true)
}

const load = async () => {
  state.loading = true
  state.error = ''

  try {
    const response = await getNotificationPreferences()
    const payload = response?.data || {}
    form.in_app = normalizeInApp(payload.in_app)
    form.email_enabled = Boolean(payload.email_enabled)
  } catch (err) {
    state.error = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa načítať nastavenia.'
  } finally {
    state.loading = false
  }
}

const save = async () => {
  if (state.loading || state.saving) return
  state.saving = true
  state.error = ''

  try {
    await auth.csrf()
    await updateNotificationPreferences({
      in_app: { ...form.in_app },
      email_enabled: Boolean(form.email_enabled),
    })
    toast.success('Nastavenia notifikácií boli uložené.')
    emit('saved')
    close()
  } catch (err) {
    state.error = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa uložiť nastavenia.'
  } finally {
    state.saving = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    load()
  },
)
</script>

<style scoped>
.prefs-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(0 0 0 / 0.64);
}

.prefs-card {
  width: min(540px, 100%);
  border-radius: 1rem;
  border: 1px solid rgb(48 48 48);
  background: rgb(8 8 8);
  color: rgb(247 247 247);
  padding: 1rem;
  box-shadow: 0 24px 52px rgb(0 0 0 / 0.42);
}

.prefs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
}

.prefs-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 800;
}

.prefs-close {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid rgb(58 58 58);
  background: transparent;
  color: rgb(209 209 209);
}

.prefs-close svg {
  width: 1rem;
  height: 1rem;
  margin: 0 auto;
  stroke: currentColor;
  stroke-width: 1.8;
}

.prefs-helper {
  margin: 0.55rem 0 0;
  font-size: 0.88rem;
  line-height: 1.4;
  color: rgb(172 172 172);
}

.prefs-error {
  margin-top: 0.85rem;
  border: 1px solid rgb(190 24 93 / 0.6);
  background: rgb(136 19 55 / 0.22);
  color: rgb(253 164 175);
  border-radius: 0.75rem;
  padding: 0.62rem 0.75rem;
  font-size: 0.84rem;
}

.prefs-loading {
  margin-top: 0.85rem;
  font-size: 0.84rem;
  color: rgb(175 175 175);
}

.prefs-list {
  margin-top: 0.9rem;
  display: grid;
  gap: 0.4rem;
}

.prefs-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border: 1px solid rgb(31 31 31);
  border-radius: 0.75rem;
  background: rgb(12 12 12);
  padding: 0.62rem 0.7rem;
}

.prefs-label {
  font-size: 0.86rem;
  color: rgb(227 227 227);
  line-height: 1.3;
}

.prefs-checkbox {
  width: 1rem;
  height: 1rem;
  accent-color: rgb(255 255 255);
}

.prefs-actions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.prefs-btn {
  min-height: 2.1rem;
  border-radius: 0.68rem;
  padding: 0.46rem 0.82rem;
  font-size: 0.84rem;
  font-weight: 700;
}

.prefs-btn-secondary {
  border: 1px solid rgb(60 60 60);
  background: transparent;
  color: rgb(214 214 214);
}

.prefs-btn-primary {
  border: 1px solid rgb(89 89 89);
  background: rgb(235 235 235);
  color: rgb(11 11 11);
}

.prefs-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.prefs-fade-enter-active,
.prefs-fade-leave-active {
  transition: opacity 160ms ease;
}

.prefs-fade-enter-from,
.prefs-fade-leave-to {
  opacity: 0;
}

.prefs-pop-enter-active,
.prefs-pop-leave-active {
  transition: transform 180ms ease, opacity 180ms ease;
}

.prefs-pop-enter-from,
.prefs-pop-leave-to {
  transform: translateY(10px);
  opacity: 0;
}

@media (max-width: 640px) {
  .prefs-backdrop {
    align-items: end;
    padding: 0;
  }

  .prefs-card {
    border-radius: 1rem 1rem 0 0;
    max-height: 88vh;
    overflow: auto;
  }

  .prefs-actions {
    position: sticky;
    bottom: 0;
    background: rgb(8 8 8);
    padding-top: 0.7rem;
  }
}
</style>
