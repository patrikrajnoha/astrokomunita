<template>
  <section class="accordion" :class="{ 'accordion--open': open }">
    <button
      type="button"
      class="accordion__trigger"
      :aria-expanded="open ? 'true' : 'false'"
      @click="$emit('toggle')"
    >
      <div class="accordion__heading">
        <h2 class="accordion__title">{{ title }}</h2>
        <p v-if="summary" class="accordion__summary">{{ summary }}</p>
      </div>
      <span class="accordion__icon" aria-hidden="true">{{ open ? '-' : '+' }}</span>
    </button>

    <transition name="accordionFade">
      <div v-if="open" class="accordion__panel">
        <slot />
      </div>
    </transition>
  </section>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  summary: {
    type: String,
    default: '',
  },
  open: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle'])
</script>

<style scoped>
.accordion {
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background:
    linear-gradient(180deg, rgb(255 255 255 / 0.03), transparent 40%),
    rgb(18 26 38 / 0.84);
  overflow: hidden;
}

.accordion__trigger {
  width: 100%;
  border: 0;
  background: transparent;
  color: inherit;
  padding: 1rem 1rem 0.95rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  text-align: left;
}

.accordion__heading {
  min-width: 0;
}

.accordion__title {
  margin: 0;
  color: rgb(255 255 255 / 0.92);
  font-size: 1rem;
  font-weight: 600;
}

.accordion__summary {
  margin-top: 0.25rem;
  color: rgb(255 255 255 / 0.52);
  font-size: 0.84rem;
  line-height: 1.5;
}

.accordion__icon {
  flex: 0 0 auto;
  display: inline-grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  color: rgb(255 255 255 / 0.7);
  font-size: 1rem;
}

.accordion__panel {
  padding: 0 1rem 1rem;
  border-top: 1px solid var(--divider-color);
}

.accordionFade-enter-active,
.accordionFade-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.accordionFade-enter-from,
.accordionFade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
