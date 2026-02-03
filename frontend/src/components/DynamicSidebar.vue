<template>
  <aside v-if="isDesktop && sections.length > 0" class="rightCol">
    <component
      v-for="section in sections"
      :key="section.key"
      :is="getWidgetComponent(section.key)"
      :title="section.title"
    />
  </aside>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import NextEventWidget from './widgets/NextEventWidget.vue'
import LatestArticlesWidget from './widgets/LatestArticlesWidget.vue'
import NasaApodWidget from './widgets/NasaApodWidget.vue'

export default {
  name: 'DynamicSidebar',
  components: {
    NextEventWidget,
    LatestArticlesWidget,
    NasaApodWidget
  },
  setup() {
    const sections = ref([])
    const loading = ref(true)

    // Media query for desktop detection
    const isDesktop = computed(() => {
      if (typeof window === 'undefined') return true
      return window.matchMedia('(min-width: 1024px)').matches
    })

    // Widget component mapper
    const widgetComponents = {
      'next_event': 'NextEventWidget',
      'latest_articles': 'LatestArticlesWidget', 
      'nasa_apod': 'NasaApodWidget'
    }

    const getWidgetComponent = (key) => {
      return widgetComponents[key] || null
    }

    const fetchSidebarSections = async () => {
      if (!isDesktop.value) {
        loading.value = false
        return
      }

      try {
        const response = await api.get('/sidebar-sections')
        sections.value = response.data?.data || []
      } catch (error) {
        console.error('Failed to fetch sidebar sections:', error)
        sections.value = []
      } finally {
        loading.value = false
      }
    }

    // Listen for window resize to update desktop detection
    const updateDesktopDetection = () => {
      if (isDesktop.value && sections.value.length === 0 && !loading.value) {
        fetchSidebarSections()
      }
    }

    onMounted(() => {
      fetchSidebarSections()
      if (typeof window !== 'undefined') {
        window.addEventListener('resize', updateDesktopDetection)
      }
    })

    return {
      sections,
      loading,
      isDesktop,
      getWidgetComponent
    }
  }
}
</script>

<style scoped>
.rightCol {
  position: sticky;
  top: 1.25rem;
  align-self: start;
  display: grid;
  gap: 1rem;
}

/* Responsive: hide sidebar completely on mobile */
@media (max-width: 1023px) {
  .rightCol {
    display: none !important;
  }
}
</style>
