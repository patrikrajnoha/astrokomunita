<template src="./mainNavbar/MainNavbar.template.html"></template>

<script setup>
import { computed, defineComponent, h, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useBadgeAnimateOnIncrease } from '@/composables/useBadgeAnimateOnIncrease'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const route = useRoute()
const router = useRouter()
const { showBrandLogo } = defineProps({
  showBrandLogo: {
    type: Boolean,
    default: true,
  },
})
const isWipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)
const isAdminOpen = ref(false)
const adminWrapperRef = ref(null)
const isNotificationsOpen = ref(false)
const isCreatePickerOpen = ref(false)
const createPickerWrapperRef = ref(null)
const unreadCount = computed(() => Number(notifications.unreadCount || 0))
const unreadCountHydrated = computed(() => Boolean(notifications.unreadCountHydrated))
const { shouldAnimate: shouldAnimateUnreadBadge } = useBadgeAnimateOnIncrease(unreadCount, {
  readyRef: unreadCountHydrated,
})

const isMoreActive = computed(() => {
  return route.path.startsWith('/settings') || (isWipEnabled && route.path === '/creator-studio')
})

const isAdminActive = computed(() => {
  return route.path.startsWith('/admin/')
})

const createNavIconComponent = (paths, filled = false) =>
  defineComponent({
    name: filled ? 'NavFilledIcon' : 'NavOutlineIcon',
    render() {
      return h(
        'svg',
        {
          class: ['navIcon', filled ? 'navIcon--filled' : 'navIcon--outline'],
          width: '20',
          height: '20',
          viewBox: '0 0 24 24',
          fill: filled ? 'currentColor' : 'none',
          stroke: filled ? 'none' : 'currentColor',
          'stroke-width': filled ? undefined : '1.9',
          'fill-rule': filled ? 'evenodd' : undefined,
          'clip-rule': filled ? 'evenodd' : undefined,
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
        },
        paths.map((path, index) => h('path', { key: `path-${index}`, d: path })),
      )
    },
  })

const homeGlobeOutlineIcon = defineComponent({
  name: 'HomeGlobeOutlineIcon',
  render() {
    return h(
      'svg',
      {
        class: ['navIcon', 'navIcon--outline'],
        width: '20',
        height: '20',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': '1.9',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'aria-hidden': 'true',
      },
      [
        h('circle', { cx: '12', cy: '12', r: '8.25' }),
        h('path', {
          d: 'M8.1 8.2 9.35 8.95 9.05 10.1 9.95 11 9.45 12.3 8.45 12.8 7.95 14.2 6.95 13.4 7.25 11.9 6.55 10.8 7.1 9.4Z',
          fill: 'currentColor',
          stroke: 'none',
        }),
        h('path', {
          d: 'M12.2 7.2 14 7.6 15.1 8.4 16.25 8.3 17.05 9.35 16.35 10.55 15.2 10.65 14.6 11.75 15.2 12.9 14.5 14.05 13.1 13.9 12.45 12.85 11.55 12.2 11.8 10.75 12.85 9.9 12.65 8.75Z',
          fill: 'currentColor',
          stroke: 'none',
        }),
      ],
    )
  },
})

const homeGlobeFilledIcon = defineComponent({
  name: 'HomeGlobeFilledIcon',
  render() {
    return h(
      'svg',
      {
        class: ['navIcon', 'navIcon--filled'],
        width: '20',
        height: '20',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'aria-hidden': 'true',
      },
      [
        h('circle', {
          cx: '12',
          cy: '12',
          r: '8.25',
          fill: 'currentColor',
          'fill-opacity': '0.18',
          stroke: 'currentColor',
          'stroke-width': '1.7',
        }),
        h('path', {
          d: 'M8.1 8.2 9.35 8.95 9.05 10.1 9.95 11 9.45 12.3 8.45 12.8 7.95 14.2 6.95 13.4 7.25 11.9 6.55 10.8 7.1 9.4Z',
          fill: 'currentColor',
          stroke: 'none',
        }),
        h('path', {
          d: 'M12.2 7.2 14 7.6 15.1 8.4 16.25 8.3 17.05 9.35 16.35 10.55 15.2 10.65 14.6 11.75 15.2 12.9 14.5 14.05 13.1 13.9 12.45 12.85 11.55 12.2 11.8 10.75 12.85 9.9 12.65 8.75Z',
          fill: 'currentColor',
          stroke: 'none',
        }),
      ],
    )
  },
})

