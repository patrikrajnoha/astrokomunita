<template>
  <nav class="flex h-full flex-col gap-4" aria-label="Primary navigation">
    <RouterLink
      to="/"
      class="inline-flex items-center gap-3 rounded-2xl px-3 py-2 text-base font-semibold text-slate-100 transition hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
      title="Home"
      aria-label="Home"
    >
      <span
        class="grid h-10 w-10 place-items-center rounded-2xl border border-slate-700 bg-gradient-to-br from-sky-400/30 via-slate-900/40 to-indigo-400/20 text-base shadow-lg"
        aria-hidden="true"
      >
        🌌
      </span>
      <span>Astrokomunita</span>
    </RouterLink>

    <div class="flex flex-col gap-1">
      <RouterLink
        v-for="item in primaryLinks"
        :key="item.to"
        :to="item.to"
        custom
        v-slot="{ href, navigate, isActive }"
      >
        <div v-if="item.isMore" ref="moreWrapperRef" class="relative">
          <button
            type="button"
            class="group flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
            :class="isMoreActive
              ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
              : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100'"
            :aria-expanded="isMoreOpen ? 'true' : 'false'"
            aria-controls="more-menu"
            aria-label="More"
            title="More"
            @click="toggleMore"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-[0.7rem] font-semibold uppercase text-slate-300 group-hover:text-slate-100"
              aria-hidden="true"
            >
              {{ item.icon }}
            </span>
            <span class="flex-1">{{ item.label }}</span>
            <span class="text-xs text-slate-400" aria-hidden="true">▾</span>
          </button>

          <div
            v-if="isMoreOpen"
            id="more-menu"
            class="absolute left-0 top-full z-50 mt-2 w-56 rounded-2xl border border-slate-800 bg-slate-950/95 p-2 shadow-xl"
            role="menu"
            aria-label="More options"
          >
            <RouterLink
              to="/settings"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
                :class="isMoreItemActive
                  ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
                  : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100'"
                role="menuitem"
                aria-label="Settings"
              >
                <span
                  class="grid h-8 w-8 place-items-center rounded-lg border border-slate-800 bg-slate-900/60 text-[0.7rem] font-semibold uppercase text-slate-300"
                  aria-hidden="true"
                >
                  S
                </span>
                <span class="flex-1">Settings</span>
              </a>
            </RouterLink>

            <RouterLink
              to="/creator-studio"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
                :class="isMoreItemActive
                  ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
                  : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100'"
                role="menuitem"
                aria-label="Creator Studio"
              >
                <span
                  class="grid h-8 w-8 place-items-center rounded-lg border border-slate-800 bg-slate-900/60 text-[0.7rem] font-semibold uppercase text-slate-300"
                  aria-hidden="true"
                >
                  C
                </span>
                <span class="flex-1">Creator Studio</span>
              </a>
            </RouterLink>
          </div>
        </div>
        <a
          v-else
          :href="href"
          @click="navigate"
          :title="item.title || item.label"
          :aria-label="item.label"
          :class="[
            'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400',
            isActive
              ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
              : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100',
          ]"
        >
          <span
            class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-[0.7rem] font-semibold uppercase text-slate-300 group-hover:text-slate-100"
            aria-hidden="true"
          >
            {{ item.icon }}
          </span>
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge"
            class="rounded-full border border-sky-400/40 bg-sky-400/15 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-100"
          >
            {{ item.badge }}
          </span>
        </a>
      </RouterLink>
    </div>

    <div class="mt-auto border-t border-slate-900/80 pt-4">
      <template v-if="auth.user">
        <RouterLink
          to="/profile"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Profile"
            aria-label="Profile"
            :class="[
              'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400',
              isActive
                ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
                : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100',
            ]"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-sm text-slate-300 group-hover:text-slate-100"
              aria-hidden="true"
            >
              👤
            </span>
            <span class="flex-1">{{ auth.user.name }}</span>
          </a>
        </RouterLink>

        <button
          class="mt-2 flex w-full items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-sky-400/40 hover:bg-slate-900 hover:text-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
          title="Log out"
          aria-label="Log out"
          @click="logout"
        >
          <span
            class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-sm text-slate-300"
            aria-hidden="true"
          >
            ⎋
          </span>
          <span class="flex-1">Logout</span>
        </button>
      </template>

      <template v-else>
        <RouterLink
          to="/login"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Log in"
            aria-label="Log in"
            :class="[
              'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400',
              isActive
                ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
                : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100',
            ]"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-sm text-slate-300 group-hover:text-slate-100"
              aria-hidden="true"
            >
              🔐
            </span>
            <span class="flex-1">Login</span>
          </a>
        </RouterLink>

        <RouterLink
          to="/register"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Register"
            aria-label="Register"
            :class="[
              'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400',
              isActive
                ? 'bg-sky-500/20 text-slate-50 ring-1 ring-sky-400/40'
                : 'text-slate-300 hover:bg-slate-900 hover:text-slate-100',
            ]"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-slate-800 bg-slate-900/60 text-sm text-slate-300 group-hover:text-slate-100"
              aria-hidden="true"
            >
              ✨
            </span>
            <span class="flex-1">Register</span>
          </a>
        </RouterLink>
      </template>
    </div>
  </nav>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)

const isMoreActive = computed(() => {
  return route.path === '/settings' || route.path === '/creator-studio'
})

const primaryLinks = computed(() => {
  const links = [
    { to: '/events', label: 'Events', icon: 'U' },
    { to: '/calendar', label: 'Calendar', icon: 'K' },
    { to: '/learn', label: 'Learning', icon: 'V' },
  ]

  if (auth.user) {
    links.push({ to: '/observations', label: 'Observations', icon: 'P' })
  }

  if (auth.user?.is_admin) {
    links.push({
      to: '/admin/candidates',
      label: '🛠 Admin',
      title: 'Event candidate approvals',
      badge: 'Approvals',
      icon: 'A',
    })
    links.push({
      to: '/admin/blog-posts',
      label: '📰 Articles',
      title: 'Blog post management',
      badge: 'Admin',
      icon: 'C',
    })
  }

  links.push({ to: '/more', label: 'More', icon: 'M', isMore: true })

  return links
})

const toggleMore = () => {
  isMoreOpen.value = !isMoreOpen.value
}

const closeMore = () => {
  isMoreOpen.value = false
}

const handleClickOutside = (event) => {
  if (!isMoreOpen.value) {
    return
  }

  const target = event.target
  const wrapper = moreWrapperRef.value

  if (wrapper && target instanceof Node && !wrapper.contains(target)) {
    closeMore()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && isMoreOpen.value) {
    closeMore()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('keydown', handleKeydown)
})

const logout = async () => {
  try {
    await auth.logout()
  } finally {
    router.push({ name: 'login' })
  }
}
</script>
