<template>
  <section class="feed-container">
    <!-- Header -->
    <header class="feed-header">
      <div class="feed-actions">
        <FeedSwitcher
          :tabs="tabs"
          :model-value="activeTab"
          @update:modelValue="switchTab"
        />
      </div>
    </header>

    <section
      v-for="tab in tabs"
      :id="tab.panelId"
      :key="tab.panelId"
      role="tabpanel"
      class="feed-panel"
      :aria-labelledby="tab.tabId"
      :hidden="activeTab !== tab.id"
    >
      <template v-if="activeTab === tab.id">
    <!-- Error -->
    <div v-if="err" class="error-message">
      <span>{{ err }}</span>
      <button type="button" class="retry-btn" @click="retryCurrentTab">Skusit znova</button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && items.length === 0" class="skeleton-container">
      <div class="skeleton-post" v-for="i in 3" :key="i">
        <div class="skeleton-header">
          <div class="skeleton-avatar"></div>
          <div class="skeleton-meta">
            <div class="skeleton-line skeleton-name"></div>
            <div class="skeleton-line skeleton-time"></div>
          </div>
        </div>
        <div class="skeleton-content">
          <div class="skeleton-line skeleton-text"></div>
          <div class="skeleton-line skeleton-text"></div>
        </div>
        <div class="skeleton-media"></div>
      </div>
    </div>

    <div v-else-if="!loading && items.length === 0" class="empty-state">
      <p>Zatial tu nic nie je.</p>
      <button type="button" class="retry-btn" @click="retryCurrentTab">Obnovit feed</button>
    </div>

    <!-- Feed -->
    <div v-else class="feed-list">
      <article
        v-for="p in items"
        :key="p.id"
        class="post-card"
        :class="{ 
          'post-card--pinned': p.pinned_at,
          'post-card--astrobot': p.source_name === 'astrobot',
          'post-card--new': highlightedPostId === p.id
        }"
        @click="openPost(p)"
      >
        <div class="post-avatar">
          <button class="avatar-button" type="button" @click.stop="openProfile(p)">
            <img
              v-if="p?.user?.avatar_url"
              class="avatar-image"
              :src="avatarSrc(p?.user?.avatar_url)"
              :alt="p?.user?.name || 'avatar'"
              loading="lazy"
            />
            <span v-else class="avatar-fallback">{{ initials(p?.user?.name) }}</span>
          </button>
        </div>

        <div class="post-content">
          <!-- Header -->
          <div class="post-header">
            <div class="post-meta">
              <div class="post-author">
                <button class="author-name" type="button" @click.stop="openProfile(p)">
                  {{ p?.user?.name ?? 'User' }}
                </button>
                <span class="author-username">@{{ p?.user?.username }}</span>
                <span v-if="p.source_name === 'astrobot'" class="astrobot-badge">🚀 AstroBot</span>
                <span v-if="p.pinned_at" class="pinned-badge">📌 Pripnuté</span>
              </div>
              <div class="post-time">
                <span class="time-text">{{ fmt(p?.created_at) }}</span>
                <span v-if="p?.user?.location" class="location">📍 {{ p.user.location }}</span>
                <span v-if="p.source_name === 'astrobot'" class="astrobot-label">Automated news · replies disabled</span>
              </div>
            </div>
            <!-- Actions dropdown -->
            <div class="post-actions-menu">
              <DropdownMenu
                v-if="menuItemsForPost(p).length"
                :items="menuItemsForPost(p)"
                label="More actions"
                menu-label="Post actions"
                @select="(item) => onMenuAction(item, p)"
              />
            </div>
          </div>
          <!-- Content -->
          <div class="post-text">
            <HashtagText :content="p.content" />
          </div>
          
          <!-- Source URL for AstroBot posts -->
          <div v-if="p.source_name === 'astrobot' && p.source_url" class="source-url">
            <a :href="p.source_url" target="_blank" rel="noopener noreferrer" class="source-link">
              📰 Zobraziť pôvodný článok
            </a>
          </div>

          <!-- Media attachment -->
          <div v-if="p.attachment_url" class="post-media">
            <PostMediaImage
              v-if="isImage(p)"
              :src="attachmentSrc(p)"
              alt="Priloha prispevku"
            />

            <a
              v-else
              class="file-attachment"
              :href="attachmentSrc(p)"
              target="_blank"
              rel="noopener"
              @click.stop
            >
              <div class="file-icon">📎</div>
              <div class="file-info">
                <div class="file-title">Príloha</div>
                <div class="file-name">
                  {{ p.attachment_original_name || 'Súbor' }}
                </div>
              </div>
              <div class="file-arrow">→</div>
            </a>
          </div>

          <!-- Bottom actions -->
          <div class="post-actions" @click.stop>
