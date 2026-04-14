<template>
  <aside v-if="isDesktop && activeScope && renderedSections.length > 0" class="rightCol sidebar-dense">
    <TransitionGroup name="sidebarSection" tag="div">
      <section
        v-for="section in renderedSections"
        :key="resolveItemKey(section)"
        class="sidebarSection"
      >
        <component
          :is="resolveSidebarComponent(section)"
          class="sidebarSection__content sidebarDenseCard"
          v-bind="propsForSection(section)"
        />
      </section>
    </TransitionGroup>
  </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import {
  SIDEBAR_WIDGET_BUNDLE_SECTION_KEYS,
  useSidebarWidgetBundle,
} from '@/composables/useSidebarWidgetBundle'
import { DEFAULT_SIDEBAR_SCOPE, resolveSidebarScopeFromPath } from '@/utils/sidebarScope'
import {
  GUEST_OBSERVING_PROMPT_SECTION_KEY,
  getEnabledSidebarSections,
  resolveSidebarComponent,
} from '@/sidebar/engine'
import { resolvePreferredSidebarWidgetKeys } from '@/sidebar/preferences'

const props = defineProps({
  observingLat: {
    type: [Number, String],
    default: null,
  },
  observingLon: {
    type: [Number, String],
    default: null,
  },
  observingDate: {
    type: String,
    default: '',
  },
  observingTz: {
    type: String,
    default: 'Europe/Bratislava',
  },
  observingLocationName: {
    type: String,
    default: '',
  },
})

const route = useRoute()
const sidebarConfigStore = useSidebarConfigStore()
const auth = useAuthStore()
const preferences = useEventPreferencesStore()
const isDesktop = ref(typeof window === 'undefined' ? true : window.matchMedia('(min-width: 1280px)').matches)
const currentItems = ref([])

const activeScope = computed(() => resolveSidebarScopeFromPath(route.path || ''))
const isGuest = computed(() => !auth.isAuthed)
const toFiniteCoordinate = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string') return null
  const normalized = value.trim()
  if (!normalized) return null
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}
const hasObservingLocation = computed(() => {
  return toFiniteCoordinate(props.observingLat) !== null && toFiniteCoordinate(props.observingLon) !== null
})
const preferredSidebarWidgetKeys = computed(() => {
  return resolvePreferredSidebarWidgetKeys({
    isAuthed: auth.isAuthed,
    preferences,
    scope: activeScope.value || DEFAULT_SIDEBAR_SCOPE,
  })
})

const hasUserPreferenceOverride = computed(() => {
  if (!auth.isAuthed || !preferences.loaded) return false
  return (
    typeof preferences.hasSidebarWidgetOverrideForScope === 'function' &&
    (preferences.hasSidebarWidgetOverrideForScope(activeScope.value || DEFAULT_SIDEBAR_SCOPE) ||
      preferences.hasSidebarWidgetOverrideForScope(DEFAULT_SIDEBAR_SCOPE))
  )
})

const renderedSections = computed(() => {
  return getEnabledSidebarSections(currentItems.value, {
    isGuest: isGuest.value,
    collapseObservingForMissingLocation: !isGuest.value && !hasObservingLocation.value,
    preferredSectionKeys: preferredSidebarWidgetKeys.value,
    allowUserPreferenceOverride: hasUserPreferenceOverride.value,
  }).filter((section) => Boolean(resolveSidebarComponent(section)))
})
const renderedBuiltinSectionKeySet = computed(() => {
  return new Set(
    renderedSections.value
      .filter((section) => section?.kind !== 'custom_component')
      .map((section) => String(section?.section_key || ''))
      .filter((key) => key !== ''),
  )
})
const preloadableSectionKeys = computed(() => (
  renderedSections.value
    .filter((section) => section?.kind !== 'custom_component')
    .map((section) => String(section?.section_key || ''))
    .filter((sectionKey) => SIDEBAR_WIDGET_BUNDLE_SECTION_KEYS.has(sectionKey))
))
const sidebarBundleQuery = computed(() => {
  const query = {}

  const lat = toFiniteCoordinate(props.observingLat)
  const lon = toFiniteCoordinate(props.observingLon)
  const tz = String(props.observingTz || '').trim()

  if (lat !== null && lon !== null) {
    query.lat = lat
    query.lon = lon
    if (tz) {
      query.tz = tz
    }
  }

  return query
})
const {
  bundledSectionPayloads,
  bundlePending: sidebarBundlePending,
  resetBundle: resetSidebarBundle,
  syncBundle: syncSidebarBundle,
} = useSidebarWidgetBundle({
  enabled: computed(() => isDesktop.value && Boolean(activeScope.value)),
  query: sidebarBundleQuery,
  sectionKeys: preloadableSectionKeys,
})

const resolveItemKey = (section) => {
  if (section.kind === 'custom_component') {
    return `custom:${section.custom_component_id}`
  }

  return `builtin:${section.section_key}`
}

