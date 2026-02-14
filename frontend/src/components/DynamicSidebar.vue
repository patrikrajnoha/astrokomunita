<template>
  <aside v-if="isDesktop && activeScope && renderedSections.length > 0" class="rightCol">
    <component
      :is="resolveSidebarComponent(section)"
      v-for="section in renderedSections"
      :key="resolveItemKey(section)"
      v-bind="propsForSection(section)"
    />
  </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'
import {
  getEnabledSidebarSections,
  resolveSidebarComponent,
} from '@/sidebar/engine'

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
const isDesktop = ref(typeof window === 'undefined' ? true : window.matchMedia('(min-width: 1280px)').matches)
const currentItems = ref([])

const activeScope = computed(() => resolveSidebarScopeFromPath(route.path || ''))

const renderedSections = computed(() => {
  return getEnabledSidebarSections(currentItems.value).filter((section) => {
    return Boolean(resolveSidebarComponent(section))
  })
})

const resolveItemKey = (section) => {
  if (section.kind === 'custom_component') {
    return `custom:${section.custom_component_id}`
  }

  return `builtin:${section.section_key}`
}

const propsForSection = (section) => {
  const sectionKey = section.section_key

  if (section.kind === 'custom_component') {
    return {
      component: section.custom_component || null,
    }
  }

  if (sectionKey === 'observing_conditions') {
    return {
      lat: props.observingLat,
      lon: props.observingLon,
      date: props.observingDate,
      tz: props.observingTz,
      locationName: props.observingLocationName,
    }
  }

  if (sectionKey === 'nasa_apod' || sectionKey === 'next_event' || sectionKey === 'latest_articles') {
    const staticSection = currentItems.value.find((item) => item.section_key === sectionKey)
    return staticSection?.title ? { title: staticSection.title } : {}
  }

  return {}
}

const syncScope = async (scope) => {
  if (!scope || !isDesktop.value) {
    currentItems.value = []
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
  gap: 1rem;
}
</style>
