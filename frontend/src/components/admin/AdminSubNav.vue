<script setup>
import { computed, defineComponent, h, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ADMIN_SECTION_KEYS, getAdminSectionLabel } from '@/components/admin/adminSections'
import { useAuthStore } from '@/stores/auth'

defineEmits(['navigate'])

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

<template src="./adminSubNav/AdminSubNav.template.html"></template>

<style scoped src="./adminSubNav/AdminSubNav.css"></style>
