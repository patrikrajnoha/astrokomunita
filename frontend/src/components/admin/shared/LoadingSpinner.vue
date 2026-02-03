<template>
  <div
    :class="[
      'loadingSpinner',
      { 'loadingSpinner--inline': inline },
      { 'loadingSpinner--small': small },
      { 'loadingSpinner--large': large }
    ]"
  >
    <div class="spinner"></div>
    <span v-if="text" class="loadingText">{{ text }}</span>
  </div>
</template>

<script setup>
defineProps({
  text: {
    type: String,
    default: ''
  },
  inline: {
    type: Boolean,
    default: false
  },
  small: {
    type: Boolean,
    default: false
  },
  large: {
    type: Boolean,
    default: false
  }
});
</script>

<style scoped>
.loadingSpinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
}

.loadingSpinner--inline {
  flex-direction: row;
  padding: 0;
  gap: 0.5rem;
}

.loadingSpinner--small {
  padding: 1rem;
  gap: 0.5rem;
}

.loadingSpinner--large {
  padding: 3rem;
  gap: 1rem;
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 2px solid var(--color-border);
  border-top: 2px solid var(--color-primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.loadingSpinner--small .spinner {
  width: 1rem;
  height: 1rem;
  border-width: 1px;
}

.loadingSpinner--large .spinner {
  width: 3rem;
  height: 3rem;
  border-width: 3px;
}

.loadingText {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  font-weight: 500;
}

.loadingSpinner--inline .loadingText {
  font-size: 0.875rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