<button 
              v-if="p.source_name !== 'astrobot'"
              class="action-btn action-btn--reply" 
              type="button" 
              title="Reagovať" 
              disabled
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
              </svg>
              <span class="action-count">{{ p.replies_count ?? 0 }}</span>
            </button>
            
            <span 
              v-else
              class="action-btn action-btn--disabled"
              title="Replies disabled on automated news"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
              </svg>
              <span class="action-count">{{ p.replies_count ?? 0 }}</span>
            </span>
            
            <button
              class="action-btn action-btn--share"
              type="button"
              title="Zdieľať"
              aria-label="Zdieľať prispevok"
              @click.stop="openShareModal(p)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                <polyline points="16 6 12 2 8 6"/>
                <line x1="12" y1="2" x2="12" y2="15"/>
              </svg>
            </button>
            
            <button
              class="action-btn action-btn--like"
              type="button"
              :class="{ 
                'action-btn--liked': p.liked_by_me, 
                'action-btn--bump': likeBumpId === p.id 
              }"
              :disabled="!auth.isAuthed || isLikeLoading(p)"
              :title="auth.isAuthed ? (p.liked_by_me ? 'Zrušiť like' : 'Páči sa mi') : 'Prihlás sa pre lajkovanie'"
              @click.stop="toggleLike(p)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
              <span class="action-count">{{ p.likes_count ?? 0 }}</span>
            </button>
            
            <div class="action-spacer"></div>
            
            <button
              class="action-btn action-btn--thread"
              type="button"
              title="Počet zobrazení"
              aria-label="Počet zobrazení"
              @click.stop="openPost(p)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <span v-if="Number(p.views ?? 0) > 0" class="view-count">{{ p.views }}</span>
            </button>
            
            <button class="action-btn action-btn--save" type="button" title="Uložiť" disabled>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </button>
