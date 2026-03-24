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
      <div class="sectionCopy">
        <h2 class="sectionTitle">{{ title }}</h2>
        <p v-if="subtitle" class="sectionSubtitle">{{ subtitle }}</p>
      </div>

      <div v-if="$slots['header-actions'] || actionLabel" class="sectionControls">
        <slot name="header-actions" />
        <button
          v-if="actionLabel"
          type="button"
          class="ui-btn ui-btn--secondary sectionAction"
          :disabled="actionDisabled"
          @click="emit('action')"
        >
          {{ actionLabel }}
        </button>
      </div>
    </header>

    <div class="sectionBody">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.sectionCard {
  display: grid;
  gap: 10px;
  padding: 12px;
  border-radius: var(--dashboard-radius, 12px);
  background: var(--dashboard-panel, rgb(var(--color-bg-rgb) / 0.42));
  min-width: 0;
}

.sectionHead {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  min-width: 0;
}

.sectionCopy {
  min-width: 0;
}

.sectionTitle {
  margin: 0;
  font-size: 1rem;
  font-weight: 650;
  letter-spacing: -0.01em;
  color: var(--color-surface);
}

.sectionSubtitle {
  margin: 3px 0 0;
  font-size: 11px;
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
}

.sectionControls {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
  min-width: 0;
}

.sectionAction {
  min-height: 30px;
  padding-inline: 10px;
  font-size: 11px;
  font-weight: 600;
}

.sectionBody {
  display: grid;
  gap: 8px;
  min-width: 0;
}

@media (max-width: 640px) {
  .sectionHead {
    align-items: flex-start;
    flex-direction: column;
  }

  .sectionControls {
    justify-content: flex-start;
    width: 100%;
  }
}
</style>
