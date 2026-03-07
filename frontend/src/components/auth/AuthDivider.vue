<template>
  <div class="authDivider" role="separator" aria-label="section divider">
    <span>{{ text }}</span>
  </div>
</template>

<script setup>
defineProps({
  text: {
    type: String,
    default: 'or',
  },
})
</script>
