<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 text-white">
    <div class="w-full max-w-xl p-6 rounded-2xl bg-slate-800/60 border border-slate-700">
      <h1 class="text-4xl font-bold text-indigo-400">Astrokomunita 🚀</h1>

      <p class="mt-4 text-slate-300">Backend health check:</p>

      <pre class="mt-3 p-3 rounded-lg bg-slate-950/60 border border-slate-700 text-sm overflow-auto">
{{ health }}
      </pre>

      <button
        class="mt-4 px-4 py-2 rounded-lg bg-indigo-500 hover:bg-indigo-600 transition"
        @click="loadHealth"
      >
        Načítať /api/health
      </button>
    </div>
  </div>
</template>

<script>
import { api } from "./services/api";

export default {
  name: "App",
  data() {
    return {
      health: "Načítavam...",
    };
  },
  methods: {
    async loadHealth() {
      try {
        const res = await api.get("/api/health");
        this.health = JSON.stringify(res.data, null, 2);
      } catch (err) {
        this.health =
          "Chyba: " +
          (err?.response?.data?.message || err?.message || String(err));
      }
    },
  },
  mounted() {
    this.loadHealth();
  },
};
</script>
