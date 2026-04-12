<template>
  <transition name="popupFade" appear>
    <div class="overlay" role="presentation" @click.self="$emit('close')">
      <transition name="popupScale" appear>
        <section class="card" role="dialog" aria-modal="true" aria-labelledby="myc-title">
          <header class="head">
            <div>
              <p class="eyebrow">Top udalosti</p>
              <h2 id="myc-title" class="title">Označ si v kalendári</h2>
            </div>
            <button type="button" class="closeBtn" aria-label="Zavrieť" @click="$emit('close')">
              <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15"/>
              </svg>
            </button>
          </header>

          <div class="grid">
            <article
              v-for="(item, index) in limitedItems"
              :key="item.id"
              class="tile"
              :style="{ animationDelay: `${index * 40}ms` }"
            >
              <p class="tile__date">{{ formatDate(item.start_at, item.end_at) }}</p>
              <p class="tile__title">{{ item.title }}</p>
              <div class="tile__cals">
                <a
                  v-if="item.google_calendar_url || item.calendar?.google_calendar_url"
                  class="calBtn calBtn--g"
                  :href="item.google_calendar_url || item.calendar?.google_calendar_url"
                  target="_blank"
                  rel="noopener"
                  title="Pridať do Google Kalendára"
                >Google</a>
                <a
                  v-if="item.ics_url || item.calendar?.ics_url"
                  class="calBtn calBtn--ics"
                  :href="item.ics_url || item.calendar?.ics_url"
                  target="_blank"
                  rel="noopener"
                  title="Stiahnuť .ics súbor"
                >.ics</a>
              </div>
            </article>
          </div>

          <footer class="foot">
            <a v-if="bundleIcsUrl" class="footBtn footBtn--ghost" :href="bundleIcsUrl" target="_blank" rel="noopener">
              Stiahnuť všetky .ics
            </a>
            <button type="button" class="footBtn footBtn--ghost" @click="$emit('close')">Zavrieť</button>
            <button type="button" class="footBtn footBtn--primary" @click="$emit('go-calendar')">Prejsť do kalendára</button>
          </footer>
        </section>
      </transition>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'
import { EVENT_TIMEZONE, formatEventDate, formatEventDateKey } from '@/utils/eventTime'

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

const limitedItems = computed(() => (Array.isArray(props.items) ? props.items.slice(0, 6) : []))

function formatDate(startAt, endAt) {
  const startLabel = formatShortDate(startAt)
  const endLabel = formatShortDate(endAt)

  if (!startLabel && !endLabel) return 'Dátum upresníme'
  if (startLabel && !endLabel) return startLabel
  if (!startLabel && endLabel) return endLabel

  const sameDay = formatEventDateKey(startAt, EVENT_TIMEZONE) === formatEventDateKey(endAt, EVENT_TIMEZONE)
  return sameDay ? startLabel : `${startLabel} – ${endLabel}`
}

function formatShortDate(value) {
  if (!value) return ''

  const label = formatEventDate(value, EVENT_TIMEZONE, {
    day: 'numeric',
    month: 'short',
  })

  return label === '-' ? '' : label
}
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 95;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(6 10 16 / 0.72);
  backdrop-filter: blur(8px);
}

.card {
  width: min(100%, 660px);
  border-radius: 24px;
  border: 0;
  background: #151d28;
  box-shadow: none;
  padding: 1.25rem;
  overflow: hidden;
}

/* ── Header ── */
.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.eyebrow {
  margin: 0 0 0.22rem;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #0f73ff;
  opacity: 0.85;
}

.title {
  margin: 0;
  font-size: clamp(1.2rem, 3vw, 1.6rem);
  font-weight: 700;
  color: #ffffff;
  line-height: 1.15;
}

.closeBtn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: none;
  box-shadow: none;
  background: #222e3f;
  color: #abb8c9;
  cursor: pointer;
  flex-shrink: 0;
  transition: background-color 140ms ease, color 140ms ease;
}

.closeBtn svg {
  width: 14px;
  height: 14px;
}

.closeBtn:hover {
  background: #1c2736;
  color: #ffffff;
}

/* ── Grid ── */
.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  margin-bottom: 1rem;
}

/* ── Tile ── */
.tile {
  border: 0;
  border-radius: 16px;
  padding: 0.7rem 0.75rem 0.6rem;
  background: #1c2736;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  animation: tileIn 200ms ease both;
}

.tile__date {
  margin: 0;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #abb8c9;
}

.tile__title {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #ffffff;
  line-height: 1.3;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tile__cals {
  display: flex;
  gap: 0.3rem;
  margin-top: 0.35rem;
  flex-wrap: wrap;
}

.calBtn {
  font-size: 0.65rem;
  font-weight: 500;
  text-decoration: none;
  padding: 0.28rem 0.64rem;
  border-radius: 999px;
  border: 0;
  box-shadow: none;
  transition: background-color 140ms ease, color 140ms ease;
  white-space: nowrap;
}

.calBtn--g {
  background: #0f73ff;
  color: #ffffff;
}

.calBtn--g:hover {
  background: #0d65e6;
}

.calBtn--ics {
  background: #222e3f;
  color: #abb8c9;
}

.calBtn--ics:hover {
  background: #1c2736;
  color: #ffffff;
}

/* ── Footer ── */
.foot {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 0.45rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgb(34 46 63 / 0.85);
}

.footBtn {
  border-radius: 999px;
  padding: 0.5rem 0.9rem;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  border: 0;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  box-shadow: none;
  transition: background-color 140ms ease, color 140ms ease;
}

.footBtn--ghost {
  background: #222e3f;
  color: #abb8c9;
}

.footBtn--ghost:hover {
  background: #1c2736;
  color: #ffffff;
}

.footBtn--primary {
  background: #0f73ff;
  color: #ffffff;
}

.footBtn--primary:hover {
  background: #0d65e6;
}

/* ── Animations ── */
@keyframes tileIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.popupFade-enter-active,
.popupFade-leave-active {
  transition: opacity 160ms ease;
}

.popupFade-enter-from,
.popupFade-leave-to {
  opacity: 0;
}

.popupScale-enter-active,
.popupScale-leave-active {
  transition: transform 160ms ease, opacity 160ms ease;
}

.popupScale-enter-from,
.popupScale-leave-to {
  opacity: 0;
  transform: scale(0.96) translateY(6px);
}

/* ── Responsive ── */
@media (max-width: 520px) {
  .card {
    padding: 1rem;
    border-radius: 16px;
  }

  .grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .foot {
    flex-wrap: wrap;
    justify-content: stretch;
  }

  .footBtn {
    flex: 1;
    justify-content: center;
  }
}
</style>
