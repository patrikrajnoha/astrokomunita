<template>
  <div class="authHero">
    <p v-if="eyebrow" class="authHero__eyebrow">{{ eyebrow }}</p>
    <h1 class="authHero__title">{{ title }}</h1>
    <p v-if="subtitle" class="authHero__subtitle">{{ subtitle }}</p>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})
</script>
