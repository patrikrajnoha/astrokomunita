<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AdminSectionTabs from '@/components/admin/AdminSectionTabs.vue'
import { getAdminSectionLabel, getAdminSectionTabs, isKnownAdminSection } from '@/components/admin/adminSections'

const BACK_LINK_QUERY_WHITELIST = new Set([
  'page',
  'search',
  'q',
  'filter',
  'status',
  'source',
  'run_id',
  'sort',
  'dir',
  'tab',
])

const props = defineProps({
  section: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: '',
  },
  backLabel: {
    type: String,
    default: 'Späť na zoznam',
  },
  backTo: {
    type: [String, Object],
    default: null,
  },
  showTabs: {
    type: Boolean,
    default: true,
  },
  preserveQuery: {
    type: Boolean,
    default: true,
  },
})

const route = useRoute()

const resolvedSection = computed(() => {
  if (isKnownAdminSection(props.section)) return props.section

  const fromMeta = String(route.meta?.adminSection || '')
  return isKnownAdminSection(fromMeta) ? fromMeta : ''
})

const sectionLabel = computed(() => getAdminSectionLabel(resolvedSection.value))

const sectionTabs = computed(() => getAdminSectionTabs(resolvedSection.value))

const routeTab = computed(() => String(route.meta?.adminTab || ''))

function pickWhitelistedQuery(query) {
  const source = query || {}
  const filtered = {}

  for (const [key, value] of Object.entries(source)) {
    if (BACK_LINK_QUERY_WHITELIST.has(key)) {
      filtered[key] = value
    }
  }

  return filtered
}

const defaultBackTarget = computed(() => {
  if (sectionTabs.value.length === 0) return null

  const matchedTab = sectionTabs.value.find((tab) => tab.key === routeTab.value)
  return matchedTab?.to || sectionTabs.value[0].to
})

const backTarget = computed(() => {
  const target = props.backTo || defaultBackTarget.value
  if (!target) return null

  if (!props.preserveQuery) {
    return target
  }

  const preservedQuery = pickWhitelistedQuery(route.query)
  const hasPreservedQuery = Object.keys(preservedQuery).length > 0

  if (typeof target === 'string') {
    if (!hasPreservedQuery) return target

    return {
      path: target,
      query: preservedQuery,
    }
  }

  if (!hasPreservedQuery) {
    return target
  }

  return {
    ...target,
    query: {
      ...preservedQuery,
      ...(target.query || {}),
    },
  }
})
</script>

<template>
  <header class="adminSectionHeader" data-testid="admin-section-header">
    <div class="adminSectionHeader__top">
      <div class="adminSectionHeader__titleWrap">
        <div class="adminSectionHeader__section">{{ sectionLabel }}</div>
        <h1 v-if="title" class="adminSectionHeader__title">{{ title }}</h1>
      </div>

      <RouterLink
        v-if="backTarget"
        :to="backTarget"
        class="adminSectionHeader__back"
        data-testid="admin-section-back-link"
      >
        {{ backLabel }}
      </RouterLink>
    </div>

    <AdminSectionTabs v-if="showTabs && resolvedSection" :section="resolvedSection" />
  </header>
</template>

<style scoped>
.adminSectionHeader {
  display: grid;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.adminSectionHeader__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.adminSectionHeader__titleWrap {
  display: grid;
  gap: 4px;
}

.adminSectionHeader__section {
  font-size: var(--font-size-xs);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-secondary);
  font-weight: 700;
}

.adminSectionHeader__title {
  margin: 0;
  font-size: clamp(1.05rem, 2vw, 1.35rem);
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--color-text-primary);
}

.adminSectionHeader__back {
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  padding: 8px 14px;
  text-decoration: none;
  color: var(--color-text-primary);
  background: rgb(var(--bg-app-rgb) / 0.34);
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast);
}

.adminSectionHeader__back:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.44);
  background: rgb(var(--color-accent-rgb) / 0.14);
  color: var(--color-text-primary);
}

@media (max-width: 767px) {
  .adminSectionHeader {
    gap: 6px;
    margin-bottom: 10px;
  }

  .adminSectionHeader__top {
    flex-wrap: wrap;
  }
}
</style>
