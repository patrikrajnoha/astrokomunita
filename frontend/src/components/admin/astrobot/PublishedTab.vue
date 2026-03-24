<template>
  <div class="publishedTab">
    <div class="tabActions">
      <button class="actionbtn" @click="loadItems" :disabled="loading">
        {{ loading ? 'Načítavam...' : 'Obnoviť zoznam' }}
      </button>
    </div>

    <div class="tabHint">Tu vidíš RSS položky, ktoré boli automaticky alebo manuálne publikované.</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-3/4"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-4/5"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa načítať dáta</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="loadItems">Skúsiť znova</button>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="stateTitle">Žiadne publikované položky</div>
    </div>

    <ul v-else class="postsList">
      <li v-for="item in items" :key="item.id" class="postCard">
        <div class="postHeader">
          <span class="postBadge">published</span>
          <span class="postMeta">RSS dátum: {{ formatDateTime(item.published_at) }}</span>
        </div>

        <div class="postContent">{{ item.title }}</div>
        <div v-if="item.summary" class="postSummary">{{ truncate(item.summary, 200) }}</div>

        <div class="postActions">
          <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="ghostbtn">Otvoriť zdroj</a>
          <button class="ghostbtn danger" @click="rejectItem(item)">Presunúť do rejected</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { prompt } = useConfirm()
const toast = useToast()

export default {
  name: 'PublishedTab',
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
          params: { status: 'published', scope: 'all', per_page: 50 },
        })
        this.items = res.data.data || []
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Nepodarilo sa načítať položky.'
      } finally {
        this.loading = false
      }
    },

    async rejectItem(item) {
      const note = await prompt({
        title: 'Presunúť do rejected',
        message: 'Dôvod presunu do rejected (voliteľne):',
        placeholder: 'Napíš poznámku',
        confirmText: 'Presunúť do rejected',
        cancelText: 'Zrušiť',
        variant: 'danger',
      })
      if (note === null) return
      try {
        await api.post(`/admin/astrobot/items/${item.id}/reject`, { note })
        await this.loadItems()
        toast.success('Položka bola presunutá do rejected.')
      } catch (err) {
        this.error = 'Akcia zlyhala: ' + (err?.response?.data?.message || err?.message)
        toast.error(this.error)
      }
    },

    formatDateTime(value) {
      if (!value) return '-'
      return new Date(value).toLocaleString('sk-SK')
    },

    truncate(text, max) {
      if (!text) return ''
      return text.length > max ? text.slice(0, max) + '...' : text
    },
  },
}
</script>

<style scoped>
.publishedTab { display: grid; gap: 1rem; }
.tabActions { display: flex; gap: 1rem; }
.tabHint { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.45); border-radius: 0.8rem; padding: 0.75rem 0.9rem; color: var(--color-text-secondary); font-size: 0.88rem; }
.postsList { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; }
.postCard { padding: 1rem; border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.4); border-radius: 0.9rem; display: grid; gap: 0.6rem; }
.postHeader { display: flex; justify-content: space-between; align-items: center; }
.postBadge { padding: 0.25rem 0.7rem; background: var(--color-success); color: var(--color-white); border-radius: 0.4rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.postMeta { color: var(--color-text-secondary); font-size: 0.83rem; }
.postContent { color: var(--color-surface); font-weight: 700; }
.postSummary { color: var(--color-text-secondary); }
.postActions { display: flex; gap: 0.5rem; }
.danger { color: var(--color-danger); border-color: var(--color-danger); }
.state { padding: 1.3rem; border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.35); border-radius: 0.9rem; }
.stateError { border-style: solid; border-color: rgb(var(--color-danger-rgb) / 0.4); background: rgb(var(--color-danger-rgb) / 0.08); }
.stateTitle { font-weight: 700; color: var(--color-surface); }
.stateText { color: var(--color-text-secondary); }
</style>