</div>
        </div>
      </article>
    </div>

        <!-- Load more -->
    <div class="load-more">
      <button
        v-if="nextPageUrl"
        class="load-more-btn"
        :disabled="loading"
        @click="load(false)"
      >
        {{ loading ? 'Loading...' : 'Load more' }}
      </button>
    </div>
      </template>
    </section>
    <!-- Report modal -->
    <div v-if="reportTarget" class="report-modal" @click.stop>
      <div class="report-content">
        <h3 class="report-title">Nahlasit prispevok</h3>
        <div class="report-form">
          <div class="form-group">
            <label class="form-label">Reason</label>
            <select v-model="reportReason" class="form-select">
              <option value="spam">spam</option>
              <option value="abuse">abuse</option>
              <option value="misinfo">misinfo</option>
              <option value="other">other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Message (optional)</label>
            <textarea v-model="reportMessage" class="form-textarea" rows="3" placeholder="Popis..."></textarea>
          </div>
          <div class="report-actions">
            <button class="btn btn-secondary" type="button" @click="closeReport">Cancel</button>
            <button class="btn btn-primary" type="button" @click="submitReport">Submit</button>
          </div>
        </div>
      </div>
    </div>
    <div v-if="deleteTarget" class="report-modal" @click="closeDeleteConfirm">
      <div
        class="report-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-title"
        @click.stop
      >
        <h3 id="delete-title" class="report-title">Delete post</h3>
        <p class="delete-copy">This action cannot be undone.</p>
        <div class="report-actions">
          <button class="btn btn-secondary" type="button" @click="closeDeleteConfirm">Cancel</button>
          <button
            class="btn btn-danger"
            type="button"
            :disabled="deleteLoadingId === deleteTarget.id"
            @click="confirmDelete"
          >
            {{ deleteLoadingId === deleteTarget.id ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
    <ShareModal :open="!!shareTarget" :post="shareTarget" @close="closeShareModal" />
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import FeedSwitcher from '@/components/FeedSwitcher.vue'
import HashtagText from './HashtagText.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import ShareModal from '@/components/share/ShareModal.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { canDeletePost, canReportPost } from '@/utils/postPermissions'

const router = useRouter()
const auth = useAuthStore()
const tabs = [
  { id: 'for_you', label: 'Pre vas', tabId: 'feed-tab-for-you', panelId: 'feed-panel-for-you' },
  { id: 'astrobot', label: 'AstroBot', tabId: 'feed-tab-astrobot', panelId: 'feed-panel-astrobot' },
]

const feedState = reactive({
  for_you: createFeedState(),
  astrobot: createFeedState(),
})
const activeTab = ref('for_you')
const currentFeed = computed(() => feedState[activeTab.value])
const items = computed(() => currentFeed.value.items)
const nextPageUrl = computed(() => currentFeed.value.nextPageUrl)
const loading = computed(() => currentFeed.value.loading)
const err = computed(() => currentFeed.value.err)
const deleteLoadingId = ref(null)
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const pinLoadingId = ref(null)
const reportTarget = ref(null)
const deleteTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const highlightedPostId = ref(null)
const shareTarget = ref(null)
let highlightTimer = null

function createFeedState() {
  return {
    items: [],
    nextPageUrl: null,
    loading: false,
    err: '',
    controller: null,
    loaded: false,
    scrollY: 0,
  }
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openProfile(post) {
  const username = post?.user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function canDelete(post) {
  return canDeletePost(post, auth.user)
}

function canReport(post) {
  return canReportPost(post, auth.user)
}

function menuItemsForPost(post) {
  const items = []

  if (canReport(post)) {
    items.push({ key: 'report', label: 'Report', danger: false })
  }

  if (canDelete(post)) {
    items.push({ key: 'delete', label: 'Delete', danger: true })
  }

  if (auth.user?.is_admin && post?.source_name !== 'astrobot') {
    items.push({
      key: 'pin',
      label: post?.pinned_at ? 'Unpin' : 'Pin',
      danger: false,
    })
  }

  return items
}

function onMenuAction(item, post) {
  if (!item?.key || !post?.id) return

  if (item.key === 'report') {
    openReport(post)
    return
  }

  if (item.key === 'delete') {
    openDeleteConfirm(post)
    return
  }

  if (item.key === 'pin') {
    togglePin(post)
  }
}

function isLikeLoading(post) {
  return likeLoadingIds.value.has(post?.id)
}

function setLikeLoading(id, on) {
  const next = new Set(likeLoadingIds.value)
  if (on) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function openReport(post) {
  if (!post?.id || !canReport(post)) return
  reportTarget.value = post
}

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

async function submitReport() {
  const post = reportTarget.value
  if (!post?.id) return

  try {
    await auth.csrf()
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })
    currentFeed.value.err = 'Thanks, we will review it.'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 409) currentFeed.value.err = 'Už si reportoval tento post.'
    else currentFeed.value.err = e?.response?.data?.message || 'Report zlyhal.'
  } finally {
    closeReport()
  }
}
function bumpLike(id) {
  likeBumpId.value = id
  window.setTimeout(() => {
    if (likeBumpId.value === id) likeBumpId.value = null
  }, 220)
}

function applyLikeResponse(post, res) {
  const data = res?.data
  if (!data || !post) return
  if (data.likes_count !== undefined) post.likes_count = data.likes_count
  if (data.liked_by_me !== undefined) post.liked_by_me = data.liked_by_me
}

async function toggleLike(post) {
  if (!post?.id || isLikeLoading(post)) return
  if (!auth.isAuthed) {
    currentFeed.value.err = 'Prihlas sa pre lajkovanie.'
    return
  }

  currentFeed.value.err = ''
  const prevLiked = !!post.liked_by_me
  const prevCount = Number(post.likes_count ?? 0) || 0

  post.liked_by_me = !prevLiked
  post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(post.id)
  setLikeLoading(post.id, true)

  try {
    await auth.csrf()
    const res = prevLiked
      ? await api.delete(`/posts/${post.id}/like`)
      : await api.post(`/posts/${post.id}/like`)
    applyLikeResponse(post, res)
  } catch (e) {
    post.liked_by_me = prevLiked
    post.likes_count = prevCount
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else currentFeed.value.err = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(post.id, false)
  }
}

async function deletePost(post) {
  if (!post?.id || deleteLoadingId.value) return
  if (!canDelete(post)) return

  currentFeed.value.err = ''
  deleteLoadingId.value = post.id

  try {
    await auth.csrf()
    await api.delete(`/posts/${post.id}`)
    currentFeed.value.items = currentFeed.value.items.filter((x) => x.id !== post.id)
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 403) currentFeed.value.err = 'Nemas opravnenie.'
    else currentFeed.value.err = e?.response?.data?.message || 'Mazanie zlyhalo.'
  } finally {
    deleteLoadingId.value = null
    closeDeleteConfirm()
  }
}

function openDeleteConfirm(post) {
  if (!post?.id || !canDelete(post)) return
  deleteTarget.value = post
}

function openShareModal(post) {
  if (!post?.id) return
  shareTarget.value = post
}

function closeShareModal() {
  shareTarget.value = null
}

function closeDeleteConfirm() {
  deleteTarget.value = null
}

async function confirmDelete() {
  if (!deleteTarget.value?.id) return
  await deletePost(deleteTarget.value)
}

function initials(name) {
  const n = name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
}

function fmt(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
}

function attachmentSrc(p) {
  const u = p?.attachment_url
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (u.startsWith('/')) return origin + u
  return origin + '/' + u
}

function avatarSrc(url) {
  const u = url || ''
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (u.startsWith('/')) return origin + u
  return origin + '/' + u
}

function isImage(p) {
  const mime = p?.attachment_mime || ''
  if (typeof mime === 'string' && mime.startsWith('image/')) return true

  const name = (p?.attachment_original_name || p?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.jpg') ||
    name.endsWith('.jpeg') ||
    name.endsWith('.png') ||
    name.endsWith('.gif') ||
    name.endsWith('.webp')
  )
}

function saveTabScroll(tab) {
  if (typeof window === 'undefined') return
  if (!feedState[tab]) return
  feedState[tab].scrollY = window.scrollY || 0
}

function restoreTabScroll(tab) {
  if (typeof window === 'undefined') return
  if (!feedState[tab]) return
  window.scrollTo({
    top: feedState[tab].scrollY || 0,
    behavior: 'auto',
  })
}

async function switchTab(tab) {
  if (!feedState[tab]) return
  if (activeTab.value === tab) return

  saveTabScroll(activeTab.value)
  activeTab.value = tab

  if (!feedState[tab].loaded) {
    await load(true, tab)
  }

  await nextTick()
  restoreTabScroll(tab)
}

function resetFeed(tab) {
  const state = feedState[tab]
  if (state?.controller) {
    state.controller.abort()
  }
  state.items = []
  state.nextPageUrl = null
  state.loading = false
  state.err = ''
  state.controller = null
  state.loaded = false
}

async function load(reset = true, tab = activeTab.value) {
  const state = feedState[tab]
  if (!state) return
  if (state.loading) return
  state.loading = true
  state.err = ''

  try {
    let url
    
    if (reset) {
      // Reset pagination state when switching tabs
      state.nextPageUrl = null
      
      // Use dedicated endpoints based on active tab
      if (tab === 'astrobot') {
        url = '/feed/astrobot?with=counts'
      } else {
        // for_you tab uses new unified feed endpoint
        url = '/feed?with=counts'
      }
    } else {
      url = state.nextPageUrl
    }
    
    if (!url) return

    if (state.controller) {
      state.controller.abort()
    }
    const controller = new AbortController()
    state.controller = controller

    const res = await api.get(url, { signal: controller.signal })
    const payload = res.data || {}
    const rows = payload.data || []

    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    state.nextPageUrl = payload.next_page_url || null
    state.loaded = true
  } catch (e) {
    if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return
    state.err = e?.response?.data?.message || e?.message || 'Načítanie feedu zlyhalo.'
  } finally {
    state.loading = false
  }
}

async function retryCurrentTab() {
  const tab = activeTab.value
  resetFeed(tab)
  await load(true, tab)
}
async function togglePin(post) {
  if (!post?.id || pinLoadingId.value) return
  if (!auth.user?.is_admin) {
    currentFeed.value.err = 'Admin access required.'
    return
  }

  currentFeed.value.err = ''
  const wasPinned = !!post.pinned_at
  pinLoadingId.value = post.id

  try {
    await auth.csrf()
    if (wasPinned) {
      await api.patch(`/admin/posts/${post.id}/unpin`)
    } else {
      await api.patch(`/admin/posts/${post.id}/pin`)
    }
    
    // Update local state
    if (wasPinned) {
      post.pinned_at = null
    } else {
      post.pinned_at = new Date().toISOString()
    }
    
    // Refresh feed to re-order
    load(true)
    
    currentFeed.value.err = wasPinned ? 'Post unpinned successfully' : 'Post pinned successfully'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 403) currentFeed.value.err = 'Nemáš oprávnenie.'
    else currentFeed.value.err = e?.response?.data?.message || 'Pin action failed.'
  } finally {
    pinLoadingId.value = null
  }
}