const propsForSection = (section) => {
  const sectionKey = section.section_key
  const builtins = renderedBuiltinSectionKeySet.value

  if (section.kind === 'custom_component') {
    return {
      component: section.custom_component || null,
    }
  }

  if (
    sectionKey === 'observing_conditions'
    || sectionKey === 'observing_weather'
    || sectionKey === 'space_weather'
    || sectionKey === 'aurora_watch'
    || sectionKey === 'night_sky'
    || sectionKey === 'iss_pass'
    || sectionKey === 'moon_events'
  ) {
    return {
      lat: props.observingLat,
      lon: props.observingLon,
      date: props.observingDate,
      tz: props.observingTz,
      locationName: props.observingLocationName,
      initialPayload: bundledSectionPayloads.value?.[sectionKey],
      bundlePending: sidebarBundlePending.value,
    }
  }

  if (sectionKey === 'moon_phases') {
    return {
      lat: props.observingLat,
      lon: props.observingLon,
      date: props.observingDate,
      tz: props.observingTz,
      locationName: props.observingLocationName,
      showSpecialEvents: !builtins.has('moon_events'),
    }
  }

  if (
    sectionKey === 'nasa_apod'
    || sectionKey === 'next_event'
    || sectionKey === 'next_eclipse'
    || sectionKey === 'next_meteor_shower'
    || sectionKey === 'constellations_now'
    || sectionKey === 'neo_watchlist'
    || sectionKey === 'upcoming_launches'
    || sectionKey === 'latest_articles'
    || sectionKey === 'upcoming_events'
  ) {
    return {
      ...(section?.title ? { title: section.title } : {}),
      initialPayload: bundledSectionPayloads.value?.[sectionKey],
      bundlePending: sidebarBundlePending.value,
    }
  }

  return {}
}

const syncScope = async (scope) => {
  if (!scope || !isDesktop.value) {
    currentItems.value = []
    resetSidebarBundle()
    return
  }

  const items = await sidebarConfigStore.fetchScope(scope)
  currentItems.value = items
}

const updateDesktopState = () => {
  if (typeof window === 'undefined') return
  isDesktop.value = window.matchMedia('(min-width: 1280px)').matches
}

watch(
  () => activeScope.value,
  async (scope) => {
    await syncScope(scope)
  },
  { immediate: true },
)

watch(
  () => route.fullPath,
  async () => {
    if (!isDesktop.value || !activeScope.value) return
    await syncSidebarBundle(preloadableSectionKeys.value, { force: true })
  },
)

// Re-fetch when the admin invalidates the scope cache (delete byScope[scope]).
// Only acts when we already have loaded data — prevents a double-fetch on initial mount.
watch(
  () => activeScope.value ? sidebarConfigStore.byScope?.[activeScope.value] : undefined,
  async (cached) => {
    if (!cached && activeScope.value && isDesktop.value && currentItems.value.length > 0) {
      await syncScope(activeScope.value)
    }
  },
)

watch(
  () => isDesktop.value,
  async (value) => {
    if (!value) {
      currentItems.value = []
      return
    }

    await syncScope(activeScope.value)
  },
)

onMounted(() => {
  updateDesktopState()
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateDesktopState)
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateDesktopState)
  }
})
</script>

<style scoped>
.rightCol {
  display: grid;
  gap: var(--sb-gap-md, 0.75rem);
}

.sidebarSection {
  margin: 0;
}

.sidebarSection + .sidebarSection {
  border-top: 1px solid var(--divider-color);
  padding-top: var(--sb-gap-md, 0.75rem);
}

.sidebarSection-enter-active,
.sidebarSection-leave-active {
  transition: opacity var(--motion-fast), transform var(--motion-fast);
}

.sidebarSection-enter-from,
.sidebarSection-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.sidebarSection-move {
  transition: transform var(--motion-base);
}

.sidebarSection__content {
  min-width: 0;
}

.sidebarDenseCard {
  border-radius: 0.9rem;
}

.sidebar-dense :deep(.panel) {
  gap: var(--sb-gap-sm, 0.5rem);
}

.sidebar-dense :deep(.panelTitle),
.sidebar-dense :deep(.sidebarSection__header) {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.22;
}

.sidebar-dense :deep(.panelLoading) {
  gap: var(--sb-gap-xs, 0.3rem);
}

.sidebar-dense :deep(.panelActions) {
  gap: var(--sb-gap-xs, 0.3rem);
  padding-top: var(--sb-gap-xs, 0.3rem);
}

.sidebar-dense :deep(.stateTitle) {
  font-size: 0.86rem;
  line-height: 1.25;
}

.sidebar-dense :deep(.stateText) {
  margin-top: 0.2rem;
  font-size: 0.8rem;
  line-height: 1.3;
}

.sidebar-dense :deep(.ghostbtn),
.sidebar-dense :deep(.actionbtn),
.sidebar-dense :deep(.showMoreLink) {
  min-height: 1.9rem;
  padding: 0.4rem 0.68rem;
  border-radius: 0.7rem;
  font-size: 0.78rem;
  line-height: 1.15;
}

.sidebar-dense :deep(.nasaActionBtn) {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border-radius: 0 !important;
  font-size: 0.72rem;
  line-height: 1.12;
}

.sidebar-dense :deep(.nasaCard .ghostbtn) {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border-radius: 0 !important;
  font-size: 0.72rem;
  line-height: 1.12;
}

.sidebar-dense :deep(.eventActionBtn),
.sidebar-dense :deep(.eventGhostBtn) {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border-radius: 0 !important;
  font-size: 0.72rem;
  line-height: 1.12;
}

.sidebar-dense :deep(.upcomingMoreLink) {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border-radius: 0 !important;
  font-size: 0.72rem;
  line-height: 1.12;
}
</style>
