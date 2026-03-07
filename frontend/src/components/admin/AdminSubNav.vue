<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ADMIN_SECTION_KEYS, getAdminSectionLabel } from '@/components/admin/adminSections'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const wipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const routeName = computed(() => String(route.name || ''))
const expandedGroups = ref(new Set())

const overviewItem = {
  key: 'overview',
  label: 'Prehľad',
  to: { name: 'admin.dashboard' },
  icon: 'D',
  routeNames: ['admin.dashboard'],
}

const navGroups = computed(() => {
  const contentChildren = [
    {
      key: 'featured-events',
      label: 'Vybrané udalosti',
      to: { name: 'admin.featured-events' },
      icon: 'V',
      routeNames: ['admin.featured-events'],
    },
    {
      key: 'contests',
      label: 'Súťaže',
      to: { name: 'admin.contests' },
      icon: 'S',
      routeNames: ['admin.contests'],
    },
    ...(wipEnabled
      ? [{
          key: 'banned-words',
          label: 'Zakázané slová',
          to: { name: 'admin.banned-words' },
          icon: 'W',
          routeNames: ['admin.banned-words'],
          tone: 'danger',
        }]
      : []),
  ]

  return [
    {
      title: 'HLAVNÉ',
      items: [
        {
          key: 'events',
          label: getAdminSectionLabel(ADMIN_SECTION_KEYS.EVENTS),
          to: { name: 'admin.events' },
          icon: 'E',
          section: ADMIN_SECTION_KEYS.EVENTS,
        },
        {
          key: 'content',
          label: getAdminSectionLabel(ADMIN_SECTION_KEYS.CONTENT),
          to: { name: 'admin.blog' },
          icon: 'O',
          section: ADMIN_SECTION_KEYS.CONTENT,
          children: contentChildren,
        },
      ],
    },
    {
      title: 'KOMUNITA',
      items: [
        {
          key: 'community',
          label: getAdminSectionLabel(ADMIN_SECTION_KEYS.COMMUNITY),
          to: { name: 'admin.users' },
          icon: 'K',
          section: ADMIN_SECTION_KEYS.COMMUNITY,
        },
      ],
    },
    {
      title: 'SYSTÉM',
      items: [
        {
          key: 'sidebar',
          label: 'Bočný panel',
          to: { name: 'admin.sidebar' },
          icon: 'P',
          routeNames: ['admin.sidebar'],
        },
        {
          key: 'bots',
          label: 'Boti',
          to: { name: 'admin.bots' },
          icon: 'B',
          routeNames: ['admin.bots', 'admin.astrobot'],
          routeNamePrefixes: ['admin.bots'],
        },
        {
          key: 'performance',
          label: 'Výkonnosť',
          to: { name: 'admin.performance-metrics' },
          icon: 'M',
          routeNames: ['admin.performance-metrics'],
        },
      ],
    },
  ]
})

const isEditorOnly = computed(() => Boolean(auth.isEditor && !auth.isAdmin))
const visibleGroups = computed(() => {
  if (!isEditorOnly.value) {
    return navGroups.value
  }

  return [
    {
      title: 'EDITOR',
      items: [
        {
          key: 'content',
          label: getAdminSectionLabel(ADMIN_SECTION_KEYS.CONTENT),
          to: { name: 'admin.blog' },
          icon: 'O',
          section: ADMIN_SECTION_KEYS.CONTENT,
        },
      ],
    },
  ]
})

function hasChildren(item) {
  return Array.isArray(item.children) && item.children.length > 0
}

function sectionFromMeta() {
  const section = String(route.meta?.adminSection || '')
  if (
    section === ADMIN_SECTION_KEYS.EVENTS
    || section === ADMIN_SECTION_KEYS.COMMUNITY
    || section === ADMIN_SECTION_KEYS.CONTENT
  ) {
    return section
  }

  return ''
}

