<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { getAdminSectionTabs, isKnownAdminSection } from '@/components/admin/adminSections'

const props = defineProps({
  section: {
    type: String,
    default: '',
  },
  tabs: {
    type: Array,
    default: () => [],
  },
  activeKey: {
    type: String,
    default: '',
  },
})

const route = useRoute()

const resolvedSection = computed(() => {
  if (isKnownAdminSection(props.section)) return props.section

  const fromMeta = String(route.meta?.adminSection || '')
  return isKnownAdminSection(fromMeta) ? fromMeta : ''
})

const resolvedTabs = computed(() => {
  if (props.tabs.length > 0) return props.tabs
  if (resolvedSection.value) return getAdminSectionTabs(resolvedSection.value)
  return []
})

const resolvedActiveKey = computed(() => {
  if (props.activeKey) return props.activeKey
  return String(route.meta?.adminTab || '')
})
</script>

<template>
  <nav class="adminSectionTabs" role="tablist" aria-label="Karty sekcie administrácie">
    <RouterLink
      v-for="tab in resolvedTabs"
      :key="tab.key"
      :to="tab.to"
      class="adminSectionTabs__tab"
      :class="{ active: resolvedActiveKey === tab.key }"
      :aria-current="resolvedActiveKey === tab.key ? 'page' : undefined"
    >
      {{ tab.label }}
    </RouterLink>
  </nav>
</template>

<style scoped>
.adminSectionTabs {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 11px;
  background: rgb(var(--color-bg-rgb) / 0.84);
}

.adminSectionTabs__tab {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 9px;
  padding: 6px 10px;
  text-decoration: none;
  color: var(--color-text-secondary);
  font-size: 12px;
  font-weight: 600;
  background: transparent;
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    color var(--motion-fast),
    transform 120ms ease;
}

.adminSectionTabs__tab:hover {
  border-color: rgb(var(--color-surface-rgb) / 0.35);
  background: rgb(var(--color-surface-rgb) / 0.08);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.adminSectionTabs__tab.active {
  border-color: rgb(var(--color-accent-rgb) / 0.42);
  background: rgb(var(--color-accent-rgb) / 0.14);
  color: var(--color-text-primary);
}

.adminSectionTabs__tab:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  box-shadow: var(--focus-ring);
}

@media (max-width: 767px) {
  .adminSectionTabs {
    padding: 7px 8px;
    overflow-x: auto;
    flex-wrap: nowrap;
  }

  .adminSectionTabs__tab {
    white-space: nowrap;
    flex-shrink: 0;
  }
}
</style>
