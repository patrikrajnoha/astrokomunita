<template>
  <transition name="sheet-fade">
    <div v-if="open" class="sheet-backdrop" @click.self="$emit('close')">
      <transition name="sheet-slide">
        <section v-if="open" class="sheet" role="dialog" aria-modal="true">
          <div class="sheet-handle"></div>
          <button type="button" class="sheet-close" @click="$emit('close')">Zavriet</button>

          <h3 class="sheet-title">{{ event?.title || 'Detail udalosti' }}</h3>
          <p class="sheet-description">{{ event?.description || event?.short || 'Bez popisu.' }}</p>

          <div class="sheet-grid">
            <span class="badge">{{ typeLabel(event?.type) }}</span>
            <span>{{ formatDateTime(startAt) }}</span>
            <span>{{ formatDateTime(endAt) }}</span>
            <span>{{ visibilityText }}</span>
          </div>

          <div v-if="!authIsAuthed" class="notify-box">
            <p class="notify-copy">Neprihlasenym vieme poslat email upozornenie.</p>
            <div class="notify-row">
              <input
                :value="notifyEmail"
                type="email"
                class="notify-input"
                placeholder="tvoj@email.sk"
                @input="$emit('update:notifyEmail', $event.target.value)"
              />
              <button type="button" class="notify-btn" :disabled="notifyLoading || !notifyEmail?.trim()" @click="$emit('send-notify')">
                {{ notifyLoading ? 'Odosielam...' : 'Poslat' }}
              </button>
            </div>
            <p v-if="notifyMsg" class="notify-msg ok">{{ notifyMsg }}</p>
            <p v-if="notifyErr" class="notify-msg err">{{ notifyErr }}</p>
          </div>

          <div v-if="isDebug" class="debug-box">
            <div>ID: {{ event?.id ?? '—' }}</div>
            <div>UID: {{ event?.source?.uid || '—' }}</div>
            <div>HASH: {{ event?.source?.hash || '—' }}</div>
            <div>Source: {{ event?.source?.name || '—' }}</div>
          </div>
        </section>
      </transition>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  event: { type: Object, default: null },
  typeLabel: { type: Function, required: true },
  formatDateTime: { type: Function, required: true },
  visibilityText: { type: String, default: '—' },
  isDebug: { type: Boolean, default: false },
  authIsAuthed: { type: Boolean, default: false },
  notifyEmail: { type: String, default: '' },
  notifyLoading: { type: Boolean, default: false },
  notifyMsg: { type: String, default: '' },
  notifyErr: { type: String, default: '' },
})

defineEmits(['close', 'send-notify', 'update:notifyEmail'])

const startAt = computed(() => props.event?.start_at || props.event?.starts_at || props.event?.max_at || null)
const endAt = computed(() => props.event?.end_at || props.event?.ends_at || null)
</script>

<style scoped>
.sheet-backdrop {
  position: fixed;
  inset: 0;
  background: rgb(5 10 22 / 0.54);
  z-index: 60;
  display: flex;
  align-items: flex-end;
}

.sheet {
  width: 100%;
  border-radius: 1.2rem 1.2rem 0 0;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.97);
  padding: 0.95rem 1rem 1.3rem;
  max-height: 82vh;
  overflow-y: auto;
}

.sheet-handle {
  width: 3rem;
  height: 0.32rem;
  border-radius: 99px;
  margin: 0 auto 0.7rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.45);
}

.sheet-close {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0;
}

.sheet-title {
  margin-top: 0.45rem;
  font-size: 1.2rem;
  font-weight: 700;
}

.sheet-description {
  margin-top: 0.6rem;
  color: rgb(var(--color-surface-rgb) / 0.85);
  line-height: 1.55;
}

.sheet-grid {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.45rem;
  color: rgb(var(--color-surface-rgb) / 0.8);
  font-size: 0.9rem;
}

.badge {
  width: fit-content;
  padding: 0.22rem 0.65rem;
  border-radius: 999px;
  color: var(--color-primary);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.14);
  font-size: 0.78rem;
  font-weight: 600;
}

.notify-box {
  margin-top: 1.1rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding: 0.72rem;
}

.notify-copy {
  font-size: 0.84rem;
  color: rgb(var(--color-surface-rgb) / 0.72);
}

.notify-row {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.58rem;
}

.notify-input {
  flex: 1;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.74);
  color: var(--color-surface);
  padding: 0.55rem 0.65rem;
}

.notify-btn {
  border-radius: 0.66rem;
  border: none;
  background: rgb(var(--color-primary-rgb) / 0.92);
  color: white;
  padding: 0.55rem 0.8rem;
}

.notify-msg {
  margin-top: 0.45rem;
  font-size: 0.82rem;
}

.notify-msg.ok {
  color: #6ed7a2;
}

.notify-msg.err {
  color: #ff7f94;
}

.debug-box {
  margin-top: 1rem;
  border-top: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.3);
  padding-top: 0.7rem;
  display: grid;
  gap: 0.28rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-size: 0.76rem;
  color: rgb(var(--color-surface-rgb) / 0.64);
}

.sheet-fade-enter-active,
.sheet-fade-leave-active {
  transition: opacity 180ms ease;
}

.sheet-fade-enter-from,
.sheet-fade-leave-to {
  opacity: 0;
}

.sheet-slide-enter-active,
.sheet-slide-leave-active {
  transition: transform 220ms ease;
}

.sheet-slide-enter-from,
.sheet-slide-leave-to {
  transform: translateY(100%);
}
</style>