function sectionFromName() {
  const name = routeName.value
  if (!name) return ''

  if (
    name === 'admin.events'
    || name === 'admin.event-sources'
    || name === 'admin.event-candidates'
    || name === 'admin.candidate.detail'
    || name === 'admin.crawl-run.detail'
    || name === 'admin.events.create'
    || name === 'admin.events.edit'
  ) {
    return ADMIN_SECTION_KEYS.EVENTS
  }

  if (
    name === 'admin.users'
    || name === 'admin.users.detail'
    || name === 'admin.moderation'
    || name === 'admin.reports'
  ) {
    return ADMIN_SECTION_KEYS.COMMUNITY
  }

  if (
    name === 'admin.blog'
    || name === 'admin.newsletter'
    || name === 'admin.featured-events'
    || name === 'admin.contests'
    || name === 'admin.banned-words'
  ) {
    return ADMIN_SECTION_KEYS.CONTENT
  }

  return ''
}

function sectionFromPath() {
  const path = route.path

  if (
    path.startsWith('/admin/events')
    || path.startsWith('/admin/event-sources')
    || path.startsWith('/admin/event-candidates')
    || path.startsWith('/admin/candidates/')
    || path.startsWith('/admin/crawl-runs/')
  ) {
    return ADMIN_SECTION_KEYS.EVENTS
  }

  if (
    path.startsWith('/admin/community')
    || path.startsWith('/admin/users')
    || path.startsWith('/admin/moderation')
    || path.startsWith('/admin/reports')
  ) {
    return ADMIN_SECTION_KEYS.COMMUNITY
  }

  if (
    path.startsWith('/admin/content')
    || path.startsWith('/admin/blog')
    || path.startsWith('/admin/newsletter')
    || path.startsWith('/admin/featured-events')
    || path.startsWith('/admin/contests')
    || path.startsWith('/admin/banned-words')
  ) {
    return ADMIN_SECTION_KEYS.CONTENT
  }

  return ''
}

const activeSection = computed(() => sectionFromMeta() || sectionFromName() || sectionFromPath())

function hasActiveChild(item) {
  if (!hasChildren(item)) return false
  return item.children.some((child) => isActive(child))
}

function isActive(item) {
  if (item.section) {
    if (activeSection.value !== item.section) return false
    return !hasChildren(item) || !hasActiveChild(item)
  }

  const name = routeName.value
  if (name) {
    if (Array.isArray(item.routeNames) && item.routeNames.includes(name)) return true
    if (
      Array.isArray(item.routeNamePrefixes)
      && item.routeNamePrefixes.some((prefix) => name === prefix || name.startsWith(`${prefix}.`))
    ) {
      return true
    }
  }

  if (typeof item.pathPrefix === 'string') {
    return route.path === item.pathPrefix || route.path.startsWith(`${item.pathPrefix}/`)
  }

  return false
}

function isExpanded(item) {
  if (!hasChildren(item)) return false
  if (isActive(item) || hasActiveChild(item)) return true
  return expandedGroups.value.has(item.key)
}

function toggleGroup(item) {
  if (!hasChildren(item)) return
  const next = new Set(expandedGroups.value)
  if (next.has(item.key)) {
    next.delete(item.key)
  } else {
    next.add(item.key)
  }
  expandedGroups.value = next
}

const activeLabel = computed(() => {
  if (!isEditorOnly.value && isActive(overviewItem)) {
    return overviewItem.label
  }

  for (const group of visibleGroups.value) {
    for (const item of group.items) {
      if (hasChildren(item)) {
        const activeChild = item.children.find((child) => isActive(child))
        if (activeChild) return activeChild.label
      }
      if (isActive(item)) return item.label
    }
  }

  return isEditorOnly.value ? 'Editor' : 'Administrácia'
})
</script>

