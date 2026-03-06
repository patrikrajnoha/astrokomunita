<template>
  <section v-if="nasaEnabled" class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

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
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: hidden;
}

.panel {
  display: grid;
  gap: var(--sb-gap-sm, 0.5rem);
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

.panelLoading {
  display: grid;
  gap: var(--sb-gap-xs, 0.3rem);
}

.nasaCard {
  display: grid;
  gap: var(--sb-gap-sm, 0.5rem);
  min-width: 0;
}

.nasaThumb {
  width: 100%;
  aspect-ratio: 16 / 7.8;
  border-radius: 0.68rem;
}

.nasaImageWrap {
  width: 100%;
  aspect-ratio: 16 / 7.8;
  max-height: 142px;
  border-radius: 0.68rem;
  overflow: hidden;
  border: 1px solid var(--divider-color);
}

.nasaImageWrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.nasaTitle {
  font-size: 0.9rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.2;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.nasaExcerpt {
  color: var(--color-text-secondary);
  font-size: 0.78rem;
  line-height: 1.3;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.panelActions {
  display: flex;
  gap: var(--sb-gap-xs, 0.3rem);
  flex-wrap: wrap;
  padding-top: 0.08rem;
}

.ghostbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: auto;
  min-height: 1.95rem;
  padding: 0.36rem 0.64rem;
  border-radius: 0.66rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  font-size: 0.76rem;
  line-height: 1.15;
}

.ghostbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.stateText {
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  line-height: 1.32;
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

@media (max-width: 420px) {
  .nasaImageWrap {
    max-height: 136px;
  }

  .nasaExcerpt {
    -webkit-line-clamp: 2;
    line-clamp: 2;
  }
}
</style>
