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
  gap: 8px;
  flex-wrap: wrap;
  padding: 10px 16px;
  border-bottom: 1px solid var(--color-divider);
  background: rgb(var(--bg-app-rgb) / 0.35);
}

.adminSectionTabs__tab {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  padding: 8px 14px;
  text-decoration: none;
  color: var(--color-text-secondary);
  font-size: 14px;
  font-weight: 500;
  background: rgb(var(--bg-app-rgb) / 0.4);
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    color var(--motion-fast),
    transform 120ms ease;
}

.adminSectionTabs__tab:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.adminSectionTabs__tab.active {
  border-color: rgb(var(--color-accent-rgb) / 0.46);
  background: rgb(var(--color-accent-rgb) / 0.16);
  color: var(--color-text-primary);
}

.adminSectionTabs__tab:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  box-shadow: var(--focus-ring);
}

@media (max-width: 767px) {
  .adminSectionTabs {
    padding: 8px 10px;
    overflow-x: auto;
    flex-wrap: nowrap;
  }

  .adminSectionTabs__tab {
    white-space: nowrap;
    flex-shrink: 0;
  }
}
</style>