function prepend(post) {
  if (!post?.id) return
  const state = feedState.for_you
  state.items = [post, ...state.items]
  state.loaded = true
  highlightedPostId.value = post.id

  if (highlightTimer) {
    clearTimeout(highlightTimer)
  }
  highlightTimer = setTimeout(() => {
    if (highlightedPostId.value === post.id) highlightedPostId.value = null
    highlightTimer = null
  }, 1800)
}

function handleGlobalKeydown(event) {
  if (event.ctrlKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
    event.preventDefault()
    const currentIndex = tabs.findIndex((tab) => tab.id === activeTab.value)
    if (currentIndex < 0) return

    const direction = event.key === 'ArrowRight' ? 1 : -1
    const nextIndex = (currentIndex + direction + tabs.length) % tabs.length
    switchTab(tabs[nextIndex].id)
    return
  }

  if (event.key !== 'Escape') return
  if (reportTarget.value) closeReport()
  if (deleteTarget.value) closeDeleteConfirm()
}

onMounted(() => {
  load(true, activeTab.value)
  window.addEventListener('keydown', handleGlobalKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  Object.values(feedState).forEach((state) => {
    if (state?.controller) {
      state.controller.abort()
    }
  })
  if (highlightTimer) {
    clearTimeout(highlightTimer)
    highlightTimer = null
  }
})

defineExpose({ load, prepend })
</script>

<style scoped>
/* Modern Feed Styles */
.feed-container {
  max-width: 760px;
  margin: 0 auto;
  padding: 0;
  width: 100%;
  min-width: 0;
  overflow-x: clip;
}

/* Header */
.feed-header {
  position: sticky;
  top: 0;
  z-index: 18;
  margin-bottom: 12px;
  padding: 0 4px;
  background: rgb(var(--color-bg-rgb) / 0.86);
  backdrop-filter: blur(9px);
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
}

.feed-title-section {
  margin-bottom: 20px;
}

.feed-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-surface);
  margin-bottom: 4px;
  font-family: 'Space Grotesk', sans-serif;
}

