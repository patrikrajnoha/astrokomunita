<template>
  <div class="inboxTab">
    <div class="tabActions">
      <button class="actionbtn" @click="loadItems" :disabled="loading">
        {{ loading ? 'Nacitavam...' : 'Obnovit inbox' }}
      </button>
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Hladat podla nazvu..."
        class="searchInput"
      />
    </div>

    <div class="actionsHint">
      <strong>Needs review:</strong> tu su polozky, ktore sa nepublikovali automaticky. Môzes ich upravit,
      publikovat alebo zamietnut.
    </div>

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
      <div class="stateTitle">Inbox je prazdny</div>
      <div class="stateText">Aktualne nie su ziadne polozky na manualny review.</div>
    </div>

    <ul v-else class="itemsList">
      <li v-for="item in items" :key="item.id" class="itemCard">
        <div class="itemHeader">
          <span class="itemBadge badge-review">needs_review</span>
          <span class="sourceBadge">{{ item.domain || item.source || 'unknown' }}</span>
        </div>

        <div class="itemTitle">{{ item.title }}</div>
        <div v-if="item.summary" class="itemSummary">{{ truncate(item.summary, 260) }}</div>

        <div class="itemMeta">
          <span v-if="item.published_at">RSS datum: {{ formatDateTime(item.published_at) }}</span>
          <span v-if="item.fetched_at">Sync: {{ formatDateTime(item.fetched_at) }}</span>
        </div>

        <div class="itemActions">
          <button class="ghostbtn" @click="openEdit(item)">Upravit</button>
          <button class="actionbtn" @click="publishItem(item)" :disabled="actionLoading[item.id] === 'publish'">
            {{ actionLoading[item.id] === 'publish' ? 'Publikujem...' : 'Publish' }}
          </button>
          <button class="ghostbtn danger" @click="rejectItem(item)" :disabled="actionLoading[item.id] === 'reject'">
            {{ actionLoading[item.id] === 'reject' ? 'Zamietam...' : 'Reject' }}
          </button>
          <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="ghostbtn">Zdroj</a>
        </div>
      </li>
    </ul>

    <div v-if="pagination.last_page > 1" class="pagination">
      <button class="paginationBtn" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">
        Predchadzajuca
      </button>
      <span class="paginationInfo">
        Strana {{ pagination.current_page }} z {{ pagination.last_page }} (spolu {{ pagination.total }})
      </span>
      <button class="paginationBtn" :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)">
        Dalsia
      </button>
    </div>

    <div v-if="editItem" class="modalOverlay" @click="closeEdit">
      <div class="modalCard" @click.stop>
        <div class="modalHeader">
          <h2>Upravit polozku</h2>
          <button class="ghostbtn" @click="closeEdit">&times;</button>
        </div>
        <div class="modalBody">
          <div class="formGroup">
            <label for="title">Nadpis</label>
            <input id="title" v-model="editItem.title" class="formInput" type="text" />
          </div>
          <div class="formGroup">
            <label for="summary">Zhrnutie</label>
            <textarea id="summary" v-model="editItem.summary" class="formTextarea" rows="5"></textarea>
          </div>
          <div class="modalActions">
            <button class="actionbtn" @click="saveEdit">Ulozit</button>
            <button class="ghostbtn" @click="closeEdit">Zrusit</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { confirm, prompt } = useConfirm()
const toast = useToast()

