<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import api from '@/services/api'
import { formatDateTime } from './utils'

const healthLoading = ref(false)
const moderationHealth = ref({
  status: 'checking',
  checkedAt: null,
  device: null,
  error: '',
})

let healthIntervalId = null

async function loadModerationHealth() {
  healthLoading.value = true

  try {
    const res = await api.get('/admin/moderation/health')
    moderationHealth.value = {
      status: res?.data?.status || 'running',
      checkedAt: res?.data?.checked_at || null,
      device: res?.data?.service?.device || null,
      error: '',
    }
  } catch (e) {
    moderationHealth.value = {
      status: 'down',
      checkedAt: e?.response?.data?.checked_at || null,
      device: null,
      error: e?.response?.data?.error?.message || 'Moderacna sluzba nie je dostupná.',
    }
  } finally {
    healthLoading.value = false
  }
}

onMounted(() => {
  loadModerationHealth()
  healthIntervalId = window.setInterval(loadModerationHealth, 45000)
})

onBeforeUnmount(() => {
  if (healthIntervalId) {
    window.clearInterval(healthIntervalId)
    healthIntervalId = null
  }
})
</script>

<template>
  <section class="servicePanel">
    <div class="healthBar">
      <div class="healthState">
        <span class="dot" :class="`is-${moderationHealth.status}`" />
        <strong>
          Sluzba:
          {{
            moderationHealth.status === 'running'
              ? 'running'
              : moderationHealth.status === 'checking'
                ? 'checking...'
                : 'down'
          }}
        </strong>
        <span v-if="moderationHealth.device" class="meta">({{ moderationHealth.device }})</span>
      </div>
      <div class="healthMeta">
        <span v-if="moderationHealth.checkedAt" class="meta">
          Posledna kontrola: {{ formatDateTime(moderationHealth.checkedAt) }}
        </span>
        <button class="tab" type="button" :disabled="healthLoading" @click="loadModerationHealth">
          {{ healthLoading ? 'Kontrolujem...' : 'Obnovit stav' }}
        </button>
      </div>
    </div>

    <div v-if="moderationHealth.error" class="warn">{{ moderationHealth.error }}</div>
  </section>
</template>

<style scoped>
.servicePanel {
  display: grid;
  gap: 12px;
}

.healthBar {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.healthState,
.healthMeta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  display: inline-block;
  background: rgb(var(--color-surface-rgb) / 0.5);
}

.dot.is-running {
  background: rgb(34 197 94);
}

.dot.is-checking {
  background: rgb(234 179 8);
}

.dot.is-down {
  background: rgb(var(--color-danger-rgb, 239 68 68));
}

.warn {
  border: 1px solid rgb(234 179 8 / 0.45);
  border-radius: 10px;
  padding: 10px;
}

.meta {
  font-size: 13px;
  opacity: 0.82;
}

.tab {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 6px 12px;
  background: transparent;
  color: inherit;
}
</style>
