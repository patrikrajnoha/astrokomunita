<template>
  <div class="authAlert" :class="`authAlert--${variant}`" role="alert" aria-live="polite">
    <span class="authAlert__icon" aria-hidden="true">!</span>
    <div class="authAlert__body">
      <p v-if="title" class="authAlert__title">{{ title }}</p>
      <p class="authAlert__message">{{ message }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    required: true,
  },
  variant: {
    type: String,
    default: 'danger',
  },
})
</script>
