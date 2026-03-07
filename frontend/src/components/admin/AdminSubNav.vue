<script setup>
import { computed, defineComponent, h, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ADMIN_SECTION_KEYS, getAdminSectionLabel } from '@/components/admin/adminSections'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const wipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const routeName = computed(() => String(route.name || ''))
const expandedGroups = ref(new Set())

const createAdminIcon = (paths, filled = false) =>
  defineComponent({
    name: filled ? 'AdminSubNavFilledIcon' : 'AdminSubNavOutlineIcon',
    render() {
      return h(
        'svg',
        {
          class: 'adminSubNav__iconSvg',
          viewBox: '0 0 24 24',
          fill: filled ? 'currentColor' : 'none',
          stroke: filled ? 'none' : 'currentColor',
          'stroke-width': filled ? undefined : '1.9',
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
        },
        paths.map((path, index) => h('path', { key: `path-${index}`, d: path })),
      )
    },
  })

const adminIcons = {
  overview: createAdminIcon([
    'M3.75 10.5 12 4l8.25 6.5',
    'M5.75 9.75V19a1 1 0 0 0 1 1h3.75v-5.25a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1V20h3.75a1 1 0 0 0 1-1V9.75',
  ]),
  events: createAdminIcon([
    'M7 3.75v2.5',
    'M17 3.75v2.5',
    'M4.75 9.25h14.5',
    'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v11A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V7A1.25 1.25 0 0 1 6 5.75Z',
  ]),
  content: createAdminIcon([
    'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v10A1.25 1.25 0 0 1 18 18.25H6A1.25 1.25 0 0 1 4.75 17V7A1.25 1.25 0 0 1 6 5.75Z',
    'M8 9h8',
    'M8 12h8',
    'M8 15h5',
  ]),
  featured: createAdminIcon([
    'M12 3l2.6 5.5 6 .9-4.3 4.2 1 6-5.3-2.9-5.3 2.9 1-6L3.4 9.4l6-.9L12 3Z',
  ]),
  contests: createAdminIcon([
    'M7.5 5.5h9v3a2.5 2.5 0 0 0 2.5 2.5v1A2.5 2.5 0 0 0 16.5 14.5v4h-9v-4A2.5 2.5 0 0 0 5 12v-1A2.5 2.5 0 0 0 7.5 8.5v-3Z',
    'M10 9.5h4',
    'M10 13h4',
  ]),
  community: createAdminIcon([
    'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    'M4.75 20a7.25 7.25 0 0 1 14.5 0',
  ]),
  sidebar: createAdminIcon([
    'M4.75 5.75h5.5v12.5h-5.5z',
    'M11.75 5.75h7.5v4h-7.5z',
    'M11.75 11.25h7.5v7h-7.5z',
  ]),
  bots: createAdminIcon([
    'M8 9h8v7H8z',
    'M10 6h4',
    'M12 3.75v2.25',
    'M9 14h0',
    'M15 14h0',
  ]),
  performance: createAdminIcon([
    'M4.75 18.25h14.5',
    'M8 16V9.75',
    'M12 16V6.75',
    'M16 16v-4.5',
  ]),
  bannedWords: createAdminIcon([
    'M6.5 6.5l11 11',
    'M17.5 6.5l-11 11',
    'M4.75 12a7.25 7.25 0 1 0 14.5 0 7.25 7.25 0 0 0-14.5 0Z',
  ]),
}

const overviewItem = {
  key: 'overview',
  label: 'Prehľad',
  to: { name: 'admin.dashboard' },
  iconKey: 'overview',
  routeNames: ['admin.dashboard'],
}