const navIcons = {
  home: {
    outline: homeGlobeOutlineIcon,
    filled: homeGlobeFilledIcon,
  },
  search: {
    outline: createNavIconComponent(['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5']),
    filled: createNavIconComponent(
      ['M10.75 3.5a7.25 7.25 0 1 0 4.61 12.85l4.12 4.12a1 1 0 1 0 1.42-1.42l-4.12-4.12A7.25 7.25 0 0 0 10.75 3.5Z'],
      true,
    ),
  },
  notifications: {
    outline: createNavIconComponent([
      'M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z',
      'M9.5 18a2.5 2.5 0 0 0 5 0',
    ]),
    filled: createNavIconComponent(
      [
        'M12 3a5.75 5.75 0 0 0-5.75 5.75c0 2.42-.65 4.02-1.61 5.28-.53.69-.02 1.72.88 1.72h13c.9 0 1.4-1.03.88-1.72-.96-1.26-1.61-2.86-1.61-5.28A5.75 5.75 0 0 0 12 3Z',
        'M9.55 17.1a2.45 2.45 0 0 0 4.9 0h-4.9Z',
      ],
      true,
    ),
  },
  events: {
    outline: createNavIconComponent([
      'M7 3.75v2.5',
      'M17 3.75v2.5',
      'M4.75 9.25h14.5',
      'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v11A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V7A1.25 1.25 0 0 1 6 5.75Z',
      'M8.25 13h3.5',
      'M8.25 16h6.5',
    ]),
    filled: createNavIconComponent(
      [
        'M7.75 3.5a1 1 0 0 1 1 1v1.25h6.5V4.5a1 1 0 1 1 2 0v1.3A2.25 2.25 0 0 1 19.5 8v10.25A2.25 2.25 0 0 1 17.25 20.5H6.75A2.25 2.25 0 0 1 4.5 18.25V8A2.25 2.25 0 0 1 6.75 5.8V4.5a1 1 0 0 1 1-1Z',
        'M4.5 10.25h15v-.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 9.75v.5Z',
      ],
      true,
    ),
  },
  learn: {
    outline: createNavIconComponent([
      'M6 4.75h12A1.25 1.25 0 0 1 19.25 6v12A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V6A1.25 1.25 0 0 1 6 4.75Z',
      'M8 8h2.75v3.5H8Z',
      'M12.25 8h3.75',
      'M12.25 10.25h3.75',
      'M8 13.5h8',
      'M8 16h8',
    ]),
    filled: createNavIconComponent(
      [
        'M6 4.5h12A1.5 1.5 0 0 1 19.5 6v12a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V6A1.5 1.5 0 0 1 6 4.5Zm2 3v4h3v-4H8Zm4.25 0V9h3.75V7.5h-3.75Zm0 2.75v1.5h3.75v-1.5h-3.75ZM8 13.5V15h8v-1.5H8Zm0 2.5v1h8v-1H8Z',
      ],
      true,
    ),
  },
  admin: {
    outline: createNavIconComponent([
      'M4 18.25h16',
      'M5.1 18.25 6.35 9.5l4.05 2.95L12 7.5l1.6 4.95 4.05-2.95 1.25 8.75',
    ]),
    filled: createNavIconComponent([
      'M4 18.75h16l-1.25-9.2-4.15 3.05L12 7.35 9.4 12.6 5.25 9.55 4 18.75Z',
    ], true),
  },
  user: {
    outline: createNavIconComponent([
      'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
      'M4.75 20a7.25 7.25 0 0 1 14.5 0',
    ]),
    filled: createNavIconComponent(
      [
        'M12 3.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z',
        'M12 13.75c-4.2 0-7.75 2.7-8.55 6.4-.1.47.27.9.75.9h15.6c.48 0 .85-.43.75-.9-.8-3.7-4.35-6.4-8.55-6.4Z',
      ],
      true,
    ),
  },
  settings: {
    outline: createNavIconComponent([
      'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.757.426 1.757 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.757-2.924 1.757-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.757-.426-1.757-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.607 2.296.07 2.572-1.065Z',
      'M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',
    ]),
    filled: createNavIconComponent(
      [
        'M10.483 1.904a1.875 1.875 0 0 1 1.5 0 1.875 1.875 0 0 1 1.734 1.113l.332.744a1.875 1.875 0 0 0 2.28 1.018l.781-.23a1.875 1.875 0 0 1 2.188.918l.75 1.299a1.875 1.875 0 0 1-.454 2.358l-.578.5a1.875 1.875 0 0 0 0 2.752l.578.5a1.875 1.875 0 0 1 .454 2.358l-.75 1.3a1.875 1.875 0 0 1-2.188.917l-.78-.23a1.875 1.875 0 0 0-2.281 1.018l-.332.744a1.875 1.875 0 0 1-1.734 1.113h-1.5a1.875 1.875 0 0 1-1.734-1.113l-.332-.744a1.875 1.875 0 0 0-2.28-1.018l-.781.23a1.875 1.875 0 0 1-2.188-.918l-.75-1.299a1.875 1.875 0 0 1 .454-2.358l.578-.5a1.875 1.875 0 0 0 0-2.752l-.578-.5a1.875 1.875 0 0 1-.454-2.358l.75-1.3a1.875 1.875 0 0 1 2.188-.917l.78.23a1.875 1.875 0 0 0 2.281-1.018l.332-.744A1.875 1.875 0 0 1 10.483 1.904Z',
        'M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',
      ],
      true,
    ),
  },
  creatorStudio: {
    outline: createNavIconComponent([
      'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z',
      'M18.5 3.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z',
    ]),
    filled: createNavIconComponent(
      [
        'M12 2.75 14.1 8.3l5.55 2.1-5.55 2.1L12 18.05l-2.1-5.55-5.55-2.1 5.55-2.1L12 2.75Z',
        'M18.5 2.9l.78 2 .02.03 2 .78-2 .78-.8 2.02-.78-2.02-2-.78 2-.78z',
      ],
      true,
    ),
  },
}

