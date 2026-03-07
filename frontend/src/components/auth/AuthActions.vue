<template>
  <div class="authActions">
    <RouterLink v-if="backTo" :to="backTo" class="authButton authButton--secondary">
      {{ backLabel }}
    </RouterLink>
    <button v-else type="button" class="authButton authButton--secondary" @click="$emit('back')">
      {{ backLabel }}
    </button>

    <button
      type="submit"
      class="authButton authButton--primary"
      :disabled="disabled || loading"
    >
      {{ loading ? loadingLabel : submitLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  backTo: {
    type: [String, Object],
    default: null,
  },
  backLabel: {
    type: String,
    default: 'Spat',
  },
  submitLabel: {
    type: String,
    default: 'Dalej',
  },
  loadingLabel: {
    type: String,
    default: 'Spracuvam...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['back'])
</script>
