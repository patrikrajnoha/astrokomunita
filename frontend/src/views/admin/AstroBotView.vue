<template>
  <div class="adminAstrobot">
    <header class="adminHeader">
      <div>
        <h1 class="adminTitle">AstroBot Admin</h1>
        <p class="adminSubtitle">RSS pipeline - NASA news automation</p>
      </div>

      <button
        class="refreshButton"
        type="button"
        :disabled="refreshing"
        @click="refreshRss"
      >
        <LoadingIndicator v-if="refreshing" size="sm" :text="''" :full-width="false" />
        <span>{{ refreshing ? 'Aktualizujem...' : 'Aktualizovat RSS' }}</span>
      </button>
    </header>

    <div
      v-if="toast.message"
      class="toast"
      :class="toast.type === 'error' ? 'toastError' : 'toastSuccess'"
    >
      {{ toast.message }}
    </div>

    <div class="adminTabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="adminTab"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <main class="adminMain">
      <TodayTab v-if="activeTab === 'today'" :key="`today-${refreshNonce}`" />
      <PublishedTab v-if="activeTab === 'published'" :key="`published-${refreshNonce}`" />
      <SettingsTab v-if="activeTab === 'settings'" />
    </main>
  </div>
</template>

<script>
import TodayTab from '@/components/admin/astrobot/TodayTab.vue'
import PublishedTab from '@/components/admin/astrobot/PublishedTab.vue'
import SettingsTab from '@/components/admin/astrobot/SettingsTab.vue'
import LoadingIndicator from '@/components/shared/LoadingIndicator.vue'
import api from '@/services/api'

export default {
  name: 'AstroBotView',
  components: { TodayTab, PublishedTab, SettingsTab, LoadingIndicator },
  data() {
    return {
      activeTab: 'today',
      refreshing: false,
      refreshNonce: 0,
      toast: {
        message: '',
        type: 'success',
      },
      tabs: [
        { key: 'today', label: 'Today' },
        { key: 'published', label: 'Published' },
        { key: 'settings', label: 'Settings' },
      ],
    }
  },
  methods: {
    async refreshRss() {
      if (this.refreshing) return
      this.refreshing = true
      this.toast.message = ''

      try {
        const res = await api.post('/admin/astrobot/rss/refresh')
        const result = res?.data?.result || {}
        this.toast = {
          type: 'success',
          message: `RSS aktualizovane. Nove: ${result.created ?? 0}, preskocene: ${result.skipped ?? 0}.`,
        }
        this.refreshNonce += 1
      } catch (e) {
        this.toast = {
          type: 'error',
          message: e?.response?.data?.message || 'Nepodarilo sa aktualizovat RSS feed.',
        }
      } finally {
        this.refreshing = false
        window.setTimeout(() => {
          this.toast.message = ''
        }, 5000)
      }
    },
  },
}
</script>

<style scoped>
.adminAstrobot {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.adminHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  gap: 1rem;
}

.adminTitle {
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-surface);
  margin-bottom: 0.5rem;
}

.adminSubtitle {
  color: var(--color-text-secondary);
  font-size: 1rem;
  margin: 0;
}

.refreshButton {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.45);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.12);
  border-radius: 0.75rem;
  padding: 0.6rem 1rem;
  font-weight: 600;
  cursor: pointer;
}

.refreshButton:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.2);
}

.refreshButton:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.toast {
  margin-bottom: 1rem;
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  font-weight: 600;
}

.toastSuccess {
  border: 1px solid rgb(var(--color-success-rgb) / 0.4);
  background: rgb(var(--color-success-rgb) / 0.12);
  color: var(--color-success);
}

.toastError {
  border: 1px solid rgb(var(--color-danger-rgb) / 0.4);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
}

.adminTabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.adminTab {
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--color-text-secondary);
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s ease-out;
}

.adminTab:hover {
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.3);
}

.adminTab.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.adminMain {
  min-height: 400px;
}

@media (max-width: 760px) {
  .adminHeader {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