const primaryLinks = computed(() => {
  const links = [
    {
      key: 'home',
      to: '/',
      label: 'Domov',
      icon: 'D',
      iconOutline: navIcons.home.outline,
      iconFilled: navIcons.home.filled,
    },
    {
      key: 'search',
      to: '/search',
      label: 'Preskúmať',
      icon: 'P',
      iconOutline: navIcons.search.outline,
      iconFilled: navIcons.search.filled,
      matchPrefix: '/search',
    },
    {
      key: 'events',
      to: '/events',
      label: 'Udalosti',
      icon: 'U',
      iconOutline: navIcons.events.outline,
      iconFilled: navIcons.events.filled,
      matchPrefix: '/events',
    },
    {
      key: 'learn',
      to: '/clanky',
      label: 'Články',
      icon: 'V',
      iconOutline: navIcons.learn.outline,
      iconFilled: navIcons.learn.filled,
      matchPrefix: '/clanky',
    },
  ]
  if (auth.isAuthed) {
    links.splice(2, 0, {
      key: 'notifications',
      to: '/notifications',
      label: 'Notifikácie',
      icon: 'U',
      iconOutline: navIcons.notifications.outline,
      iconFilled: navIcons.notifications.filled,
      matchPrefix: '/notifications',
      badge: notifications.unreadBadge,
    })
  }

  if (auth.user) {
    links.push({
      key: 'profile',
      to: '/profile',
      label: 'Profil',
      icon: 'P',
      iconOutline: navIcons.user.outline,
      iconFilled: navIcons.user.filled,
      matchPrefix: '/profile',
    })
  }

  if (auth.isAuthed) {
    links.push({
      key: 'settings',
      to: '/settings',
      label: 'Nastavenia',
      icon: 'S',
      iconOutline: navIcons.settings.outline,
      iconFilled: navIcons.settings.filled,
      matchPrefix: '/settings',
    })
  }

  if (auth.isAdmin || auth.isEditor) {
    links.push({
      key: 'admin',
      to: auth.isAdmin ? { name: 'admin.dashboard' } : { name: 'admin.blog' },
      label: auth.isAdmin ? 'Admin' : 'Editor',
      icon: 'A',
      iconOutline: navIcons.admin.outline,
      iconFilled: navIcons.admin.filled,
      matchPrefix: '/admin',
    })
  }

  if (isWipEnabled) {
    links.push({
      key: 'creator-studio',
      to: '/creator-studio',
      label: 'Štúdio tvorcu',
      icon: 'C',
      iconOutline: navIcons.creatorStudio.outline,
      iconFilled: navIcons.creatorStudio.filled,
      matchPrefix: '/creator-studio',
    })
  }

  return links
})

