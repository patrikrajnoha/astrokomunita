<template>
  <section class="settings-card" :class="{ 'settings-card-danger': danger }">
    <RouterLink class="settings-back-link" :to="{ name: 'settings' }">
      <span aria-hidden="true">&lt;-</span>
      <span>Back to Settings</span>
    </RouterLink>

    <h2 class="card-title">{{ title }}</h2>
    <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>

    <slot />
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  danger: {
    type: Boolean,
    default: false,
  },
  subtitle: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
})
</script>
