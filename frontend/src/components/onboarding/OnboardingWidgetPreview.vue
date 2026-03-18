<template>
  <div class="widgetPreview" :class="`is-${size}`" aria-hidden="true">
    <div
      v-for="(widget, index) in previewWidgets"
      :key="index"
      class="widgetSlide"
      :style="{ '--widget-index': index }"
    >
      <component :is="widget" />
    </div>
  </div>
</template>

<script setup>
import NextEclipseWidget from '@/components/widgets/NextEclipseWidget.vue'
import NextMeteorWidget from '@/components/widgets/NextMeteorWidget.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import NasaHighlightsWidget from '@/components/widgets/NasaHighlightsWidget.vue'

defineProps({
  size: {
    type: String,
    default: 'default',
  },
})

const previewWidgets = [
  NextEclipseWidget,
  NextMeteorWidget,
  LatestArticlesWidget,
  NasaHighlightsWidget,
]
</script>

<style scoped>
.widgetPreview {
  position: relative;
  border-radius: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background:
    radial-gradient(140% 100% at 0% 0%, rgb(var(--color-primary-rgb) / 0.1), transparent 60%),
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.96), rgb(var(--color-bg-rgb) / 0.88));
  overflow: hidden;
  pointer-events: none;
  user-select: none;
}

.widgetPreview.is-default {
  min-height: 14rem;
}

.widgetPreview.is-compact {
  min-height: 10.5rem;
}

.widgetSlide {
  position: absolute;
  inset: 0.75rem;
  overflow: hidden;
  opacity: 0;
  transform: translateY(8px) scale(0.987);
  animation: widgetPreviewCycle 12s ease-in-out infinite;
  animation-delay: calc(var(--widget-index) * -3s);
}

@keyframes widgetPreviewCycle {
  0%,
  22% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  28%,
  100% {
    opacity: 0;
    transform: translateY(8px) scale(0.987);
  }
}

@media (prefers-reduced-motion: reduce) {
  .widgetSlide {
    animation: none;
    opacity: 0;
    transform: none;
  }

  .widgetSlide:first-child {
    opacity: 1;
  }
}
</style>