<template>
  <aside class="adminSubNav" aria-label="Podnavigácia administrácie">
    <div class="adminSubNav__head">
      <div class="adminSubNav__title">{{ isEditorOnly ? 'Editor' : 'Administrácia' }}</div>
      <div class="adminSubNav__caption">Sekcia: {{ activeLabel }}</div>
    </div>

    <RouterLink
      v-if="!isEditorOnly"
      :to="overviewItem.to"
      class="adminSubNav__item adminSubNav__item--overview"
      :class="{ active: isActive(overviewItem) }"
      :title="`Otvoriť ${overviewItem.label}`"
    >
      <span class="adminSubNav__icon" aria-hidden="true">{{ overviewItem.icon }}</span>
      <span>{{ overviewItem.label }}</span>
    </RouterLink>

    <div class="adminSubNav__groups">
      <section v-for="group in visibleGroups" :key="group.title" class="adminSubNav__group">
        <div class="adminSubNav__groupTitle">{{ group.title }}</div>
        <nav class="adminSubNav__list" :aria-label="group.title">
          <template v-for="item in group.items" :key="item.key || item.label">
            <div
              v-if="hasChildren(item)"
              class="adminSubNav__collapsible"
              :class="{ 'adminSubNav__collapsible--open': isExpanded(item) }"
            >
              <div class="adminSubNav__itemRow">
                <RouterLink
                  :to="item.to"
                  class="adminSubNav__item adminSubNav__item--parent"
                  :class="{ active: isActive(item) }"
                  :title="`Otvoriť ${item.label}`"
                >
                  <span class="adminSubNav__icon" aria-hidden="true">{{ item.icon }}</span>
                  <span>{{ item.label }}</span>
                </RouterLink>

                <button
                  type="button"
                  class="adminSubNav__collapseToggle"
                  :class="{ active: isExpanded(item) }"
                  :aria-expanded="isExpanded(item) ? 'true' : 'false'"
                  :aria-label="`${isExpanded(item) ? 'Skryť' : 'Zobraziť'} podsekcie ${item.label}`"
                  @click="toggleGroup(item)"
                >
                  <span aria-hidden="true">{{ isExpanded(item) ? '−' : '+' }}</span>
                </button>
              </div>

              <Transition name="adminSubNavReveal">
                <div v-if="isExpanded(item)" class="adminSubNav__children">
                  <RouterLink
                    v-for="child in item.children"
                    :key="child.key || child.label"
                    :to="child.to"
                    class="adminSubNav__item adminSubNav__item--child"
                    :class="{ active: isActive(child), 'adminSubNav__item--danger': child.tone === 'danger' }"
                    :title="`Otvoriť ${child.label}`"
                  >
                    <span class="adminSubNav__icon" aria-hidden="true">{{ child.icon }}</span>
                    <span>{{ child.label }}</span>
                  </RouterLink>
                </div>
              </Transition>
            </div>

            <RouterLink
              v-else
              :to="item.to"
              class="adminSubNav__item"
              :class="{ active: isActive(item), 'adminSubNav__item--danger': item.tone === 'danger' }"
              :title="`Otvoriť ${item.label}`"
            >
              <span class="adminSubNav__icon" aria-hidden="true">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </RouterLink>
          </template>
        </nav>
      </section>
    </div>
  </aside>
</template>

<style scoped>
.adminSubNav {
  --admin-nav-bg: #151d28;
  --admin-nav-surface: #1d2228;
  --admin-nav-accent: #01a6ff;
  --admin-nav-text: #e6f2ff;
  --admin-nav-danger: #eb2452;
  border: 1px solid rgb(1 166 255 / 0.18);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  background:
    linear-gradient(180deg, rgb(29 34 40 / 0.58), transparent 30%),
    var(--admin-nav-bg);
  box-shadow: 0 14px 28px rgb(0 0 0 / 0.24);
  color: var(--admin-nav-text);
}

.adminSubNav__head {
  border-bottom: 1px solid rgb(230 242 255 / 0.13);
  padding-bottom: 10px;
}

