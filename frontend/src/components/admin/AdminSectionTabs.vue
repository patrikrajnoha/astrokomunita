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
  <nav class="adminSectionTabs" role="tablist" aria-label="Admin section tabs">
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
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.adminSectionTabs__tab {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 999px;
  padding: 7px 12px;
  text-decoration: none;
  color: inherit;
  font-size: 13px;
  font-weight: 600;
  background: rgb(var(--color-bg-rgb) / 0.45);
  transition: border-color 0.18s ease, background-color 0.18s ease;
}

.adminSectionTabs__tab:hover {
  border-color: rgb(var(--color-surface-rgb) / 0.3);
  background: rgb(var(--color-surface-rgb) / 0.12);
}

.adminSectionTabs__tab.active {
  border-color: rgb(var(--color-primary-rgb) / 0.42);
  background: rgb(var(--color-primary-rgb) / 0.15);
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
