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
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--dashboard-border, var(--color-border));
  border-radius: var(--dashboard-radius, 11px);
  background: var(--dashboard-panel, rgb(var(--color-bg-rgb) / 0.34));
  transition: border-color 160ms ease, background-color 160ms ease;
  min-width: 0;
}

.sectionHead {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
}

.sectionCopy {
  min-width: 0;
}

.sectionTitle {
  margin: 0;
  font-size: 0.98rem;
  font-weight: 600;
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
  }
}
</style>
