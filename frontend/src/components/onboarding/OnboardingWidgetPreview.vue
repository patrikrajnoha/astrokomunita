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
  --preview-bg: #151d28;
  --preview-hover: #1c2736;
  --preview-primary: #0f73ff;
  --preview-muted: #abb8c9;

  position: relative;
  border-radius: 1rem;
  border: 1px solid rgb(171 184 201 / 0.22);
  background:
    radial-gradient(140% 100% at 0% 0%, rgb(15 115 255 / 0.12), transparent 62%),
    linear-gradient(180deg, var(--preview-hover), var(--preview-bg));
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
  transform: translateY(5px) scale(0.994);
  animation: widgetPreviewCycle 16s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  animation-delay: calc(var(--widget-index) * -4s);
}

@keyframes widgetPreviewCycle {
  0%,
  18% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  24%,
  100% {
    opacity: 0;
    transform: translateY(5px) scale(0.994);
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
