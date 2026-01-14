<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <Navbar />

    <main class="max-w-5xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>

<script>
import Navbar from './components/MainNavbar.vue'

export default {
  name: 'App',
  components: { Navbar },
}
</script>
