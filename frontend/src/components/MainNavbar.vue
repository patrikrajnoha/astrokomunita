<template>
  <header class="sticky top-0 z-50 bg-slate-950/80 backdrop-blur border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
      <!-- Brand -->
      <router-link
        to="/"
        class="brand"
        title="Domov"
      >
        <span class="text-lg">🌌</span>
        <span>Astrokomunita</span>
      </router-link>

      <!-- Nav -->
      <nav class="flex items-center gap-2 text-sm">
        <!-- Main links -->
        <router-link class="navlink" to="/events">Udalosti</router-link>
        <router-link class="navlink" to="/calendar">Kalendár</router-link>
        <router-link class="navlink" to="/learn">Vzdelávanie</router-link>

        <!-- Auth-only links -->
        <router-link v-if="auth.user" class="navlink" to="/favorites">
          Obľúbené ⭐
        </router-link>
        <router-link v-if="auth.user" class="navlink" to="/observations">
          Pozorovania
        </router-link>

        <!-- Admin-only -->
        <router-link
          v-if="auth.user?.is_admin"
          class="navlink adminlink"
          to="/admin/candidates"
          title="Schvaľovanie event kandidátov"
        >
          🛠 Admin
          <span class="pill">Schvaľovanie</span>
        </router-link>
        <router-link
          v-if="auth.user?.is_admin"
          class="navlink adminlink"
          to="/admin/blog-posts"
          title="Správa blogových článkov"
        >
          📝 Články
          <span class="pill">Admin</span>
        </router-link>

        <!-- Right area -->
        <div class="flex items-center gap-2 ml-2 pl-2 border-l border-slate-800">
          <!-- Logged in -->
          <template v-if="auth.user">
            <router-link class="iconbtn" to="/profile" title="Profil">
              👤 <span class="hidden sm:inline">{{ auth.user.name }}</span>
              <span class="sm:hidden">Profil</span>
            </router-link>

            <button class="iconbtn" title="Odhlásiť" @click="logout">
              ⎋ <span class="hidden sm:inline">Logout</span>
            </button>
          </template>

          <!-- Guest -->
          <template v-else>
            <router-link class="iconbtn" to="/login" title="Prihlásiť">
              🔐 <span class="hidden sm:inline">Login</span>
            </router-link>

            <router-link class="iconbtn" to="/register" title="Registrácia">
              ✨ <span class="hidden sm:inline">Register</span>
            </router-link>
          </template>
        </div>
      </nav>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const logout = async () => {
  try {
    await auth.logout()
  } finally {
    router.push({ name: 'login' })
  }
}
</script>

<style scoped>
.brand {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: rgb(199 210 254);
  text-decoration: none;
}
.brand:hover {
  color: rgb(224 231 255);
}

.navlink {
  padding: 0.4rem 0.65rem;
  border-radius: 0.85rem;
  color: rgb(203 213 225);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
}
.navlink:hover {
  color: white;
  background: rgba(30, 41, 59, 0.6);
}

/* Active route highlight */
.navlink.router-link-active {
  color: white;
  background: rgba(99, 102, 241, 0.14);
  border: 1px solid rgba(99, 102, 241, 0.25);
}

/* Admin link styling */
.adminlink {
  border: 1px solid rgba(99, 102, 241, 0.25);
  background: rgba(99, 102, 241, 0.08);
}
.adminlink:hover {
  border-color: rgba(99, 102, 241, 0.45);
}

.pill {
  font-size: 0.72rem;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.18);
  border: 1px solid rgba(99, 102, 241, 0.35);
  color: rgb(199 210 254);
}

/* Right-side buttons */
.iconbtn {
  padding: 0.38rem 0.6rem;
  border-radius: 0.85rem;
  background: rgba(30, 41, 59, 0.6);
  color: rgb(203 213 225);
  border: 1px solid rgba(51, 65, 85, 0.9);
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  line-height: 1;
  transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
}
.iconbtn:hover {
  background: rgba(51, 65, 85, 0.85);
  color: white;
  border-color: rgba(99, 102, 241, 0.35);
}
</style>
