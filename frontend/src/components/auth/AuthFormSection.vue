<template>
  <section class="authFormSection">
    <header v-if="kicker || title || description" class="authFormSection__header">
      <p v-if="kicker" class="authFormSection__kicker">{{ kicker }}</p>
      <h2 v-if="title || $slots.title" class="authFormSection__title">
        <slot name="title">{{ title }}</slot>
      </h2>
      <p v-if="description || $slots.description" class="authFormSection__description">
        <slot name="description">{{ description }}</slot>
      </p>
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
