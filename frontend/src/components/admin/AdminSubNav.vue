<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const groups = [
  {
    title: 'Core management',
    items: [
      { label: 'Dashboard', to: '/admin/dashboard', icon: 'D' },
      { label: 'Users', to: '/admin/users', icon: 'U' },
      { label: 'Candidates', to: '/admin/event-candidates', icon: 'C' },
      { label: 'Reports', to: '/admin/reports', icon: 'R' },
      { label: 'Moderation', to: '/admin/moderation', icon: 'M' },
      { label: 'Banned words', to: '/admin/banned-words', icon: 'W' },
    ],
  },
  {
    title: 'Content & configuration',
    items: [
      { label: 'Events', to: '/admin/events', icon: 'E' },
      { label: 'Articles', to: '/admin/blog', icon: 'B' },
      { label: 'Sidebar', to: '/admin/sidebar', icon: 'S' },
      { label: 'AstroBot', to: '/admin/astrobot', icon: 'A' },
    ],
  },
]

function isActive(path) {
  if (path === '/admin/users') return route.path === path || route.path.startsWith('/admin/users/')
  if (path === '/admin/event-candidates') {
    return route.path === path || route.path.startsWith('/admin/candidates/')
  }
  if (path === '/admin/events') {
    return route.path === path || route.path.startsWith('/admin/events/')
  }
  return route.path === path
}

const activeLabel = computed(() => {
  for (const group of groups) {
    const found = group.items.find((item) => isActive(item.to))
    if (found) return found.label
  }
  return 'Admin'
})
</script>

<template>
  <aside class="adminSubNav" aria-label="Admin sub-navigation">
    <div class="adminSubNav__head">
      <div class="adminSubNav__title">Admin Hub</div>
      <div class="adminSubNav__caption">Section: {{ activeLabel }}</div>
    </div>

    <div class="adminSubNav__groups">
      <section v-for="group in groups" :key="group.title" class="adminSubNav__group">
        <div class="adminSubNav__groupTitle">{{ group.title }}</div>
        <nav class="adminSubNav__list" :aria-label="group.title">
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="adminSubNav__item"
            :class="{ active: isActive(item.to) }"
            :title="`Open ${item.label}`"
          >
            <span class="adminSubNav__icon" aria-hidden="true">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </RouterLink>
        </nav>
      </section>
    </div>
  </aside>
</template>

<style scoped>
.adminSubNav {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 14px;
  padding: 14px;
  background:
    linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.92), rgb(var(--color-bg-rgb) / 0.74)),
    rgb(var(--color-bg-rgb));
  box-shadow: 0 14px 30px rgb(0 0 0 / 0.2);
  backdrop-filter: blur(8px);
}

.adminSubNav__head {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding-bottom: 12px;
}

.adminSubNav__title {
  font-size: 0.98rem;
  font-weight: 800;
  letter-spacing: 0.01em;
}

.adminSubNav__caption {
  margin-top: 4px;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.adminSubNav__list {
  display: grid;
  gap: 9px;
}

.adminSubNav__groups {
  display: grid;
  gap: 16px;
  margin-top: 12px;
}

.adminSubNav__group {
  display: grid;
  gap: 9px;
}

.adminSubNav__groupTitle {
  font-size: 0.76rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgb(var(--color-text-secondary-rgb) / 0.75);
  padding: 0 2px;
}

.adminSubNav__item {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 11px;
  padding: 9px 11px;
  color: rgb(var(--color-surface-rgb) / 0.94);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
}

.adminSubNav__icon {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 0.65rem;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  background: rgb(var(--color-bg-rgb) / 0.35);
  display: grid;
  place-items: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  flex-shrink: 0;
}

.adminSubNav__item:hover {
  background: rgb(var(--color-surface-rgb) / 0.1);
  border-color: rgb(var(--color-surface-rgb) / 0.24);
  transform: translateY(-1px);
}

.adminSubNav__item.active {
  background: linear-gradient(
    130deg,
    rgb(var(--color-primary-rgb) / 0.24),
    rgb(var(--color-surface-rgb) / 0.12)
  );
  border-color: rgb(var(--color-primary-rgb) / 0.45);
  color: rgb(var(--color-surface-rgb) / 1);
  box-shadow: inset 0 1px 0 rgb(var(--color-surface-rgb) / 0.18);
}

.adminSubNav__item.active .adminSubNav__icon {
  border-color: rgb(var(--color-primary-rgb) / 0.42);
  color: rgb(var(--color-surface-rgb) / 1);
  background: rgb(var(--color-primary-rgb) / 0.2);
}

@media (max-width: 900px) {
  .adminSubNav {
    border-radius: 12px;
    padding: 10px;
  }

  .adminSubNav__head {
    border-bottom: none;
    padding-bottom: 2px;
  }

  .adminSubNav__groups {
    margin-top: 8px;
    gap: 10px;
  }

  .adminSubNav__group {
    gap: 6px;
  }

  .adminSubNav__groupTitle {
    font-size: 0.72rem;
  }

  .adminSubNav__list {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
    padding-bottom: 4px;
  }

  .adminSubNav__item {
    white-space: nowrap;
    flex-shrink: 0;
    border-radius: 999px;
    padding: 7px 12px;
  }

  .adminSubNav__icon {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    font-size: 0.62rem;
  }
}
</style>
