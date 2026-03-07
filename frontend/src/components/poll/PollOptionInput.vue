<template>
  <div class="pollOptionRow">
    <input
      ref="fileInput"
      type="file"
      class="hiddenInput"
      accept="image/*"
      :disabled="disabled"
      @change="onFileChange"
    />

    <button type="button" class="imageBtn" :disabled="disabled" @click="pickImage">
      <img v-if="option.imagePreviewUrl" :src="option.imagePreviewUrl" alt="Option image" class="thumb" />
      <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.6"/>
        <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
        <path d="m6.5 16 3.5-3 2.4 2 2.1-1.8 3 2.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <div class="textWrap">
      <input
        :value="option.text"
        type="text"
        class="optionInput"
        maxlength="25"
        :placeholder="`Moznost ${index + 1}`"
        :disabled="disabled"
        @input="onTextInput"
      />
      <span class="counter">{{ remaining }}</span>
    </div>

    <button v-if="option.imagePreviewUrl" type="button" class="iconBtn" :disabled="disabled" aria-label="Odstranit obrazok" @click="$emit('remove-image', index)">
      x
    </button>
    <button v-if="showAdd" type="button" class="iconBtn" :disabled="disabled" aria-label="Pridat moznost" @click="$emit('add-option')">
      +
    </button>
    <button v-if="canRemove" type="button" class="iconBtn" :disabled="disabled" aria-label="Odstranit moznost" @click="$emit('remove-option', index)">
      -
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  option: { type: Object, required: true },
  index: { type: Number, required: true },
  showAdd: { type: Boolean, default: false },
  canRemove: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update-text', 'set-image', 'remove-image', 'add-option', 'remove-option'])

const fileInput = ref(null)

const remaining = computed(() => {
  const text = String(props.option?.text || '')
  return Math.max(0, 25 - text.length)
})

function onTextInput(event) {
  emit('update-text', props.index, String(event?.target?.value || '').slice(0, 25))
}

function pickImage() {
  if (props.disabled) return
  fileInput.value?.click()
}

function onFileChange(event) {
  const nextFile = event?.target?.files?.[0] || null
  emit('set-image', props.index, nextFile)
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}
</script>

<style scoped>
.hiddenInput {
  display: none;
}

.pollOptionRow {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  align-items: center;
  gap: 0.5rem;
  padding: 0.38rem 0;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
}

.pollOptionRow:first-child {
  border-top: none;
}

.imageBtn {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.44);
  color: var(--color-text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.imageBtn svg {
  width: 18px;
  height: 18px;
}

.thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.textWrap {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 0.4rem;
}

.optionInput {
  min-height: 38px;
  border: none;
  background: transparent;
  color: var(--color-surface);
  font-size: 0.9rem;
}

.optionInput:focus-visible {
  outline: none;
}

.counter {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.iconBtn {
  min-width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.42);
  color: var(--color-surface);
  font-size: 0.92rem;
  font-weight: 700;
}

@media (max-width: 640px) {
  .pollOptionRow {
    grid-template-columns: 38px 1fr auto;
  }

  .imageBtn {
    width: 34px;
    height: 34px;
  }
}
</style>

