<template>
  <header class="sticky top-0 z-50 bg-slate-950/80 backdrop-blur border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
      <!-- Logo / Brand -->
      <router-link
        to="/"
        class="font-extrabold tracking-tight text-indigo-400 hover:text-indigo-300"
      >
        🌌 Astrokomunita
      </router-link>

      <!-- Main nav -->
      <nav class="flex items-center gap-3 text-sm">
        <router-link class="navlink" to="/events">Udalosti</router-link>
        <router-link class="navlink" to="/calendar">Kalendár</router-link>
        <router-link class="navlink" to="/learn">Vzdelávanie</router-link>

        <!-- 🔐 Len pre prihláseného -->
        <router-link v-if="auth.user" class="navlink" to="/favorites">
          Obľúbené ⭐
        </router-link>

        <router-link v-if="auth.user" class="navlink" to="/observations">
          Pozorovania
        </router-link>

        <!-- Pravá časť -->
        <div class="flex items-center gap-2 ml-2">
          <!-- 👤 Prihlásený -->
          <template v-if="auth.user">
            <router-link class="iconbtn" to="/profile" title="Profil">
              👤 {{ auth.user.name }}
            </router-link>

            <button class="iconbtn" title="Odhlásiť" @click="logout">
              ⎋
            </button>
          </template>

          <!-- 👋 Neprihlásený -->
          <template v-else>
            <router-link class="iconbtn" to="/login" title="Prihlásiť">
              🔐 Login
            </router-link>

            <router-link class="iconbtn" to="/register" title="Registrácia">
              ✨ Register
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
.navlink {
  padding: 0.4rem 0.6rem;
  border-radius: 0.75rem;
  color: rgb(203 213 225);
}
.navlink:hover {
  color: white;
  background: rgba(30, 41, 59, 0.6);
}

.iconbtn {
  padding: 0.35rem 0.55rem;
  border-radius: 0.75rem;
  background: rgba(30, 41, 59, 0.6);
  color: rgb(203 213 225);
  border: none;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.iconbtn:hover {
  background: rgba(51, 65, 85, 0.8);
  color: white;
}
</style>
