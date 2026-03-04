<template>
  <section class="adminHub">
    <div class="adminHub__bg" aria-hidden="true"></div>

    <div class="adminHub__subNav adminHub__subNav--mobile">
      <AdminSubNav />
    </div>

    <div class="adminHub__center">
      <div class="adminHub__statusWrap">
        <RouterLink
          v-if="aiLastRunAt"
          to="/admin/newsletter"
          class="adminHub__aiStatus adminHub__aiStatus--link"
        >
          <span>AI: {{ aiStatusLabel }}</span>
          <span class="adminHub__aiStatusTime">{{ aiRelativeTime }}</span>
        </RouterLink>
        <span v-else class="adminHub__aiStatus">
          <span>AI: {{ aiStatusLabel }}</span>
          <span class="adminHub__aiStatusTime">{{ aiRelativeTime }}</span>
        </span>
      </div>
      <div class="adminHub__contentCard">
        <RouterView />
      </div>
    </div>

    <div class="adminHub__subNav adminHub__subNav--desktop">
      <div class="adminHub__sticky">
        <AdminSubNav />
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import AdminSubNav from '@/components/admin/AdminSubNav.vue'
import { getAdminAiConfig } from '@/services/api/admin/ai'

const aiStatus = ref('idle')
const aiLastRunAt = ref(null)
const aiClock = ref(Date.now())
let aiClockTimer = null

function normalizeStatus(value) {
  const normalized = String(value || '').trim().toLowerCase()
  return ['idle', 'success', 'fallback', 'error'].includes(normalized) ? normalized : 'idle'
}

function parseTime(value) {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function selectLatestRun(features) {
  const candidates = [
    features?.newsletter_prime_insights?.last_run || null,
    features?.event_description_generate?.last_run || null,
  ]
    .filter((item) => item && typeof item === 'object')
    .map((item) => ({
      run: item,
      time: parseTime(item.updated_at),
    }))
    .filter((item) => item.time !== null)
    .sort((a, b) => b.time.getTime() - a.time.getTime())

  return candidates[0]?.run || null
}

const aiStatusLabel = computed(() => {
  const status = normalizeStatus(aiStatus.value)
  if (status === 'success') return 'Hotovo'
  if (status === 'fallback') return 'Fallback'
  if (status === 'error') return 'Chyba'
  return 'Pripravené'
})

const aiRelativeTime = computed(() => {
  aiClock.value

  const parsed = parseTime(aiLastRunAt.value)
  if (!parsed) return 'bez behu'

  const diffMs = Math.max(0, Date.now() - parsed.getTime())
  const diffMinutes = Math.floor(diffMs / 60000)

  if (diffMinutes <= 0) return 'práve teraz'
  if (diffMinutes < 60) return `pred ${diffMinutes} min`

  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return `pred ${diffHours} h`

  const diffDays = Math.floor(diffHours / 24)
  return `pred ${diffDays} d`
})

async function loadAiStatus() {
  try {
    const response = await getAdminAiConfig()
    const data = response?.data?.data || {}
    const latestRun = selectLatestRun(data.features || {})

    if (latestRun) {
      aiStatus.value = normalizeStatus(latestRun.status)
      aiLastRunAt.value = latestRun.updated_at || null
      return
    }

    aiStatus.value = 'idle'
    aiLastRunAt.value = null
  } catch {
    aiStatus.value = 'error'
    aiLastRunAt.value = null
  }
}

onMounted(() => {
  loadAiStatus()
  aiClockTimer = window.setInterval(() => {
    aiClock.value = Date.now()
  }, 60_000)
})

onBeforeUnmount(() => {
  if (aiClockTimer) {
    clearInterval(aiClockTimer)
    aiClockTimer = null
  }
})
</script>

<style scoped>
.adminHub {
  --admin-rail-width: clamp(14rem, 19vw, 18.5rem);
  --admin-sub-nav-width: clamp(13.5rem, 16vw, 15.5rem);
  --admin-center-max: 1200px;
  position: relative;
  display: grid;
  gap: 18px;
  isolation: isolate;
}

.adminHub__bg {
  position: absolute;
  inset: -10px -12px;
  z-index: -1;
  border-radius: 18px;
  background:
    radial-gradient(110% 80% at 0% 0%, rgb(var(--primary-rgb) / 0.12), transparent 58%),
    radial-gradient(90% 70% at 100% 10%, rgb(var(--text-secondary-rgb) / 0.08), transparent 64%);
  pointer-events: none;
}

.adminHub__center {
  min-width: 0;
  width: 100%;
  max-width: var(--admin-center-max);
  margin-inline: auto;
}

.adminHub__statusWrap {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 8px;
}

.adminHub__aiStatus {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.adminHub__aiStatus--link {
  text-decoration: none;
}

.adminHub__aiStatusTime {
  color: rgb(var(--color-text-secondary-rgb) / 0.8);
}

.adminHub__contentCard {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--bg-surface);
  box-shadow: 0 20px 38px rgb(var(--bg-app-rgb) / 0.22);
  backdrop-filter: blur(6px);
  min-width: 0;
  overflow: hidden;
}

.adminHub__subNav {
  min-width: 0;
}

.adminHub__subNav--desktop {
  display: none;
}

.adminHub__sticky {
  position: sticky;
  top: 92px;
}

@media (min-width: 901px) {
  .adminHub {
    grid-template-columns:
      minmax(0, 1fr)
      minmax(var(--admin-sub-nav-width), var(--admin-rail-width));
    align-items: start;
  }

  .adminHub__subNav--mobile {
    display: none;
  }

  .adminHub__subNav--desktop {
    display: block;
  }

  .adminHub__center {
    max-width: var(--admin-center-max);
  }
}

@media (max-width: 900px) {
  .adminHub {
    gap: 14px;
  }

  .adminHub__contentCard {
    border-radius: 12px;
  }
}

@media (max-width: 767px) {
  .adminHub {
    gap: 10px;
  }

  .adminHub__bg {
    inset: -4px -6px;
    border-radius: 14px;
  }

  .adminHub__subNav--mobile {
    position: sticky;
    top: 58px;
    z-index: 8;
  }

  .adminHub__statusWrap {
    justify-content: flex-start;
  }

  :deep(.adminPageShell) {
    padding: 14px 10px;
  }

  :deep(.adminPageShell__title) {
    font-size: 1.35rem;
  }

  :deep(.adminPageShell__subtitle) {
    font-size: 13px;
  }

  :deep(.adminToolbar) {
    padding: 10px;
    gap: 10px;
  }

  :deep(.adminToolbar__slot) {
    min-width: 0;
    width: 100%;
  }

  :deep(.adminToolbar input),
  :deep(.adminToolbar select),
  :deep(.adminToolbar button) {
    min-height: 40px;
  }

  :deep(.adminPagination) {
    display: grid;
    gap: 8px;
  }

  :deep(.adminPagination__controls) {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  :deep(.adminPagination__btn) {
    min-height: 40px;
  }

  :deep(.adminTableWrap) {
    border-radius: 10px;
  }

  :deep(.adminTable) {
    min-width: 640px;
  }

  :deep(.adminTable__head),
  :deep(.adminTable__cell) {
    padding: 10px;
  }
}
</style>
