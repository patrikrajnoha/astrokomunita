<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  actionDisabled: { type: Boolean, default: false },
})

const emit = defineEmits(['action'])
</script>

<template>
  <section class="sectionCard">
    <header class="sectionHead">
      <div>
        <h2 class="sectionTitle">{{ title }}</h2>
        <p v-if="subtitle" class="sectionSubtitle">{{ subtitle }}</p>
      </div>

      <button
        v-if="actionLabel"
        type="button"
        class="sectionAction"
        :disabled="actionDisabled"
        @click="emit('action')"
      >
        {{ actionLabel }}
      </button>
    </header>

    <div class="sectionBody">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.sectionCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 14px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.38);
}

.sectionHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.sectionTitle {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--color-surface);
}

.sectionSubtitle {
  margin: 3px 0 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.sectionAction {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 6px 10px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.sectionAction:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.sectionBody {
  display: grid;
  gap: 10px;
}
</style>
