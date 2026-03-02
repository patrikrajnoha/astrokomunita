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
          class="sectionAction"
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
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--dashboard-border, rgb(var(--color-surface-rgb) / 0.1));
  border-radius: var(--dashboard-radius, 18px);
  background: var(--dashboard-panel, rgb(var(--color-bg-rgb) / 0.34));
}

.sectionHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.sectionCopy {
  min-width: 0;
}

.sectionTitle {
  margin: 0;
  font-family:
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: var(--color-surface);
}

.sectionSubtitle {
  margin: 3px 0 0;
  font-size: 12px;
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
}

.sectionControls {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.sectionAction {
  height: 32px;
  border: 1px solid var(--dashboard-border-strong, rgb(var(--color-surface-rgb) / 0.15));
  border-radius: 999px;
  padding: 0 12px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.sectionAction:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.sectionBody {
  display: grid;
  gap: 10px;
  min-width: 0;
}

@media (max-width: 640px) {
  .sectionHead {
    align-items: flex-start;
    flex-direction: column;
  }

  .sectionControls {
    justify-content: flex-start;
  }
}
</style>
