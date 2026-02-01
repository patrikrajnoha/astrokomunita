<template>
  <div class="publishedTab">
    <div class="tabActions">
      <button class="actionbtn" @click="loadPosts" :disabled="loading">
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-3/4"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-4/5"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa načítať</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="loadPosts">Skúsiť znova</button>
    </div>

    <div v-else-if="posts.length === 0" class="state">
      <div class="stateTitle">Žiadne publikované príspevky</div>
    </div>

    <ul v-else class="postsList">
      <li v-for="post in posts" :key="post.id" class="postCard">
        <div class="postHeader">
          <span class="postBadge">AstroBot</span>
          <span class="postMeta">Created: {{ formatDateTime(post.created_at) }}</span>
        </div>

        <div class="postContent">{{ post.content }}</div>

        <div class="postActions">
          <a
            v-if="extractUrl(post.content)"
            :href="extractUrl(post.content)"
            target="_blank"
            rel="noopener noreferrer"
            class="ghostbtn"
          >
            Open original
          </a>
          <button class="ghostbtn danger" @click="deletePost(post)">
            Hide
          </button>
        </div>
      </li>
    </ul>

    <!-- Confirm Delete Modal -->
    <div v-if="deletePostItem" class="modalOverlay" @click="cancelDelete">
      <div class="modalCard" @click.stop>
        <div class="modalHeader">
          <h2>Hide this post?</h2>
          <button class="ghostbtn" @click="cancelDelete">&times;</button>
        </div>
        <div class="modalBody">
          <p>This will hide the post from the feed (soft delete).</p>
          <div class="modalActions">
            <button class="actionbtn danger" @click="confirmDelete">Hide</button>
            <button class="ghostbtn" @click="cancelDelete">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api'

export default {
  name: 'PublishedTab',
  data() {
    return {
      loading: false,
      error: null,
      posts: [],
      deletePostItem: null,
    }
  },
  created() {
    this.loadPosts()
  },
  methods: {
    async loadPosts() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/admin/astrobot/posts', { params: { scope: 'today' } })
        this.posts = res.data.data || []
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Failed to load posts.'
      } finally {
        this.loading = false
      }
    },

    deletePost(post) {
      this.deletePostItem = post
    },

    cancelDelete() {
      this.deletePostItem = null
    },

    async confirmDelete() {
      if (!this.deletePostItem) return
      try {
        await api.delete(`/admin/astrobot/posts/${this.deletePostItem.id}`)
        this.deletePostItem = null
        await this.loadPosts()
      } catch (err) {
        alert('Delete failed: ' + (err?.response?.data?.message || err?.message))
      }
    },

    extractUrl(content) {
      // Simple regex to extract URL from content (last line)
      const match = content.match(/https?:\/\/[^\s]+$/m)
      return match ? match[0] : null
    },

    formatDateTime(value) {
      if (!value) return ''
      const d = new Date(value)
      return d.toLocaleString()
    },
  },
}
</script>

<style scoped>
.publishedTab {
  display: grid;
  gap: 1.5rem;
}

.tabActions {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.postsList {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 1rem;
}

.postCard {
  padding: 1.25rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.4);
  border-radius: 1rem;
  display: grid;
  gap: 0.75rem;
}

.postHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.postBadge {
  padding: 0.25rem 0.75rem;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-primary);
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.postMeta {
  font-size: 0.85rem;
  color: var(--color-text-secondary);
}

.postContent {
  white-space: pre-wrap;
  color: var(--color-surface);
  line-height: 1.5;
}

.postActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.danger {
  color: var(--color-danger);
  border-color: var(--color-danger);
}

.danger:hover {
  background: rgb(var(--color-danger-rgb) / 0.1);
}

.modalOverlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modalCard {
  background: var(--color-bg);
  border: 1px solid var(--color-text-secondary);
  border-radius: 1rem;
  padding: 1.5rem;
  max-width: 400px;
  width: 90%;
}

.modalHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.modalBody {
  display: grid;
  gap: 1rem;
}

.modalActions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
