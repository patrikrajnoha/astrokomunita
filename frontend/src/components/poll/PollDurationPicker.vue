<template>
  <div class="pollDurationPicker">
    <button type="button" class="durationTrigger" :disabled="disabled" @click="toggleOpen">
      <span>Dlzka hlasovania</span>
      <strong>{{ label }}</strong>
    </button>

    <div v-if="open" class="durationMenu">
      <div class="presetRow">
        <button
          v-for="preset in presets"
          :key="preset.value"
          type="button"
          class="presetBtn"
          :class="{ active: modelValue === preset.value }"
          @click="selectPreset(preset.value)"
        >
          {{ preset.label }}
        </button>
      </div>

      <div class="customGrid">
        <label>
          Dni
          <input v-model.number="customDays" type="number" min="0" max="7" />
        </label>
        <label>
          Hod
          <input v-model.number="customHours" type="number" min="0" max="23" />
        </label>
        <label>
          Min
          <input v-model.number="customMinutes" type="number" min="0" max="59" />
        </label>
      </div>

      <div class="menuActions">
        <button type="button" class="menuBtn" @click="close">Zavriet</button>
        <button type="button" class="menuBtn menuBtn--primary" @click="applyCustom">Pouzit</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const MIN_SECONDS = 300
const MAX_SECONDS = 604800

const props = defineProps({
  modelValue: { type: Number, default: 86400 },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const customDays = ref(0)
const customHours = ref(0)
const customMinutes = ref(0)

const presets = [
  { label: '5m', value: 300 },
  { label: '1h', value: 3600 },
  { label: '1d', value: 86400 },
  { label: '3d', value: 259200 },
  { label: '7d', value: 604800 },
]

const label = computed(() => {
  const seconds = Number(props.modelValue || 0)
  const exact = presets.find((p) => p.value === seconds)
  if (exact) return exact.label
  if (seconds < 3600) return `${Math.round(seconds / 60)}m`
  if (seconds < 86400) return `${Math.round(seconds / 3600)}h`
  return `${Math.round(seconds / 86400)}d`
})

watch(
  () => props.modelValue,
  (value) => {
    const seconds = clampSeconds(value)
    customDays.value = Math.floor(seconds / 86400)
    customHours.value = Math.floor((seconds % 86400) / 3600)
    customMinutes.value = Math.floor((seconds % 3600) / 60)
  },
  { immediate: true },
)

function toggleOpen() {
  if (props.disabled) return
  open.value = !open.value
}

function close() {
  open.value = false
}

function selectPreset(value) {
  emit('update:modelValue', clampSeconds(value))
  close()
}

function applyCustom() {
  const seconds = Number(customDays.value || 0) * 86400 + Number(customHours.value || 0) * 3600 + Number(customMinutes.value || 0) * 60
  emit('update:modelValue', clampSeconds(seconds))
  close()
}

function clampSeconds(value) {
  const n = Number(value || 0)
  if (!Number.isFinite(n)) return 86400
  return Math.max(MIN_SECONDS, Math.min(MAX_SECONDS, Math.round(n)))
}
</script>

<style scoped>
.pollDurationPicker {
  position: relative;
}

.durationTrigger {
  width: 100%;
  min-height: 40px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.45);
  color: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.56rem 0.72rem;
  font-size: 0.84rem;
}

.durationMenu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 8px);
  z-index: 30;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 0.65rem;
  display: grid;
  gap: 0.65rem;
  box-shadow: 0 16px 30px rgb(0 0 0 / 0.34);
}

.presetRow {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.35rem;
}

.presetBtn {
  min-height: 34px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.36);
  color: var(--color-surface);
  font-size: 0.74rem;
  font-weight: 700;
}

.presetBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
  background: rgb(var(--color-primary-rgb) / 0.2);
}

.customGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.customGrid label {
  display: grid;
  gap: 0.35rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.customGrid input {
  min-height: 34px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  padding: 0.4rem 0.52rem;
}

.menuActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.45rem;
}

.menuBtn {
  min-height: 34px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: transparent;
  color: var(--color-surface);
  font-size: 0.75rem;
  padding: 0.32rem 0.62rem;
}

.menuBtn--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: rgb(var(--color-primary-rgb) / 0.18);
}

@media (max-width: 640px) {
  .presetRow {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>

