<template>
  <div class="adminAstrobot">
    <header class="adminHeader">
      <h1 class="adminTitle">AstroBot Admin</h1>
      <p class="adminSubtitle">RSS pipeline – NASA news automation</p>
    </header>

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
      <TodayTab v-if="activeTab === 'today'" />
      <PublishedTab v-if="activeTab === 'published'" />
      <SettingsTab v-if="activeTab === 'settings'" />
    </main>
  </div>
</template>

<script>
import TodayTab from '@/components/admin/astrobot/TodayTab.vue'
import PublishedTab from '@/components/admin/astrobot/PublishedTab.vue'
import SettingsTab from '@/components/admin/astrobot/SettingsTab.vue'

export default {
  name: 'AstroBotView',
  components: { TodayTab, PublishedTab, SettingsTab },
  data() {
    return {
      activeTab: 'today',
      tabs: [
        { key: 'today', label: 'Today' },
        { key: 'published', label: 'Published' },
        { key: 'settings', label: 'Settings' },
      ],
    }
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
  margin-bottom: 2rem;
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
</style>