.feed-subtitle {
  color: var(--color-text-secondary);
  font-size: 0.95rem;
  font-weight: 400;
}

.feed-actions {
  width: 100%;
}

.feed-panel {
  min-width: 0;
}

.empty-state {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-text-secondary);
  padding: 16px;
  display: grid;
  gap: 10px;
  justify-items: start;
}

.retry-btn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.55);
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.35rem 0.8rem;
  cursor: pointer;
}

.retry-btn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  color: var(--color-primary);
}

/* Modern Tabs */
.feed-tabs {
  display: flex;
  background: rgb(var(--color-bg-rgb) / 0.5);
  border-radius: 12px;
  padding: 4px;
  gap: 4px;
}

.tab-button {
  flex: 1;
  padding: 12px 20px;
  border: none;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 44px;
}

.tab-button:hover {
  color: var(--color-surface);
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
}

.tab-button.active {
  background: var(--color-surface);
  color: var(--color-bg);
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-button:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}


/* Error Message */
.error-message {
  background: rgb(var(--color-danger-rgb) / 0.1);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.3);
  color: var(--color-danger);
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

/* Loading Skeleton */
.skeleton-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 4px;
}

.skeleton-post {
  background: rgb(var(--color-bg-rgb) / 0.3);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 12px;
  padding: 16px;
  overflow: hidden;
}

