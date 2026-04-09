<template>
  <section class="pollPanel" data-testid="poll-panel">
    <header class="panelHead">
      <h4>Anketa</h4>
      <button class="closeBtn" type="button" :disabled="disabled" aria-label="Odstrániť anketu" @click="$emit('remove-poll')">x</button>
    </header>

    <div class="optionList">
      <PollOptionInput
        v-for="(option, index) in modelValue"
        :key="`poll-opt-${index}`"
        :option="option"
        :index="index"
        :disabled="disabled"
        :show-add="index === 1 && modelValue.length < 4"
        :can-remove="modelValue.length > 2 && index >= 2"
        @update-text="updateText"
        @set-image="setImage"
        @remove-image="removeImage"
        @add-option="addOption"
        @remove-option="removeOption"
      />
    </div>

    <PollDurationPicker
      :model-value="durationSeconds"
      :disabled="disabled"
      @update:model-value="(value) => $emit('update:durationSeconds', value)"
    />

    <p class="hint">Otázka ankety je text príspevku.</p>
  </section>
</template>

<script setup>
import PollDurationPicker from '@/components/poll/PollDurationPicker.vue'
import PollOptionInput from '@/components/poll/PollOptionInput.vue'

const props = defineProps({
  modelValue: { type: Array, required: true },
  durationSeconds: { type: Number, default: 86400 },
  disabled: { type: Boolean, default: false },
  prepareImageFile: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:durationSeconds', 'remove-poll', 'image-error'])

function updateText(index, text) {
  const next = props.modelValue.map((option, optionIndex) => {
    if (optionIndex !== index) return option
    return { ...option, text }
  })
  emit('update:modelValue', next)
}

function addOption() {
  if (props.modelValue.length >= 4) return
  emit('update:modelValue', [...props.modelValue, createEmptyOption()])
}

function removeOption(index) {
  if (props.modelValue.length <= 2) return
  emit('update:modelValue', props.modelValue.filter((_, optionIndex) => optionIndex !== index))
}

async function setImage(index, file) {
  if (!file) {
    emit('update:modelValue', props.modelValue.map((option, optionIndex) => {
      if (optionIndex !== index) return option
      return {
        ...option,
        imageFile: null,
      }
    }))
    return
  }

  try {
    const nextFile = typeof props.prepareImageFile === 'function'
      ? await props.prepareImageFile(file)
      : file

    if (!nextFile) return

    emit('update:modelValue', props.modelValue.map((option, optionIndex) => {
      if (optionIndex !== index) return option
      return {
        ...option,
        imageFile: nextFile,
      }
    }))
  } catch (error) {
    emit('image-error', String(error?.userMessage || error?.message || 'Obrazok sa nepodarilo spracovat.'))
  }
}

function removeImage(index) {
  emit('update:modelValue', props.modelValue.map((option, optionIndex) => {
    if (optionIndex !== index) return option
    return {
      ...option,
      imageFile: null,
      imagePreviewUrl: '',
    }
  }))
}

function createEmptyOption() {
  return {
    text: '',
    imageFile: null,
    imagePreviewUrl: '',
  }
}
</script>

<style scoped>
.pollPanel {
  border-radius: 16px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  background: linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.64), rgb(var(--color-bg-rgb) / 0.5));
  padding: 0.62rem;
  display: grid;
  gap: 0.58rem;
}

.panelHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.panelHead h4 {
  margin: 0;
  color: var(--color-surface);
  font-size: 0.86rem;
  font-weight: 800;
}

.closeBtn {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.45);
  background: rgb(var(--color-danger-rgb) / 0.14);
  color: var(--color-danger);
  font-size: 0.9rem;
  font-weight: 800;
}

.optionList {
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.3);
  padding: 0 0.52rem;
}

.hint {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}
</style>

