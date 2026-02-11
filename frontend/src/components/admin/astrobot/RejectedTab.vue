<template>
  <div class="rejectedTab">
    <div class="tabActions">
      <button class="actionbtn" @click="loadItems" :disabled="loading">
        {{ loading ? 'Nacitavam...' : 'Obnovit zoznam' }}
      </button>
    </div>

    <div class="tabHint">Polozky oznacene ako rejected. V pripade potreby ich mozes publikovat spat.</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-3/4"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-4/5"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Chyba</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="loadItems">Skusit znova</button>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="stateTitle">Ziadne rejected polozky</div>
    </div>

    <ul v-else class="itemsList">
      <li v-for="item in items" :key="item.id" class="itemCard">
        <div class="itemHeader">
          <span class="itemBadge">rejected</span>
          <span class="itemMeta">{{ formatDateTime(item.reviewed_at) }}</span>
        </div>
        <div class="itemTitle">{{ item.title }}</div>
        <div v-if="item.review_note" class="itemNote">Poznamka: {{ item.review_note }}</div>
        <div class="itemActions">
          <button class="actionbtn" @click="publishItem(item)">Publish</button>
          <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="ghostbtn">Zdroj</a>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { confirm } = useConfirm()
const toast = useToast()

export default {
  name: 'RejectedTab',
  data() {
    return { loading: false, error: null, items: [] }
  },
  created() {
    this.loadItems()
  },
  methods: {
    async loadItems() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/admin/astrobot/items', {
          params: { status: 'rejected', scope: 'all', per_page: 50 },
        })
        this.items = res.data.data || []
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Nepodarilo sa nacitat rejected polozky.'
      } finally {
        this.loading = false
      }
    },

    async publishItem(item) {
      const ok = await confirm({
        title: 'Publikovat rejected polozku',
        message: 'Publikovat rejected polozku?',
        confirmText: 'Publish',
        cancelText: 'Cancel',
      })
      if (!ok) return
      try {
        await api.post(`/admin/astrobot/items/${item.id}/publish`)
        await this.loadItems()
        toast.success('Polozka bola publikovana.')
      } catch (err) {
        this.error = 'Publikovanie zlyhalo: ' + (err?.response?.data?.message || err?.message)
        toast.error(this.error)
      }
    },

    formatDateTime(value) {
      if (!value) return '-'
      return new Date(value).toLocaleString('sk-SK')
    },
  },
}
</script>

<style scoped>
.rejectedTab { display: grid; gap: 1rem; }
.tabActions { display: flex; gap: 1rem; }
.tabHint { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.45); border-radius: 0.8rem; padding: 0.75rem 0.9rem; color: var(--color-text-secondary); font-size: 0.88rem; }
.itemsList { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; }
.itemCard { padding: 1rem; border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.4); border-radius: 0.9rem; display: grid; gap: 0.6rem; }
.itemHeader { display: flex; justify-content: space-between; align-items: center; }
.itemBadge { padding: 0.25rem 0.7rem; background: #ef4444; color: #fff; border-radius: 0.4rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.itemMeta { color: var(--color-text-secondary); font-size: 0.83rem; }
.itemTitle { color: var(--color-surface); font-weight: 700; }
.itemNote { color: var(--color-text-secondary); font-size: 0.88rem; }
.itemActions { display: flex; gap: 0.5rem; }
.state { padding: 1.3rem; border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.35); border-radius: 0.9rem; }
.stateError { border-style: solid; border-color: rgb(var(--color-danger-rgb) / 0.4); background: rgb(var(--color-danger-rgb) / 0.08); }
.stateTitle { font-weight: 700; color: var(--color-surface); }
.stateText { color: var(--color-text-secondary); }
</style>