const navGroups = computed(() => {
  const contentChildren = [
    {
      key: 'featured-events',
      label: 'Vybrané udalosti',
      to: { name: 'admin.featured-events' },
      iconKey: 'featured',
      routeNames: ['admin.featured-events'],
    },
    {
      key: 'contests',
      label: 'Súťaže',
      to: { name: 'admin.contests' },
      iconKey: 'contests',
      routeNames: ['admin.contests'],
    },
    ...(wipEnabled
      ? [{
          key: 'banned-words',
          label: 'Zakázané slová',
          to: { name: 'admin.banned-words' },
          iconKey: 'bannedWords',
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
          iconKey: 'events',
          section: ADMIN_SECTION_KEYS.EVENTS,
        },
        {
          key: 'content',
          label: getAdminSectionLabel(ADMIN_SECTION_KEYS.CONTENT),
          to: { name: 'admin.blog' },
          iconKey: 'content',
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
          iconKey: 'community',
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
          iconKey: 'sidebar',
          routeNames: ['admin.sidebar'],
        },
        {
          key: 'bots',
          label: 'Boti',
          to: { name: 'admin.bots' },
          iconKey: 'bots',
          routeNames: ['admin.bots', 'admin.astrobot'],
          routeNamePrefixes: ['admin.bots'],
        },
        {
          key: 'performance',
          label: 'Výkonnosť',
          to: { name: 'admin.performance-metrics' },
          iconKey: 'performance',
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
          iconKey: 'content',
          section: ADMIN_SECTION_KEYS.CONTENT,
        },
      ],
    },
  ]
})

function hasChildren(item) {
  return Array.isArray(item.children) && item.children.length > 0
}

function resolveItemIcon(item) {
  const key = String(item?.iconKey || '')
  return adminIcons[key] || adminIcons.content
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
      <span class="adminSubNav__icon" aria-hidden="true">
        <component :is="resolveItemIcon(overviewItem)" />
      </span>
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
                  <span class="adminSubNav__icon" aria-hidden="true">
                    <component :is="resolveItemIcon(item)" />
                  </span>
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
                    <span class="adminSubNav__icon" aria-hidden="true">
                      <component :is="resolveItemIcon(child)" />
                    </span>
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
              <span class="adminSubNav__icon" aria-hidden="true">
                <component :is="resolveItemIcon(item)" />
              </span>
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  background: var(--color-card);
  box-shadow: var(--shadow-soft);
  color: var(--color-text-primary);
}

.adminSubNav__head {
  border-bottom: 1px solid var(--color-divider);
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
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
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
  color: rgb(var(--color-text-secondary-rgb) / 0.68);
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  color: var(--color-text-secondary);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  background: rgb(var(--bg-surface-rgb) / 0.88);
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    color var(--motion-fast),
    transform 120ms ease;
}

.adminSubNav__item:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.45);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.adminSubNav__item.active {
  background: rgb(var(--color-accent-rgb) / 0.16);
  border-color: rgb(var(--color-accent-rgb) / 0.56);
  color: var(--color-text-primary);
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
  border-color: rgb(var(--color-danger-rgb) / 0.36);
}

.adminSubNav__item--danger.active {
  background: rgb(var(--color-danger-rgb) / 0.18);
  border-color: rgb(var(--color-danger-rgb) / 0.62);
}

.adminSubNav__item:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  box-shadow: var(--focus-ring);
}

.adminSubNav__icon {
  width: 1rem;
  height: 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: currentColor;
  opacity: 0.92;
  flex-shrink: 0;
}

.adminSubNav__iconSvg {
  width: 1rem;
  height: 1rem;
  display: block;
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: rgb(var(--bg-surface-rgb) / 0.88);
  color: var(--color-text-secondary);
  display: grid;
  place-items: center;
  font-size: 0.86rem;
  line-height: 1;
  cursor: pointer;
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    color var(--motion-fast),
    transform 120ms ease;
}

.adminSubNav__collapseToggle:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.45);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.adminSubNav__collapseToggle.active {
  border-color: rgb(var(--color-accent-rgb) / 0.58);
  color: var(--color-text-primary);
  background: rgb(var(--color-accent-rgb) / 0.16);
}

.adminSubNav__collapseToggle:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  box-shadow: var(--focus-ring);
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