.skeleton-header {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.skeleton-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skeleton-meta {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-content {
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-media {
  height: 200px;
  border-radius: 8px;
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton-line {
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skeleton-name { width: 120px; }
.skeleton-time { width: 80px; }
.skeleton-text:first-child { width: 100%; }
.skeleton-text:last-child { width: 70%; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Modern Post Cards */
.feed-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 0 4px;
  min-width: 0;
}

.post-card {
  background: rgb(var(--color-bg-rgb) / 0.4);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15);
  border-radius: 12px;
  padding: 16px;
  transition: all 0.2s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  width: 100%;
  min-width: 0;
}

.post-card:hover {
  background: rgb(var(--color-bg-rgb) / 0.6);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.25);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.post-card:active {
  transform: translateY(0);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.post-card--new {
  animation: newPostReveal 760ms cubic-bezier(0.2, 0.8, 0.2, 1);
  border-color: rgb(var(--color-primary-rgb) / 0.48);
}

@keyframes newPostReveal {
  0% {
    opacity: 0.35;
    transform: translateY(-10px) scale(0.985);
    box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0.35);
  }
  55% {
    opacity: 1;
    transform: translateY(0) scale(1);
    box-shadow: 0 0 0 8px rgb(var(--color-primary-rgb) / 0.08);
  }
  100% {
    box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0);
  }
}

/* Pinned posts */
.post-card--pinned {
  border-color: rgb(var(--color-warning-rgb) / 0.4);
  background: linear-gradient(
    135deg,
    rgb(var(--color-warning-rgb) / 0.08) 0%,
    rgb(var(--color-bg-rgb) / 0.4) 100%
  );
}

.post-card--pinned:hover {
  border-color: rgb(var(--color-warning-rgb) / 0.6);
  background: linear-gradient(
    135deg,
    rgb(var(--color-warning-rgb) / 0.12) 0%,
    rgb(var(--color-bg-rgb) / 0.6) 100%
  );
}

/* AstroBot posts */
.post-card--astrobot {
  border-color: rgb(var(--color-success-rgb) / 0.3);
  background: linear-gradient(
    135deg,
    rgb(var(--color-success-rgb) / 0.05) 0%,
    rgb(var(--color-bg-rgb) / 0.4) 100%
  );
}

.post-card--astrobot:hover {
  border-color: rgb(var(--color-success-rgb) / 0.5);
  background: linear-gradient(
    135deg,
    rgb(var(--color-success-rgb) / 0.08) 0%,
    rgb(var(--color-bg-rgb) / 0.6) 100%
  );
}

/* Post Layout */
.post-card {
  display: flex;
  gap: 12px;
}

.post-avatar {
  flex-shrink: 0;
}

.post-content {
  flex: 1;
  min-width: 0;
}

/* Modern Avatar */
.avatar-button {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  border-radius: 50%;
  overflow: hidden;
  transition: transform 0.2s ease;
}

.avatar-button:hover {
  transform: scale(1.05);
}

.avatar-button:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.avatar-image {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 2px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.avatar-fallback {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    var(--color-primary) 0%,
    rgb(var(--color-primary-rgb) / 0.7) 100%
  );
  color: white;
  font-weight: 700;
  font-size: 16px;
  border: 2px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

/* Modern Post Header */
.post-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.post-meta {
  flex: 1;
  min-width: 0;
}

.post-author {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 4px;
  min-width: 0;
}

.author-name {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-surface);
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.author-name:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
}

.author-name:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.author-username {
  color: var(--color-text-secondary);
  font-size: 14px;
  font-weight: 400;
}

.astrobot-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  background: linear-gradient(
    135deg,
    rgb(var(--color-success-rgb) / 0.2) 0%,
    rgb(var(--color-success-rgb) / 0.1) 100%
  );
  color: var(--color-success);
  border: 1px solid rgb(var(--color-success-rgb) / 0.3);
}

.pinned-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  background: linear-gradient(
    135deg,
    rgb(var(--color-warning-rgb) / 0.2) 0%,
    rgb(var(--color-warning-rgb) / 0.1) 100%
  );
  color: var(--color-warning);
  border: 1px solid rgb(var(--color-warning-rgb) / 0.3);
}

.post-time {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  color: var(--color-text-secondary);
  font-size: 13px;
  font-weight: 400;
}

.time-text {
  color: inherit;
}

.location {
  display: flex;
  align-items: center;
  gap: 2px;
  color: inherit;
}

.astrobot-label {
  font-style: italic;
  opacity: 0.8;
  color: inherit;
}

.post-actions-menu {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.action-button {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-button:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: var(--color-surface);
}

.action-button:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modern Post Content */
.post-text {
  margin-bottom: 12px;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 15px;
  word-wrap: break-word;
  word-break: break-word;
  overflow-wrap: break-word;
  max-width: 100%;
}

.post-text a {
  color: var(--color-primary);
  text-decoration: none;
  font-weight: 500;
  border-bottom: 1px solid transparent;
  transition: border-color 0.2s ease;
}

.post-text a:hover {
  border-bottom-color: var(--color-primary);
}

/* Source URL */
.source-url {
  margin-top: 12px;
}

.source-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 8px;
  background: linear-gradient(
    135deg,
    rgb(var(--color-primary-rgb) / 0.1) 0%,
    rgb(var(--color-primary-rgb) / 0.05) 100%
  );
  border: 1px solid rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-primary);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.source-link:hover {
  background: linear-gradient(
    135deg,
    rgb(var(--color-primary-rgb) / 0.15) 0%,
    rgb(var(--color-primary-rgb) / 0.08) 100%
  );
  border-color: rgb(var(--color-primary-rgb) / 0.3);
  transform: translateY(-1px);
}

/* Media */
.post-media {
  margin-top: 12px;
}

/* File Attachment */
.file-attachment {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
  text-decoration: none;
  transition: all 0.2s ease;
}

.file-attachment:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.3);
  transform: translateY(-1px);
}

