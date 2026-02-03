<template>
  <section v-if="nasaEnabled" class="card panel">
    <div class="panelTitle">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton nasaThumb"></div>
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-4 w-2/3"></div>
    </div>

    <div v-else-if="!nasaItem || !nasaItem.available" class="state">
      <div class="stateText">Obrázok dňa je momentálne nedostupný</div>
    </div>

    <div v-else class="nasaCard">
      <a
        class="nasaImageLink"
        :href="nasaItem.link"
        target="_blank"
        rel="noopener noreferrer"
      >
        <div class="nasaImageWrap">
          <img
            :src="nasaItem.image_url"
            :alt="nasaItem.title"
            loading="lazy"
          />
        </div>
      </a>

      <div class="nasaTitle">{{ nasaItem.title }}</div>
      <div v-if="nasaItem.excerpt" class="nasaExcerpt">{{ nasaItem.excerpt }}</div>

      <div class="panelActions">
        <a
          class="ghostbtn"
          :href="nasaItem.link"
          target="_blank"
          rel="noopener noreferrer"
        >
          Zobraziť na NASA.gov
        </a>
      </div>
    </div>
  </section>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

export default {
  name: 'NasaApodWidget',
  props: {
    title: {
      type: String,
      default: 'NASA – Obrázok dňa'
    }
  },
  setup() {
    const nasaItem = ref(null)
    const loading = ref(false)
    const error = ref(null)

    const nasaEnabled = computed(() => {
      return (
        import.meta.env.VITE_FEATURE_NASA_IOTD !== 'false' &&
        import.meta.env.VITE_FEATURE_NASA_IOTD !== '0'
      )
    })

    const fetchNasaIotd = async () => {
      loading.value = true
      error.value = null
      nasaItem.value = null

      try {
        const res = await api.get('/nasa/iotd')
        const payload = res?.data

        if (payload && payload.available) {
          nasaItem.value = payload
        } else {
          nasaItem.value = null
        }
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať NASA Image of the Day.'
        nasaItem.value = null
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      if (nasaEnabled.value) {
        fetchNasaIotd()
      }
    })

    return {
      nasaItem,
      loading,
      error,
      nasaEnabled,
      fetchNasaIotd
    }
  }
}
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

.panelLoading {
  display: grid;
  gap: 0.5rem;
}

.nasaCard {
  display: grid;
  gap: 0.6rem;
}

.nasaThumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 1rem;
}

.nasaImageWrap {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 1rem;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.nasaImageWrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.nasaTitle {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.25;
}

.nasaExcerpt {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  display: -webkit-box;
  line-clamp: 3;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.panelActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.ghostbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}

.ghostbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.stateText {
  color: var(--color-text-secondary);
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0.75rem;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-4 { height: 1rem; }
.w-4\/5 { width: 80%; }
.w-2\/3 { width: 66.666667%; }
</style>
