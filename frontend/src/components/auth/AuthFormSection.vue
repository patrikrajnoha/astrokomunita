<template>
  <section class="authFormSection">
    <header v-if="kicker || title || description" class="authFormSection__header">
      <p v-if="kicker" class="authFormSection__kicker">{{ kicker }}</p>
      <h2 v-if="title" class="authFormSection__title">{{ title }}</h2>
      <p v-if="description" class="authFormSection__description">{{ description }}</p>
    </header>
    <slot />
  </section>
</template>

<script setup>
defineProps({
  kicker: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
})
</script>