.adminSubNav__title {
  font-size: 0.98rem;
  font-weight: 800;
  letter-spacing: 0.01em;
}

.adminSubNav__caption {
  margin-top: 4px;
  font-size: var(--font-size-sm);
  color: rgb(230 242 255 / 0.76);
}

.adminSubNav__groups {
  display: grid;
  gap: var(--space-3);
  margin-top: 10px;
}

.adminSubNav__group {
  display: grid;
  gap: 7px;
}

.adminSubNav__groupTitle {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(230 242 255 / 0.54);
  padding: 0 2px;
}

.adminSubNav__list {
  display: grid;
  gap: 8px;
}

.adminSubNav__itemRow {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: stretch;
}

.adminSubNav__item {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  border: 1px solid rgb(230 242 255 / 0.12);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  color: rgb(230 242 255 / 0.84);
  text-decoration: none;
  font-size: var(--font-size-sm);
  font-weight: 600;
  background: var(--admin-nav-surface);
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast);
}

.adminSubNav__item:hover {
  border-color: rgb(1 166 255 / 0.4);
  color: var(--admin-nav-text);
}

.adminSubNav__item.active {
  background: rgb(1 166 255 / 0.16);
  border-color: rgb(1 166 255 / 0.58);
  color: var(--admin-nav-text);
}

.adminSubNav__item--overview {
  margin-top: 10px;
}

.adminSubNav__item--parent {
  min-width: 0;
}

.adminSubNav__item--child {
  margin-left: 26px;
  padding-left: 12px;
  font-size: var(--font-size-xs);
}

.adminSubNav__item--danger {
  border-color: rgb(235 36 82 / 0.34);
}

.adminSubNav__item--danger.active {
  background: rgb(235 36 82 / 0.18);
  border-color: rgb(235 36 82 / 0.62);
}

.adminSubNav__icon {
  width: 1.6rem;
  height: 1.6rem;
  border-radius: var(--radius-sm);
  border: 1px solid rgb(230 242 255 / 0.18);
  background: rgb(21 29 40 / 0.72);
  display: grid;
  place-items: center;
  font-size: 0.64rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: rgb(230 242 255 / 0.84);
  flex-shrink: 0;
}

.adminSubNav__item.active .adminSubNav__icon {
  border-color: rgb(1 166 255 / 0.5);
  color: var(--admin-nav-text);
  background: rgb(1 166 255 / 0.2);
}

.adminSubNav__collapsible {
  display: grid;
  gap: 7px;
}

.adminSubNav__children {
  display: grid;
  gap: 7px;
}

.adminSubNav__collapseToggle {
  width: 34px;
  border: 1px solid rgb(230 242 255 / 0.12);
  border-radius: var(--radius-sm);
  background: var(--admin-nav-surface);
  color: rgb(230 242 255 / 0.76);
  display: grid;
  place-items: center;
  font-size: 0.86rem;
  line-height: 1;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast);
}

.adminSubNav__collapseToggle:hover {
  border-color: rgb(1 166 255 / 0.4);
  color: var(--admin-nav-text);
}

.adminSubNav__collapseToggle.active {
  border-color: rgb(1 166 255 / 0.58);
  color: var(--admin-nav-text);
  background: rgb(1 166 255 / 0.16);
}

.adminSubNavReveal-enter-active,
.adminSubNavReveal-leave-active {
  transition: opacity var(--motion-fast), transform var(--motion-fast);
}

.adminSubNavReveal-enter-from,
.adminSubNavReveal-leave-to {
  opacity: 0;
  transform: translateY(-3px);
}

@media (max-width: 900px) {
  .adminSubNav {
    border-radius: var(--radius-md);
    padding: 10px;
  }

  .adminSubNav__groups {
    margin-top: 8px;
    gap: 10px;
  }

  .adminSubNav__groupTitle {
    font-size: 0.66rem;
  }
}
</style>
