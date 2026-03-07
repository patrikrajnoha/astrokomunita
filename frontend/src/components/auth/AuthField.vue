<template>
  <div class="authField">
    <div class="authField__head">
      <label class="authField__label" :for="controlId">{{ label }}</label>
      <slot name="labelAction" />
    </div>

    <div class="authField__control" :class="{ 'is-error': Boolean(error) }">
      <span class="authField__icon" :class="{ 'is-empty': !$slots.icon }" aria-hidden="true">
        <slot name="icon" />
      </span>
      <input
        :id="controlId"
        class="authField__input"
        :type="type"
        :name="name || controlId"
        :autocomplete="autocomplete"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :value="modelValue"
        :aria-invalid="error ? 'true' : 'false'"
        :aria-describedby="metaId"
        v-bind="attrs"
        @input="onInput"
      />
    </div>

    <p v-if="error" :id="metaId" class="authField__meta authField__meta--error">{{ error }}</p>
    <p v-else-if="helper" :id="metaId" class="authField__meta">{{ helper }}</p>
  </div>
</template>

<script setup>
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    required: true,
  },
  id: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  placeholder: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  helper: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])
const attrs = useAttrs()
const fallbackId = `auth-field-${Math.random().toString(36).slice(2, 9)}`

const controlId = computed(() => props.id || fallbackId)
const metaId = computed(() => (props.error || props.helper ? `${controlId.value}-meta` : undefined))

function onInput(event) {
  emit('update:modelValue', event?.target?.value || '')
}
</script>