.file-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    rgb(var(--color-primary-rgb) / 0.15) 0%,
    rgb(var(--color-primary-rgb) / 0.08) 100%
  );
  border: 1px solid rgb(var(--color-primary-rgb) / 0.2);
  font-size: 18px;
}

.file-info {
  flex: 1;
  min-width: 0;
}

.file-title {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 2px;
}

.file-name {
  color: var(--color-text-secondary);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-arrow {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

/* Modern Action Buttons */
.post-actions {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
  padding-top: 12px;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.1);
  flex-wrap: wrap;
  min-width: 0;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: none;
  background: transparent;
  color: var(--color-text-secondary);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 36px;
  text-decoration: none;
}

.action-btn:hover:not(:disabled) {
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
  color: var(--color-surface);
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn--disabled {
  opacity: 0.4;
  cursor: not-allowed;
  font-style: italic;
}

.action-btn--report {
  color: var(--color-text-secondary);
}

.action-btn--report:hover:not(:disabled) {
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: var(--color-danger);
}

.action-btn--reply {
  color: var(--color-text-secondary);
}

.action-btn--reply:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: var(--color-primary);
}

.action-btn--share {
  color: var(--color-text-secondary);
}

.action-btn--share:hover:not(:disabled) {
  background: rgb(var(--color-success-rgb) / 0.1);
  color: var(--color-success);
}

.action-btn--like {
  color: var(--color-text-secondary);
  position: relative;
}

.action-btn--like:hover:not(:disabled) {
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: var(--color-danger);
}

.action-btn--like.action-btn--liked {
  color: var(--color-danger);
  font-weight: 600;
}

.action-btn--like.action-btn--bump {
  animation: likePop 220ms ease;
}

.action-btn--thread {
  color: var(--color-text-secondary);
}

.action-btn--thread:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: var(--color-primary);
}

.action-btn--save {
  color: var(--color-text-secondary);
}

.action-btn--save:hover:not(:disabled) {
  background: rgb(var(--color-warning-rgb) / 0.1);
  color: var(--color-warning);
}

.action-btn--delete {
  color: var(--color-danger);
}

.action-btn--delete:hover:not(:disabled) {
  background: rgb(var(--color-danger-rgb) / 0.1);
}

.action-count {
  font-size: 12px;
  font-weight: 500;
  min-width: 16px;
  text-align: center;
}