const isPrimaryLinkActive = (item, isActive, isExactActive) => {
  if (!item) return false
  const targetPath = typeof item.to === 'string' ? item.to : item.to?.path
  // Domov je aktívny iba na koreňovej route.
  if (targetPath === '/') {
    return Boolean(isExactActive)
  }

  if (item.matchPrefix) {
    return route.path.startsWith(item.matchPrefix)
  }

  return Boolean(isActive)
}

const toggleMore = () => {
  closeNotifications()
  closeCreatePicker()
  isMoreOpen.value = !isMoreOpen.value
}

const closeMore = () => {
  isMoreOpen.value = false
}

const toggleAdmin = () => {
  closeNotifications()
  closeCreatePicker()
  isAdminOpen.value = !isAdminOpen.value
}

const closeAdmin = () => {
  isAdminOpen.value = false
}

const closeNotifications = () => {
  isNotificationsOpen.value = false
}

const firstElementRef = (value) => {
  if (Array.isArray(value)) return value[0] || null
  return value || null
}

const handleClickOutside = (event) => {
  const target = event.target
  const wrapper = firstElementRef(moreWrapperRef.value)
  const adminWrapper = firstElementRef(adminWrapperRef.value)
  const createPickerWrapper = firstElementRef(createPickerWrapperRef.value)

  if (isMoreOpen.value && wrapper instanceof Element && target instanceof Node && !wrapper.contains(target)) {
    closeMore()
  }
  if (isAdminOpen.value && adminWrapper instanceof Element && target instanceof Node && !adminWrapper.contains(target)) {
    closeAdmin()
  }
  if (isCreatePickerOpen.value && createPickerWrapper instanceof Element && target instanceof Node && !createPickerWrapper.contains(target)) {
    closeCreatePicker()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isMoreOpen.value) closeMore()
    if (isAdminOpen.value) closeAdmin()
    if (isCreatePickerOpen.value) closeCreatePicker()
  }
}

const openComposer = (action = 'post') => {
  closeMore()
  closeAdmin()
  closeNotifications()
  closeCreatePicker()

  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent('post:composer:open', {
    detail: {
      action,
    },
  }))
}

const closeCreatePicker = () => {
  isCreatePickerOpen.value = false
}

const toggleCreatePicker = () => {
  closeMore()
  closeAdmin()
  closeNotifications()
  isCreatePickerOpen.value = !isCreatePickerOpen.value
}

const selectCreateType = (type) => {
  closeCreatePicker()

  if (type === 'observation') {
    openComposer('observation')
    return
  }

  if (type === 'poll') {
    openComposer('poll')
    return
  }

  if (type === 'event') {
    openComposer('event')
    return
  }

  openComposer('post')
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('keydown', handleKeydown)
})

watch(
  () => auth.isAuthed,
  (isAuthed) => {
    if (!isAuthed) closeNotifications()
  }
)

watch(
  () => route.fullPath,
  () => {
    closeMore()
    closeAdmin()
    closeNotifications()
    closeCreatePicker()
  }
)
</script>

<style scoped src="./mainNavbar/MainNavbar.css"></style>
