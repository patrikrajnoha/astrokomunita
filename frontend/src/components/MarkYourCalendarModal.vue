<template>
  <transition name="popupFade" appear>
    <div class="popupOverlay" role="presentation" @click.self="$emit('close')">
      <transition name="popupScale" appear>
        <section class="popupCard" role="dialog" aria-modal="true" aria-labelledby="mark-calendar-title">
          <header class="popupHead">
            <p class="popupEyebrow">Top events</p>
            <h2 id="mark-calendar-title" class="popupTitle">Mark your calendar</h2>
          </header>

          <div class="eventGrid">
            <article
              v-for="(item, index) in limitedItems"
              :key="item.id"
              class="eventTile"
              :style="{ animationDelay: `${index * 35}ms` }"
            >
              <p class="eventDate">{{ formatDate(item.start_at, item.end_at) }}</p>
              <p class="eventTitle">{{ item.title }}</p>
              <div class="calendarActions">
                <a
                  v-if="item.google_calendar_url || item.calendar?.google_calendar_url"
                  class="tileLink"
                  :href="item.google_calendar_url || item.calendar?.google_calendar_url"
                  target="_blank"
                  rel="noopener"
                >
                  Pridat do Google Kalendara
                </a>
                <a
                  v-if="item.ics_url || item.calendar?.ics_url"
                  class="tileLink"
                  :href="item.ics_url || item.calendar?.ics_url"
                  target="_blank"
                  rel="noopener"
                >
                  Stiahnut .ics
                </a>
              </div>
            </article>
          </div>

          <footer class="popupActions">
            <a v-if="bundleIcsUrl" class="btnGhost" :href="bundleIcsUrl" target="_blank" rel="noopener">Stiahnut vsetky .ics</a>
            <button type="button" class="btnGhost" @click="$emit('close')">Zavriet</button>
            <button type="button" class="btnPrimary" @click="$emit('go-calendar')">Prejst do kalendara</button>
          </footer>
        </section>
      </transition>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  bundleIcsUrl: {
    type: String,
    default: '',
  },
})

defineEmits(['close', 'go-calendar'])

const limitedItems = computed(() => (Array.isArray(props.items) ? props.items.slice(0, 10) : []))

function formatDate(startAt, endAt) {
  const start = parseDate(startAt)
  const end = parseDate(endAt)

  if (!start && !end) return 'Datum upresnime'
  if (start && !end) return start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  if (!start && end) return end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })

  const startLabel = start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  const endLabel = end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  return startLabel === endLabel ? startLabel : `${startLabel} - ${endLabel}`
}

function parseDate(value) {
  if (!value) return null
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null
  return parsed
}
</script>

<style scoped>
.popupOverlay {
  position: fixed;
  inset: 0;
  z-index: 95;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(5 8 19 / 0.7);
  backdrop-filter: blur(6px);
}

.popupCard {
  width: min(100%, 980px);
  border-radius: 16px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  background:
    radial-gradient(880px 280px at 0% 0%, rgb(var(--color-primary-rgb) / 0.24), transparent 64%),
    linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.97), rgb(var(--color-bg-rgb) / 0.92));
  box-shadow: 0 28px 74px rgb(0 0 0 / 0.46);
  padding: 1.15rem;
}

.popupHead {
  margin-bottom: 0.9rem;
}

.popupEyebrow {
  margin: 0;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.popupTitle {
  margin: 0.2rem 0 0;
  font-size: clamp(1.25rem, 2.6vw, 1.75rem);
  color: rgb(var(--color-surface-rgb) / 0.98);
}

.eventGrid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.62rem;
  margin-bottom: 1rem;
}

.eventTile {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 11px;
  padding: 0.62rem;
  min-height: 82px;
  background: rgb(var(--color-bg-rgb) / 0.45);
  animation: tileIn 220ms ease both;
}

.eventDate {
  margin: 0;
  font-size: 0.72rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.eventTitle {
  margin: 0.32rem 0 0;
  color: rgb(var(--color-surface-rgb) / 1);
  font-weight: 700;
  font-size: 0.84rem;
  line-height: 1.25;
}

.calendarActions {
  display: grid;
  gap: 0.3rem;
  margin-top: 0.5rem;
}

.tileLink {
  color: rgb(var(--color-primary-rgb) / 1);
  font-size: 0.72rem;
  text-decoration: underline;
}

.popupActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.btnGhost,
.btnPrimary {
  border-radius: 10px;
  padding: 0.52rem 0.82rem;
  border: 1px solid transparent;
  font-weight: 600;
  cursor: pointer;
}

.btnGhost {
  border-color: rgb(var(--color-surface-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.52);
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.btnPrimary {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.22);
  color: rgb(var(--color-surface-rgb) / 1);
}

.popupFade-enter-active,
.popupFade-leave-active {
  transition: opacity 180ms ease;
}

.popupFade-enter-from,
.popupFade-leave-to {
  opacity: 0;
}

.popupScale-enter-active,
.popupScale-leave-active {
  transition: transform 180ms ease, opacity 180ms ease;
}

.popupScale-enter-from,
.popupScale-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

@keyframes tileIn {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 900px) {
  .popupCard {
    padding: 0.92rem;
  }

  .eventGrid {
    gap: 0.5rem;
  }

  .eventTile {
    min-height: 76px;
    padding: 0.5rem;
  }

  .eventTitle {
    font-size: 0.78rem;
  }
}
</style>
