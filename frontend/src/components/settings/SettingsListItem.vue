<template>
  <li class="settings-nav-item">
    <button
      type="button"
      class="settings-nav-button"
      :class="{ 'is-active': isActive }"
      :aria-label="ariaLabel || title"
      @click="navigate"
    >
      <span class="settings-nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path v-for="(path, index) in iconPaths" :key="`icon-path-${index}`" :d="path" />
        </svg>
      </span>

      <span class="settings-nav-meta">
        <span class="settings-nav-title">{{ title }}</span>
        <span v-if="description" class="settings-nav-description">{{ description }}</span>
      </span>

      <span class="settings-nav-chevron" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 6 6 6-6 6" />
        </svg>
      </span>
    </button>
  </li>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const props = defineProps({
  ariaLabel: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  iconPaths: {
    type: Array,
    default: () => [],
  },
  title: {
    type: String,
    required: true,
  },
  to: {
    type: Object,
    required: true,
  },
})

const route = useRoute()
const router = useRouter()

const isActive = computed(() => {
  const targetName = props.to?.name
  if (typeof targetName !== 'string') return false
  return route.name === targetName
})

const navigate = async () => {
  if (isActive.value) return
  await router.push(props.to)
}
</script>
