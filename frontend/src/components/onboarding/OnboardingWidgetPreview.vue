<template>
  <div class="widgetPreview" :class="`is-${size}`" aria-hidden="true">
    <article
      v-for="(widget, index) in widgetPreviewItems"
      :key="widget.title"
      class="widgetPreviewItem"
      :style="{
        '--widget-index': index,
        '--widget-accent': widget.accent,
      }"
    >
      <div class="widgetPreviewHead">
        <p class="widgetPreviewEyebrow">{{ widget.eyebrow }}</p>
        <span class="widgetPreviewBadge">{{ widget.badge }}</span>
      </div>
      <h3 class="widgetPreviewTitle">{{ widget.title }}</h3>
      <p class="widgetPreviewMeta">{{ widget.meta }}</p>
      <div class="widgetPreviewStats">
        <span
          v-for="stat in widget.stats"
          :key="stat"
          class="widgetPreviewStat"
        >
          {{ stat }}
        </span>
      </div>
      <p class="widgetPreviewFoot">{{ widget.foot }}</p>
    </article>
  </div>
</template>

<script setup>
defineProps({
  size: {
    type: String,
    default: 'default',
  },
})

const widgetPreviewItems = [
  {
    eyebrow: 'Obloha dnes',
    badge: 'Live',
    title: 'Pocasie a seeing',
    meta: 'Bratislava / 22:15',
    stats: ['Oblacnost 12 %', 'Seeing 4/5', 'Vietor 6 km/h'],
    foot: 'Rychly check pred vecernym pozorovanim.',
    accent: '125 211 252',
  },
  {
    eyebrow: 'Mesiac',
    badge: 'Dnes',
    title: 'Faza a udalosti',
    meta: 'Dorastajuci Mesiac',
    stats: ['Osvetlenie 63 %', 'Zapad 01:14', 'Vrchol 21:48'],
    foot: 'Mas prehlad, ci bude Mesiac rusit tmavu oblohu.',
    accent: '251 191 36',
  },
  {
    eyebrow: 'Co sledovat',
    badge: 'Tip',
    title: 'Dalsie ukazy',
    meta: 'Meteoricky roj / ISS / Start rakety',
    stats: ['Max o 2 dni', 'ISS o 19:42', 'Start o 21:10'],
    foot: 'Widgety sa v paneli skladaju podla toho, co ta zaujima.',
    accent: '134 239 172',
  },
]
</script>

<style scoped>
.widgetPreview {
  position: relative;
  border-radius: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background:
    radial-gradient(140% 100% at 0% 0%, rgb(var(--color-primary-rgb) / 0.16), transparent 60%),
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.96), rgb(var(--color-bg-rgb) / 0.88));
  overflow: hidden;
}

.widgetPreview.is-default {
  min-height: 14rem;
}

.widgetPreview.is-compact {
  min-height: 10.5rem;
}

.widgetPreview::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
    linear-gradient(transparent, rgb(var(--color-bg-rgb) / 0.1)),
    repeating-linear-gradient(
      180deg,
      transparent,
      transparent 27px,
      rgb(var(--color-text-secondary-rgb) / 0.05) 27px,
      rgb(var(--color-text-secondary-rgb) / 0.05) 28px
    );
  pointer-events: none;
}

.widgetPreviewItem {
  position: absolute;
  inset: 0.7rem;
  display: grid;
  gap: 0.45rem;
  align-content: start;
  border-radius: 0.82rem;
  padding: 0.82rem;
  background:
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.98), rgb(var(--color-bg-rgb) / 0.9));
  border: 1px solid rgb(var(--widget-accent) / 0.32);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.04),
    0 10px 20px rgb(2 6 23 / 0.22);
  opacity: 0;
  transform: translateY(10px) scale(0.985);
  animation: widgetPreviewCycle 9s ease-in-out infinite;
  animation-delay: calc(var(--widget-index) * -3s);
}

.widgetPreview.is-default .widgetPreviewItem {
  inset: 0.8rem;
  padding: 0.95rem;
}

.widgetPreviewHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
}

.widgetPreviewEyebrow,
.widgetPreviewMeta,
.widgetPreviewFoot {
  margin: 0;
}

.widgetPreviewEyebrow {
  font-size: 0.68rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(var(--widget-accent) / 0.95);
  font-weight: 700;
}

.widgetPreviewBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: rgb(var(--widget-accent) / 0.16);
  color: rgb(var(--widget-accent) / 0.98);
  padding: 0.18rem 0.45rem;
  font-size: 0.68rem;
  font-weight: 700;
}

.widgetPreviewTitle {
  margin: 0;
  font-size: 1rem;
  line-height: 1.15;
}

.widgetPreviewMeta {
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.widgetPreviewStats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.widgetPreviewStat {
  display: inline-flex;
  align-items: center;
  min-height: 1.55rem;
  border-radius: 999px;
  padding: 0.2rem 0.55rem;
  background: rgb(var(--widget-accent) / 0.12);
  border: 1px solid rgb(var(--widget-accent) / 0.2);
  color: rgb(var(--color-surface-rgb) / 0.94);
  font-size: 0.72rem;
  font-weight: 600;
}

.widgetPreviewFoot {
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

@keyframes widgetPreviewCycle {
  0%,
  24% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  30%,
  100% {
    opacity: 0;
    transform: translateY(10px) scale(0.985);
  }
}

@media (prefers-reduced-motion: reduce) {
  .widgetPreviewItem {
    animation: none;
    opacity: 0;
    transform: none;
  }

  .widgetPreviewItem:first-child {
    opacity: 1;
  }
}
</style>
