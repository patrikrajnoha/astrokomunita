<template>
  <div class="authHero">
    <slot name="top" />
    <p v-if="eyebrow" class="authHero__eyebrow">{{ eyebrow }}</p>
    <h1 class="authHero__title">
      <slot name="title">{{ title }}</slot>
    </h1>
    <p v-if="subtitle || $slots.subtitle" class="authHero__subtitle">
      <slot name="subtitle">{{ subtitle }}</slot>
    </p>
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