.view-count {
  font-size: 12px;
  color: var(--color-text-secondary);
  font-weight: 500;
  line-height: 1;
}

.action-text {
  font-size: 12px;
  font-weight: 500;
}

.action-spacer {
  flex: 1;
}

@keyframes likePop {
  0% { transform: scale(1); }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); }
}

/* Load More Button */
.load-more {
  display: flex;
  justify-content: center;
  padding: 20px 4px;
}

.load-more-btn {
  padding: 12px 24px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 44px;
}

.load-more-btn:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.1);
  border-color: rgb(var(--color-primary-rgb) / 0.3);
  color: var(--color-primary);
  transform: translateY(-1px);
}

.load-more-btn:active:not(:disabled) {
  transform: translateY(0);
}

.load-more-btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.load-more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Report Modal */
.report-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.report-content {
  background: var(--color-bg);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
}

.report-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-surface);
  margin-bottom: 16px;
  font-family: 'Space Grotesk', sans-serif;
}

.report-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-surface);
}

.form-select,
.form-textarea {
  padding: 8px 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 6px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  font-size: 14px;
  transition: border-color 0.2s ease;
}

.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-primary);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
  font-family: inherit;
}

.report-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 8px;
}

.btn {
  padding: 8px 16px;
  border-radius: 6px;
  border: 1px solid;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 36px;
}

.btn-secondary {
  background: transparent;
  border-color: rgb(var(--color-text-secondary-rgb) / 0.3);
  color: var(--color-text-secondary);
}

.btn-secondary:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: var(--color-surface);
}

.btn-primary {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: white;
}

.btn-primary:hover {
  background: rgb(var(--color-primary-rgb) / 0.9);
  border-color: rgb(var(--color-primary-rgb) / 0.9);
}

.btn-danger {
  background: rgb(var(--color-danger-rgb) / 0.18);
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.btn-danger:hover {
  background: rgb(var(--color-danger-rgb) / 0.26);
}

.btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.delete-copy {
  color: var(--color-text-secondary);
  margin: 0 0 0.9rem;
}

@media (max-width: 480px) {
  .feed-container {
    padding: 0 6px;
  }

  .feed-header {
    margin-bottom: 16px;
  }

  .feed-title {
    font-size: 1.2rem;
  }

  .feed-subtitle {
    font-size: 0.85rem;
  }

  .feed-tabs {
    width: 100%;
  }

  .tab-button {
    padding: 10px 12px;
    font-size: 0.82rem;
  }

  .post-card {
    padding: 10px;
    gap: 8px;
    border-radius: 10px;
  }

  .post-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .post-actions-menu {
    width: 100%;
    justify-content: flex-end;
  }

  .avatar-image,
  .avatar-fallback {
    width: 36px;
    height: 36px;
    font-size: 13px;
  }

  .author-name {
    font-size: 13px;
  }

  .author-username {
    font-size: 12px;
  }

  .post-text {
    font-size: 13px;
    line-height: 1.45;
  }

  .post-time {
    font-size: 12px;
  }

  .action-btn {
    padding: 6px 8px;
    font-size: 12px;
    min-height: 32px;
  }

  .action-count {
    font-size: 11px;
  }

  .action-spacer {
    display: none;
  }

  .report-content {
    padding: 16px;
    margin: 10px;
  }
}

@media (min-width: 481px) and (max-width: 768px) {
  .feed-container {
    padding: 0 10px;
  }

  .feed-title {
    font-size: 1.32rem;
  }

  .post-card {
    padding: 12px;
    gap: 10px;
  }

  .post-header {
    gap: 10px;
  }

  .avatar-image,
  .avatar-fallback {
    width: 40px;
    height: 40px;
    font-size: 14px;
  }

  .author-name {
    font-size: 14px;
  }

  .author-username {
    font-size: 13px;
  }

  .post-text {
    font-size: 14px;
  }

  .action-btn {
    padding: 6px 10px;
    font-size: 12px;
    min-height: 34px;
  }

  .report-content {
    padding: 16px;
    margin: 16px;
  }
}

@media (min-width: 769px) {
  .feed-container {
    padding: 0 4px;
  }

  .post-card {
    padding: 16px;
    gap: 12px;
  }
}

</style>

