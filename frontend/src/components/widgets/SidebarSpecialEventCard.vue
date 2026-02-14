<template>
  <section class="card panel">
    <div class="panelTitle">{{ safeConfig.title || 'Special Event' }}</div>

    <div v-if="isInactive" class="state">
      <div class="stateTitle">Widget nie je aktivny</div>
      <div class="stateText">Komponent bol deaktivovany v admin rozhrani.</div>
    </div>

    <div v-else>
      <div v-if="safeConfig.imageUrl" class="imageWrap">
        <img :src="safeConfig.imageUrl" alt="" loading="lazy" />
      </div>

      <div class="description">{{ safeConfig.description || 'Doplnte kratky popis udalosti.' }}</div>

      <div v-if="eventMetaText" class="meta">{{ eventMetaText }}</div>
      <div v-else-if="eventError && safeConfig.eventId" class="meta fallback">Udalost nie je dostupna.</div>

      <router-link class="actionbtn" :to="targetRoute">
        {{ safeConfig.buttonLabel || 'Zobrazit detail' }}
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  component: {
    type: Object,
    default: null,
  },
  preview: {
    type: Boolean,
    default: false,
  },
  previewConfig: {
    type: Object,
    default: null,
  },
  previewEvent: {
    type: Object,
    default: null,
  },
})

const eventData = ref(null)
const eventError = ref('')

const safeConfig = computed(() => {
  const source = props.previewConfig || props.component?.config_json || {}
  const eventId = Number.isFinite(Number(source?.eventId)) ? Number(source.eventId) : null
  const fallbackTarget = eventId ? `/events/${eventId}` : '/events'

  return {
    title: String(source?.title || '').trim(),
    description: String(source?.description || '').trim(),
    eventId,
    buttonLabel: String(source?.buttonLabel || '').trim(),
    buttonTarget: String(source?.buttonTarget || '').trim() || fallbackTarget,
    imageUrl: String(source?.imageUrl || '').trim(),
    icon: String(source?.icon || '').trim(),
  }
})

const isInactive = computed(() => {
  if (props.preview) return false
  return props.component && props.component.is_active === false
})

const targetRoute = computed(() => safeConfig.value.buttonTarget || '/events')

const eventMetaText = computed(() => {
  const source = props.preview ? props.previewEvent || eventData.value : eventData.value
  if (!source || !source.title) return ''

  const dateValue = source.start_at || source.max_at || ''
  if (!dateValue) {
    return source.title
  }

  const formatted = formatDate(dateValue)
  return formatted ? `${source.title} - ${formatted}` : source.title
})

const formatDate = (value) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  return new Intl.DateTimeFormat('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const loadEvent = async () => {
  eventData.value = null
  eventError.value = ''

  if (props.preview && props.previewEvent) {
    eventData.value = props.previewEvent
    return
  }

  if (!safeConfig.value.eventId) {
    return
  }

  try {
    const response = await api.get(`/events/${safeConfig.value.eventId}`)
    eventData.value = response?.data?.data || response?.data || null
  } catch (error) {
    eventError.value = error?.response?.data?.message || 'Udalost nie je dostupna.'
  }
}

watch(
  () => [safeConfig.value.eventId, props.preview, props.previewEvent],
  () => {
    loadEvent()
  },
)

onMounted(() => {
  loadEvent()
})
</script>

<style scoped>
.card {
  position: relative;
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
  overflow: hidden;
}

.panel {
  display: grid;
  gap: 0.75rem;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.95rem;
}

.imageWrap {
  width: 100%;
  border-radius: 0.9rem;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
}

.imageWrap img {
  width: 100%;
  display: block;
  object-fit: cover;
}

.description {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.meta {
  color: var(--color-surface);
  font-size: 0.85rem;
  font-weight: 600;
}

.meta.fallback {
  color: var(--color-text-secondary);
  font-weight: 500;
}

.actionbtn {
  width: fit-content;
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}

.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.stateTitle {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--color-surface);
}

.stateText {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
}
</style>