export default {
  name: 'TodayTab',
  data() {
    return {
      loading: false,
      error: null,
      searchQuery: '',
      searchDebounce: null,
      actionLoading: {},
      items: [],
      editItem: null,
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
      },
    }
  },
  watch: {
    searchQuery() {
      clearTimeout(this.searchDebounce)
      this.searchDebounce = setTimeout(() => {
        this.pagination.current_page = 1
        this.loadItems()
      }, 400)
    },
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
          params: {
            status: 'needs_review',
            scope: 'all',
            search: this.searchQuery || undefined,
            page: this.pagination.current_page,
            per_page: this.pagination.per_page,
          },
        })
        this.items = (res.data.data || []).map((item) => ({
          ...item,
          domain: this.extractDomain(item.url),
        }))
        this.pagination = {
          current_page: res.data.current_page || 1,
          last_page: res.data.last_page || 1,
          per_page: res.data.per_page || 20,
          total: res.data.total || 0,
        }
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Nepodarilo sa nacitat inbox.'
      } finally {
        this.loading = false
      }
    },

    openEdit(item) {
      this.editItem = { ...item }
    },

    closeEdit() {
      this.editItem = null
    },

    async saveEdit() {
      if (!this.editItem) return
      try {
        await api.put(`/admin/astrobot/items/${this.editItem.id}`, {
          title: this.editItem.title,
          summary: this.editItem.summary,
        })
        this.closeEdit()
        await this.loadItems()
        toast.success('Polozka bola upravena.')
      } catch (err) {
        this.error = 'Uprava zlyhala: ' + (err?.response?.data?.message || err?.message)
        toast.error(this.error)
      }
    },

    async publishItem(item) {
      const ok = await confirm({
        title: 'Publikovat polozku',
        message: 'Publikovat tuto polozku?',
        confirmText: 'Publish',
        cancelText: 'Cancel',
      })
      if (!ok) return
      this.actionLoading[item.id] = 'publish'
      try {
        await api.post(`/admin/astrobot/items/${item.id}/publish`)
        await this.loadItems()
        toast.success('Polozka bola publikovana.')
      } catch (err) {
        this.error = 'Publikovanie zlyhalo: ' + (err?.response?.data?.message || err?.message)
        toast.error(this.error)
      } finally {
        delete this.actionLoading[item.id]
      }
    },

    async rejectItem(item) {
      const note = await prompt({
        title: 'Zamietnut polozku',
        message: 'Dovod zamietnutia (volitelne):',
        placeholder: 'Napis poznamku',
        confirmText: 'Reject',
        cancelText: 'Cancel',
      })
      if (note === null) return
      this.actionLoading[item.id] = 'reject'
      try {
        await api.post(`/admin/astrobot/items/${item.id}/reject`, { note })
        await this.loadItems()
        toast.success('Polozka bola zamietnuta.')
      } catch (err) {
        this.error = 'Reject zlyhal: ' + (err?.response?.data?.message || err?.message)
        toast.error(this.error)
      } finally {
        delete this.actionLoading[item.id]
      }
    },

    extractDomain(url) {
      try {
        return new URL(url).hostname
      } catch {
        return ''
      }
    },

    truncate(text, max) {
      if (!text) return ''
      return text.length > max ? text.slice(0, max) + '...' : text
    },

    formatDateTime(value) {
      if (!value) return ''
      return new Date(value).toLocaleString('sk-SK')
    },

    goToPage(page) {
      this.pagination.current_page = page
      this.loadItems()
    },
  },
}
</script>

<style scoped>
.inboxTab { display: grid; gap: 1rem; }
.tabActions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
.searchInput { padding: 0.5rem 0.7rem; border-radius: 0.5rem; border: 1px solid var(--color-text-secondary); background: var(--color-bg); color: var(--color-surface); min-width: 240px; }
.actionsHint { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.45); border-radius: 0.8rem; padding: 0.75rem 0.9rem; color: var(--color-text-secondary); font-size: 0.88rem; }
.itemsList { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; }
.itemCard { padding: 1rem; border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); background: rgb(var(--color-bg-rgb) / 0.4); border-radius: 0.9rem; display: grid; gap: 0.6rem; }
.itemHeader { display: flex; gap: 0.5rem; align-items: center; }
.itemBadge { padding: 0.2rem 0.6rem; border-radius: 0.4rem; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; }
.badge-review { background: #f59e0b; color: #fff; }
.sourceBadge { padding: 0.2rem 0.6rem; background: rgb(var(--color-primary-rgb) / 0.16); color: var(--color-primary); border-radius: 0.4rem; font-size: 0.78rem; }
.itemTitle { font-weight: 700; color: var(--color-surface); }
.itemSummary { color: var(--color-text-secondary); }
.itemMeta { display: flex; gap: 0.8rem; flex-wrap: wrap; font-size: 0.83rem; color: var(--color-text-secondary); }
.itemActions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.danger { color: var(--color-danger); border-color: var(--color-danger); }
.pagination { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem; border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2); border-radius: 0.8rem; }
.paginationBtn { padding: 0.4rem 0.7rem; border-radius: 0.5rem; border: 1px solid var(--color-text-secondary); background: var(--color-bg); color: var(--color-surface); }
.paginationInfo { color: var(--color-text-secondary); font-size: 0.85rem; }
.modalOverlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modalCard { background: var(--color-bg); border: 1px solid var(--color-text-secondary); border-radius: 0.9rem; padding: 1rem; width: min(560px, 92vw); }
.modalHeader { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.7rem; }
.modalBody { display: grid; gap: 0.7rem; }
.formGroup { display: grid; gap: 0.35rem; }
.formInput, .formTextarea { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid var(--color-text-secondary); background: var(--color-bg); color: var(--color-surface); }
.modalActions { display: flex; gap: 0.5rem; justify-content: flex-end; }
.state { padding: 1.4rem; border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.35); border-radius: 0.9rem; }
.stateTitle { font-weight: 700; color: var(--color-surface); margin-bottom: 0.3rem; }
.stateText { color: var(--color-text-secondary); }
.stateError { border-style: solid; border-color: rgb(var(--color-danger-rgb) / 0.4); background: rgb(var(--color-danger-rgb) / 0.08); }
</style>
