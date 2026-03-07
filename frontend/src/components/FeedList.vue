<template>
  <section class="feed-container" data-tour="feed">
    <!-- Header -->
    <header class="feed-header">
      <div class="feed-actions">
        <FeedSwitcher
          v-if="tabs.length > 1"
          :tabs="tabs"
          :model-value="activeTab"
          @update:modelValue="switchTab"
        />
      </div>
    </header>
    <div v-if="$slots.composer" class="feed-composer-slot">
      <slot name="composer" :active-tab="activeTab" />
    </div>

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
          <InlineStatus
            variant="error"
            :message="err"
            action-label="Skusit znova"
            @action="retryCurrentTab"
          />
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading && items.length === 0" class="skeleton-container">
          <div class="skeleton-post" v-for="i in 3" :key="i">
            <div class="skeleton-header">
              <div class="skeleton-avatar ui-skeleton ui-skeleton--avatar"></div>
              <div class="skeleton-meta">
                <div class="skeleton-line skeleton-name ui-skeleton ui-skeleton--line"></div>
                <div class="skeleton-line skeleton-time ui-skeleton ui-skeleton--line"></div>
              </div>
            </div>
            <div class="skeleton-content">
              <div class="skeleton-line skeleton-text ui-skeleton ui-skeleton--line"></div>
              <div class="skeleton-line skeleton-text ui-skeleton ui-skeleton--line"></div>
            </div>
            <div class="skeleton-media ui-skeleton ui-skeleton--card"></div>
          </div>
        </div>

        <AsyncState
          v-else-if="!loading && items.length === 0"
          mode="empty"
          :title="isBookmarksMode ? 'Ziadne ulozene prispevky' : 'Feed je zatial prazdny'"
          :message="isBookmarksMode ? 'Uloz prispevok a zobrazime ho tu.' : 'Skus obnovit feed alebo sa vratit neskor.'"
          :action-label="isBookmarksMode ? 'Preskumat' : 'Obnovit feed'"
          compact
          @action="handleEmptyAction"
        />

        <!-- Feed -->
        <div v-else class="feed-list ui-stream">
          <article
            v-for="p in items"
            :key="p.id"
            class="post-card ui-stream-item"
            :class="{
              'post-card--pinned': p.pinned_at,
              'post-card--astrobot': isBotPost(p),
              'post-card--new': highlightedPostId === p.id,
            }"
            @click="openPost(p)"
          >
            <div class="post-avatar">
              <button class="avatar-button" type="button" @click.stop="openProfile(p)">
                <UserAvatar class="avatar-fallback" :user="p?.user" :alt="p?.user?.name || 'avatar'" />
              </button>
            </div>

            <div class="post-content">
              <!-- Header -->
              <div class="post-header">
                <div class="post-meta">
                  <div class="post-author ui-stream-item__meta">
                    <button class="author-name" type="button" @click.stop="openProfile(p)">
                      {{ p?.user?.name ?? 'Pouzivatel' }}
                    </button>
                    <span v-if="isBotPost(p)" class="author-bot-badge" aria-label="Overeny bot">BOT</span>
                    <span class="author-username">@{{ p?.user?.username }}</span>
                    <span class="author-time">{{ fmt(p?.created_at) }}</span>
                    <span v-if="p.pinned_at" class="pinned-badge">📌 Pripnuté</span>
                  </div>
                  <div v-if="isBotPost(p)" class="bot-meta-row">
                    <span class="bot-source-label">{{ botSourceLabel(p) }}</span>
                  </div>
                </div>
              </div>
              <ObservationCard
                v-if="observationForPost(p)"
                class="embedded-observation-card"
                :observation="observationForPost(p)"
                :show-author="false"
                :show-event-link="true"
                :compact="true"
              />
              <!-- Content -->
              <div v-if="!observationForPost(p)" class="post-text ui-stream-item__body" @click.stop>
                <template v-if="isEditingPost(p)">
                  <textarea
                    v-model="editContentDraft"
                    class="inline-edit-textarea"
                    rows="6"
                    maxlength="5000"
                  ></textarea>
                  <div class="inline-edit-actions">
                    <button
                      type="button"
                      class="ui-btn ui-btn--secondary editActionBtn"
                      :disabled="editSavingId === p.id"
                      @click.stop="saveInlineEdit(p)"
                    >
                      {{ editSavingId === p.id ? 'Ukladam...' : 'Ulozit' }}
                    </button>
                    <button
                      type="button"
                      class="ui-btn ui-btn--ghost editActionBtn"
                      :disabled="editSavingId === p.id"
                      @click.stop="cancelInlineEdit"
                    >
                      Zrusit
                    </button>
                  </div>
                </template>
                <template v-else>
                  <HashtagText :content="displayPostContent(p)" />
                  <button
                    v-if="isBotContentCollapsible(p)"
                    class="show-more-btn"
                    type="button"
                    @click.stop="togglePostContent(p)"
                  >
                    {{ isPostContentExpanded(p) ? 'Zobrazit menej' : 'Zobrazit viac' }}
                  </button>
                </template>
              </div>

              <PollCard
                v-if="!observationForPost(p) && p.poll"
                :poll="p.poll"
                :post-id="p.id"
                :is-authed="auth.isAuthed"
                @updated="(nextPoll) => updatePostPoll(p, nextPoll)"
                @login-required="onPollLoginRequired"
              />

              <div v-if="!observationForPost(p) && attachedEventForPost(p)" class="attached-event-card" @click.stop>
                <div class="attached-event-copy">
                  <p class="attached-event-title">{{ attachedEventForPost(p).title || 'Udalost' }}</p>
                  <p class="attached-event-date">
                    {{ formatEventRange(attachedEventForPost(p).start_at, attachedEventForPost(p).end_at) }}
                  </p>
                </div>
                <button type="button" class="ui-btn ui-btn--subtle attached-event-btn" @click.stop="openAttachedEvent(p)">
                  Otvorit udalost
                </button>
              </div>

              <div v-if="!observationForPost(p) && isBotPost(p)" class="source-url source-url--bot" @click.stop>
                <span class="source-attribution">Zdroj: {{ sourceAttributionLabel(p) }}</span>
                <a
                  v-if="sourceLink(p)"
                  :href="sourceLink(p)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="source-link"
                >
                  Zdroj
                </a>
              </div>

              <div v-if="!observationForPost(p) && stelaPreviewImageSrc(p)" class="post-media post-media--stela">
                <div v-if="isAttachmentBlocked(p)" class="media-removed">Odstranene</div>
                <PostMediaImage
                  v-else
                  :src="stelaPreviewImageSrc(p)"
                  alt="Stela APOD nahlad"
                  :blurred="isAttachmentPending(p)"
                  :status="p?.status"
                  fit="cover"
                  frame-aspect-ratio="2 / 1"
                  :show-oversize-overlay="false"
                />
              </div>

              <div v-if="!observationForPost(p) && postGifUrl(p) && !stelaPreviewImageSrc(p)" class="post-media post-media--gif">
                <img class="gifEmbed" :src="postGifUrl(p)" :alt="postGifTitle(p)" loading="lazy" />
              </div>

              <!-- Media attachment -->
              <div v-if="!observationForPost(p) && p.attachment_url && !stelaPreviewImageSrc(p)" class="post-media">
                <div v-if="isAttachmentBlocked(p)" class="media-removed">Odstranene</div>
                <PostMediaImage
                  v-else-if="isImage(p)"
                  :src="attachmentSrc(p)"
                  alt="Priloha prispevku"
                  :blurred="isAttachmentPending(p)"
                  :status="p?.status"
                  fit="cover"
                  frame-aspect-ratio="2 / 1"
                  :show-oversize-overlay="false"
                />

                <PostMediaVideo
                  v-else-if="isVideo(p) && !isAttachmentPending(p)"
                  :src="attachmentSrc(p)"
                  :type="attachmentMime(p) || undefined"
                />

                <a
                  v-else-if="!isAttachmentPending(p)"
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
              <PostActionBar
                :item="p"
                :reply-count="Number(p.replies_count ?? 0)"
                :like-count="Number(p.likes_count ?? 0)"
                :like-loading="isLikeLoading(p)"
                :bookmark-loading="isBookmarkLoading(p)"
                :like-bump="likeBumpId === p.id"
                :is-authed="auth.isAuthed"
                :menu-items="menuItemsForPost(p)"
                @reply="openPost(p)"
                @like="toggleLike(p)"
                @bookmark="toggleBookmark(p)"
                @share="openShareModal(p)"
                @menu-select="(item) => onMenuAction(item, p)"
              />
            </div>
          </article>
        </div>

        <!-- Load more -->
        <div class="load-more">
          <button v-if="nextPageUrl" class="ui-btn ui-btn--secondary load-more-btn" :disabled="loading" @click="load(false)">
            {{ loading ? 'Nacitavam...' : 'Nacitat dalsie' }}
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
            <label class="form-label">Dovod</label>
            <select v-model="reportReason" class="ui-select form-select">
              <option value="spam">Spam</option>
              <option value="abuse">Nevhodny obsah</option>
              <option value="misinfo">Dezinformacie</option>
              <option value="other">Ine</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sprava (volitelne)</label>
            <textarea
              v-model="reportMessage"
              class="ui-textarea form-textarea"
              rows="3"
              placeholder="Popis..."
            ></textarea>
          </div>
          <div class="report-actions">
            <button class="ui-btn ui-btn--ghost reportBtn" type="button" @click="closeReport">Zrusit</button>
            <button class="ui-btn ui-btn--primary reportBtn" type="button" @click="submitReport">Odoslat</button>
          </div>
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
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from './HashtagText.vue'
import PollCard from '@/components/PollCard.vue'
import PostActionBar from '@/components/PostActionBar.vue'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import PostMediaVideo from '@/components/media/PostMediaVideo.vue'
import ShareModal from '@/components/share/ShareModal.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useBookmarksStore } from '@/stores/bookmarks'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { canDeletePost, canReportPost } from '@/utils/postPermissions'
import { formatRelativeShort } from '@/utils/dateUtils'
import { avatarDebug } from '@/utils/avatarDebug'

const props = defineProps({
  mode: {
    type: String,
    default: 'home',
  },
})

const router = useRouter()
const auth = useAuthStore()
const bookmarks = useBookmarksStore()
const { error: toastError, info: toastInfo, success: toastSuccess } = useToast()
const { confirm } = useConfirm()
const HOME_TABS = [
  { id: 'for_you', label: 'Komunita', tabId: 'feed-tab-for-you', panelId: 'feed-panel-for-you' },
  {
    id: 'astrobot',
    label: 'AstroFeed ✨',
    tabId: 'feed-tab-astrobot',
    panelId: 'feed-panel-astrobot',
  },
]
const BOOKMARK_TABS = [
  {
    id: 'bookmarks',
    label: 'Zalozky',
    tabId: 'feed-tab-bookmarks',
    panelId: 'feed-panel-bookmarks',
  },
]
const BOT_CONTENT_PREVIEW_LIMIT = 800
const HOME_FEED_TAB_STORAGE_KEY = 'astrokomunita.feed.activeTab'
const isBookmarksMode = computed(() => props.mode === 'bookmarks')
const tabs = computed(() => (isBookmarksMode.value ? BOOKMARK_TABS : HOME_TABS))

const feedState = reactive({
  for_you: createFeedState(),
  astrobot: createFeedState(),
  bookmarks: createFeedState(),
})
const activeTab = ref(resolveInitialTab())
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
const reportReason = ref('spam')
const reportMessage = ref('')
const highlightedPostId = ref(null)
const shareTarget = ref(null)
const expandedPostIds = ref(new Set())
const botContentVariantById = ref({})
const editingPostId = ref(null)
const editContentDraft = ref('')
const editSavingId = ref(null)
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

function resolveInitialTab() {
  if (isBookmarksMode.value) return 'bookmarks'
  if (typeof window === 'undefined') return 'for_you'

  const stored = window.localStorage.getItem(HOME_FEED_TAB_STORAGE_KEY)
  if (stored && HOME_TABS.some((tab) => tab.id === stored)) {
    return stored
  }

  return 'for_you'
}

function observationForPost(post) {
  const embedded = post?.attached_observation
  if (embedded && typeof embedded === 'object') {
    return embedded
  }

  const fallbackId = Number(post?.meta?.observation?.observation_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) {
    return null
  }

  return {
    id: fallbackId,
    title: `Pozorovanie #${fallbackId}`,
    media: [],
  }
}

function persistActiveTab(tab) {
  if (isBookmarksMode.value || typeof window === 'undefined') return
  if (!HOME_TABS.some((entry) => entry.id === tab)) return

  window.localStorage.setItem(HOME_FEED_TAB_STORAGE_KEY, tab)
}

function openPost(post) {
  const observationId = Number(observationForPost(post)?.id || 0)
  if (Number.isInteger(observationId) && observationId > 0) {
    router.push(`/observations/${observationId}`)
    return
  }

  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openProfile(post) {
  const username = post?.user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function goExplore() {
  router.push('/search')
}

function handleEmptyAction() {
  if (isBookmarksMode.value) {
    goExplore()
    return
  }

  retryCurrentTab()
}

function canDelete(post) {
  return canDeletePost(post, auth.user)
}

function canReport(post) {
  return canReportPost(post, auth.user)
}

function normalizeToken(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
}

function isBotPost(post) {
  const authorKind = normalizeToken(post?.author_kind)
  if (authorKind) return authorKind === 'bot'
  return normalizeToken(post?.source_name) === 'astrobot'
}

function canAdminEditBotPost(post) {
  const isAdmin = Boolean(auth.user?.is_admin || auth.user?.role === 'admin')
  if (!isAdmin || !isBotPost(post)) return false

  const identity = normalizeToken(post?.bot_identity)
  return identity === 'kozmo' || identity === 'stela'
}

function canEditTranslatedVariant(post) {
  if (!canAdminEditBotPost(post)) return false
  if (!showBotTranslationToggle(post)) return true
  return (resolvedBotVariant(post) || defaultBotVariant(post)) === 'translated'
}

function botIdentity(post) {
  return normalizeToken(post?.bot_identity)
}

function botSourceLabel(post) {
  if (!isBotPost(post)) return ''
  const sourceKey = normalizeToken(post?.meta?.bot_source_key)
  if (sourceKey === 'nasa_apod_daily') {
    return 'APOD dňa'
  }

  const sourceLabel = String(post?.meta?.bot_source_label || '').trim()
  return sourceLabel || 'Bot'
}

function sourceAttributionLabel(post) {
  const attribution = String(
    post?.meta?.bot_source_attribution || post?.meta?.source_attribution || '',
  ).trim()
  return attribution || botSourceLabel(post)
}

function absoluteUrl(url) {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}

function sourceLink(post) {
  const candidate = post?.meta?.source_url
  return absoluteUrl(candidate)
}

function showBotTranslationToggle(post) {
  if (!isBotPost(post)) return false
  const hasOriginal =
    String(post?.meta?.original_title || '').trim() !== '' ||
    String(post?.meta?.original_content || '').trim() !== ''
  const hasTranslated =
    String(post?.meta?.translated_title || '').trim() !== '' ||
    String(post?.meta?.translated_content || '').trim() !== ''
  return hasOriginal && hasTranslated
}

function normalizeBool(value) {
  if (value === true || value === false) return value
  if (typeof value === 'number') return value === 1
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  if (normalized === '1' || normalized === 'true' || normalized === 'yes') return true
  if (normalized === '0' || normalized === 'false' || normalized === 'no') return false
  return false
}

function defaultBotVariant(post) {
  return normalizeBool(post?.meta?.used_translation) ? 'translated' : 'original'
}

function resolvedBotVariant(post) {
  if (!showBotTranslationToggle(post)) return null
  const id = Number(post?.id || 0)
  if (!id) return defaultBotVariant(post)
  const current = botContentVariantById.value[id]
  if (current === 'translated' || current === 'original') {
    return current
  }
  return defaultBotVariant(post)
}

function setBotContentVariant(post, variant) {
  if (!showBotTranslationToggle(post)) return
  if (variant !== 'translated' && variant !== 'original') return
  const id = Number(post?.id || 0)
  if (!id) return
  botContentVariantById.value = {
    ...botContentVariantById.value,
    [id]: variant,
  }
}

function isBotVariantActive(post, variant) {
  return resolvedBotVariant(post) === variant
}

function variantText(post, variant) {
  const title = String(post?.meta?.[`${variant}_title`] || '').trim()
  const content = String(post?.meta?.[`${variant}_content`] || '').trim()
  return [title, content].filter(Boolean).join('\n\n')
}

function resolvedDisplayText(post) {
  if (showBotTranslationToggle(post)) {
    const variant = resolvedBotVariant(post) || defaultBotVariant(post)
    const text = variantText(post, variant)
    if (text !== '') {
      return text
    }
  }

  return String(post?.content || '')
}

function isPostContentExpanded(post) {
  return expandedPostIds.value.has(post?.id)
}

function isBotContentCollapsible(post) {
  const content = resolvedDisplayText(post)
  return isBotPost(post) && content.length > BOT_CONTENT_PREVIEW_LIMIT
}

function togglePostContent(post) {
  const id = post?.id
  if (!id) return

  const next = new Set(expandedPostIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedPostIds.value = next
}

function displayPostContent(post) {
  const content = resolvedDisplayText(post)
  if (!isBotContentCollapsible(post) || isPostContentExpanded(post)) return content
  return content.slice(0, BOT_CONTENT_PREVIEW_LIMIT).trimEnd() + '...'
}

function isStelaPost(post) {
  return botIdentity(post) === 'stela'
}

function isAttachmentEntryImage(entry) {
  const type = normalizeToken(entry?.type)
  const mime = normalizeToken(entry?.mime)
  const url = String(entry?.url || entry?.src || entry?.href || '')

  if (type === 'image') return true
  if (mime.startsWith('image/')) return true
  return /\.(png|jpe?g|gif|webp|avif)$/i.test(url)
}

function attachmentEntryUrl(entry) {
  const raw = entry?.url || entry?.src || entry?.href
  return absoluteUrl(raw)
}

function stelaPreviewImageSrc(post) {
  if (!isStelaPost(post)) return ''

  if (Array.isArray(post?.attachments)) {
    const imageEntry = post.attachments.find(
      (entry) => isAttachmentEntryImage(entry) && attachmentEntryUrl(entry),
    )
    if (imageEntry) return attachmentEntryUrl(imageEntry)
  }

  if (post?.attachment_url && isImage(post)) {
    return attachmentSrc(post)
  }

  return ''
}

function postGifUrl(post) {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = absoluteUrl(gif.original_url)
  if (original) return original

  return absoluteUrl(gif.preview_url)
}

function postGifTitle(post) {
  const title = String(post?.meta?.gif?.title || '').trim()
  return title || 'GIF'
}

function attachedEventForPost(post) {
  const event = post?.attached_event
  if (event && typeof event === 'object') {
    return event
  }

  const fallbackId = Number(post?.meta?.event?.event_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) {
    return null
  }

  return {
    id: fallbackId,
    title: `Udalost #${fallbackId}`,
    start_at: null,
    end_at: null,
  }
}

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function parseEventDate(value) {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatEventRange(startAt, endAt) {
  const start = parseEventDate(startAt)
  const end = parseEventDate(endAt)

  if (!start && !end) return 'Datum upresnime'
  if (start && !end) return start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })
  if (!start && end) return end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })

  const startLabel = start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  const endLabel = end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  return startLabel === endLabel ? startLabel : `${startLabel} - ${endLabel}`
}

function menuItemsForPost(post) {
  const items = []

  if (showBotTranslationToggle(post)) {
    items.push({
      key: 'variant_translated',
      label: isBotVariantActive(post, 'translated') ? 'Jazyk: SK (aktivne)' : 'Jazyk: SK',
      danger: false,
    })
    items.push({
      key: 'variant_original',
      label: isBotVariantActive(post, 'original') ? 'Jazyk: EN (aktivne)' : 'Jazyk: EN',
      danger: false,
    })
  }

  if (hasOriginalDownload(post)) {
    items.push({ key: 'download_original', label: 'Stiahnut v plnej kvalite', danger: false })
  }

  if (canReport(post)) {
    items.push({ key: 'report', label: 'Nahlasit', danger: false })
  }

  if (canDelete(post)) {
    items.push({ key: 'delete', label: 'Zmazat', danger: true })
  }

  if (canEditTranslatedVariant(post)) {
    items.push({ key: 'edit', label: 'Upravit', danger: false })
  }

  if (auth.user?.is_admin && !isBotPost(post)) {
    items.push({
      key: 'pin',
      label: post?.pinned_at ? 'Odopnut' : 'Pripnut',
      danger: false,
    })
  }

  return items
}

function onMenuAction(item, post) {
  if (!item?.key || !post?.id) return

  if (item.key === 'variant_translated') {
    setBotContentVariant(post, 'translated')
    return
  }

  if (item.key === 'variant_original') {
    setBotContentVariant(post, 'original')
    return
  }

  if (item.key === 'download_original') {
    downloadOriginalAttachment(post)
    return
  }

  if (item.key === 'report') {
    openReport(post)
    return
  }

  if (item.key === 'delete') {
    void confirmDelete(post)
    return
  }

  if (item.key === 'edit') {
    startInlineEdit(post)
    return
  }

  if (item.key === 'pin') {
    togglePin(post)
  }
}

function isEditingPost(post) {
  return Number(editingPostId.value) === Number(post?.id)
}

function startInlineEdit(post) {
  if (!post?.id || !canEditTranslatedVariant(post)) return

  editingPostId.value = Number(post.id)
  editContentDraft.value = String(post?.content || '')
}

function cancelInlineEdit() {
  editingPostId.value = null
  editContentDraft.value = ''
}

async function saveInlineEdit(post) {
  if (!post?.id || !isEditingPost(post) || editSavingId.value) return
  if (!canEditTranslatedVariant(post)) return

  const currentContent = String(post?.content || '')
  const trimmed = editContentDraft.value.trim()
  if (!trimmed || trimmed === currentContent) {
    cancelInlineEdit()
    return
  }

  try {
    editSavingId.value = post.id
    let res = null
    try {
      await auth.csrf()
      res = await api.patch(
        `/posts/${post.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    } catch (e) {
      const status = Number(e?.response?.status || 0)
      if (status !== 401 && status !== 419) throw e
      await auth.fetchUser({ source: 'inline-post-edit', retry: false, markBootstrap: true })
      await auth.csrf()
      res = await api.patch(
        `/posts/${post.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    }

    const updated = res?.data
    if (updated && typeof updated === 'object') {
      Object.assign(post, updated)
    }

    post.content = trimmed
    if (post?.meta && typeof post.meta === 'object') {
      const nextMeta = { ...post.meta }
      nextMeta.translated_content = trimmed
      nextMeta.used_translation = true
      post.meta = nextMeta
    }
    cancelInlineEdit()
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    const message =
      status === 401 || status === 419
        ? 'Relacia vyprsala. Prihlas sa znova.'
        : e?.response?.data?.message || 'Uprava prispevku zlyhala.'
    toastError(message)
  } finally {
    editSavingId.value = null
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
    currentFeed.value.err = ''
    toastSuccess('Dakujeme, nahlasenie sme prijali.')
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 409) currentFeed.value.err = 'Už si reportoval tento post.'
    else currentFeed.value.err = e?.response?.data?.message || 'Nahlasenie zlyhalo.'
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

function isBookmarkLoading(post) {
  return bookmarks.isLoading(post?.id)
}

async function toggleBookmark(post) {
  if (!post?.id || isBookmarkLoading(post)) return
  if (!auth.isAuthed) {
    currentFeed.value.err = 'Prihlas sa pre zalozky.'
    return
  }

  currentFeed.value.err = ''
  const prevBookmarked = !!post.is_bookmarked
  const prevBookmarkedAt = post.bookmarked_at || null
  const nextBookmarked = !prevBookmarked

  post.is_bookmarked = nextBookmarked
  post.bookmarked_at = nextBookmarked ? new Date().toISOString() : null
  bookmarks.setBookmarked(post.id, nextBookmarked)

  try {
    await auth.csrf()
    const state = await bookmarks.toggleBookmark(post.id, prevBookmarked)
    post.is_bookmarked = state
    post.bookmarked_at = state ? post.bookmarked_at || new Date().toISOString() : null
  } catch (e) {
    post.is_bookmarked = prevBookmarked
    post.bookmarked_at = prevBookmarkedAt
    bookmarks.setBookmarked(post.id, prevBookmarked)
    currentFeed.value.err = e?.response?.data?.message || 'Ulozenie zalozky zlyhalo.'
    toastError('Ulozenie zalozky zlyhalo.')
  }
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
    toastSuccess('Prispevok bol zmazany.')
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 403) currentFeed.value.err = 'Nemas opravnenie.'
    else currentFeed.value.err = e?.response?.data?.message || 'Mazanie zlyhalo.'
  } finally {
    deleteLoadingId.value = null
  }
}

function openShareModal(post) {
  if (!post?.id) return
  shareTarget.value = post
}

function closeShareModal() {
  shareTarget.value = null
}


function updatePostPoll(post, nextPoll) {
  if (!post || !nextPoll) return
  post.poll = nextPoll
}

function onPollLoginRequired() {
  currentFeed.value.err = 'Prihlas sa pre hlasovanie.'
}

async function confirmDelete(post) {
  if (!post?.id || !canDelete(post) || deleteLoadingId.value) return

  const approved = await confirm({
    title: 'Zmazat prispevok?',
    message: 'Tuto akciu uz nie je mozne vratit.',
    confirmText: 'Zmazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!approved) return
  await deletePost(post)
}

function fmt(iso) {
  return formatRelativeShort(iso)
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

function attachmentDownloadSrc(p) {
  const u = p?.attachment_download_url
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

function attachmentMime(p) {
  const mime = String(p?.attachment_mime || '')
    .trim()
    .toLowerCase()
  if (mime !== '') return mime

  const name = (p?.attachment_original_name || p?.attachment_url || '').toLowerCase()
  if (name.endsWith('.mp4') || name.endsWith('.m4v')) return 'video/mp4'
  if (name.endsWith('.webm')) return 'video/webm'
  if (name.endsWith('.mov')) return 'video/quicktime'
  return ''
}

function isVideo(p) {
  const mime = attachmentMime(p)
  if (mime.startsWith('video/')) return true

  const name = (p?.attachment_original_name || p?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.mp4') ||
    name.endsWith('.m4v') ||
    name.endsWith('.webm') ||
    name.endsWith('.mov')
  )
}

function isAttachmentPending(post) {
  return post?.attachment_moderation_status === 'pending' || post?.attachment_is_blurred === true
}

function isAttachmentBlocked(post) {
  return post?.attachment_moderation_status === 'blocked' || !!post?.attachment_hidden_at
}

function hasOriginalDownload(post) {
  return isImage(post) && Boolean(post?.attachment_download_url)
}

function downloadOriginalAttachment(post) {
  const url = attachmentDownloadSrc(post)
  if (!url) return

  toastInfo('Stahujem...')
  try {
    window.open(url, '_blank', 'noopener')
  } catch {
    toastError('Stiahnutie zlyhalo.')
  }
}

function normalizeFeedError(error) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
  const message = String(error?.message || '')

  if (status === 401) return 'Prihlas sa pre tuto akciu.'
  if (code === 'ECONNABORTED' || message.toLowerCase().includes('timeout')) {
    return 'Server neodpoveda. Skus to znova neskor.'
  }
  if (!status && (code === 'ERR_NETWORK' || message.toLowerCase().includes('network'))) {
    return 'Backend je nedostupny. Skontroluj, ci bezi API server.'
  }

  return error?.response?.data?.message || message || 'Nacitanie feedu zlyhalo.'
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
  persistActiveTab(tab)

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
        url = '/astro-feed?with=counts'
      } else if (tab === 'bookmarks') {
        url = '/me/bookmarks?with=counts'
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

    const res = await api.get(url, {
      signal: controller.signal,
      meta: { skipErrorToast: true },
    })
    const payload = res.data || {}
    const rows = payload.data || []
    avatarDebug('FeedList:load-response', {
      tab,
      url,
      count: rows.length,
      sampleUsers: rows.slice(0, 5).map((post) => ({
        postId: post?.id ?? null,
        userId: post?.user?.id ?? null,
        username: post?.user?.username ?? null,
        avatar_mode: post?.user?.avatar_mode ?? null,
        avatar_path: post?.user?.avatar_path ?? null,
        avatar_url: post?.user?.avatar_url ?? null,
      })),
    })
    bookmarks.hydrateFromPosts(rows)

    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    state.nextPageUrl = payload.next_page_url || null
    state.loaded = true
  } catch (e) {
    if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return
    state.err = normalizeFeedError(e)
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
    currentFeed.value.err = 'Akcia je dostupna len pre admina.'
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

    // Refresh feed to re-order.
    load(true)
    currentFeed.value.err = ''
    toastSuccess(wasPinned ? 'Prispevok bol odopnuty.' : 'Prispevok bol pripnuty.')
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlas sa.'
    else if (status === 403) currentFeed.value.err = 'Nemáš oprávnenie.'
    else currentFeed.value.err = e?.response?.data?.message || 'Zmena pripnutia zlyhala.'
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
  if (tabs.value.length < 2) {
    if (event.key !== 'Escape') return
    if (reportTarget.value) closeReport()
    return
  }

  if (event.ctrlKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
    event.preventDefault()
    const currentIndex = tabs.value.findIndex((tab) => tab.id === activeTab.value)
    if (currentIndex < 0) return

    const direction = event.key === 'ArrowRight' ? 1 : -1
    const nextIndex = (currentIndex + direction + tabs.value.length) % tabs.value.length
    switchTab(tabs.value[nextIndex].id)
    return
  }

  if (event.key !== 'Escape') return
  if (reportTarget.value) closeReport()
}

onMounted(() => {
  persistActiveTab(activeTab.value)
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
  max-width: 100%;
  margin: 0;
  padding: 0;
  width: 100%;
  min-width: 0;
  overflow-x: hidden;
  background: transparent;
}

/* Header */
.feed-header {
  position: relative;
  margin-bottom: 0;
  padding: 0;
}

.feed-title-section {
  margin-bottom: 20px;
}

.feed-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-surface);
  margin-bottom: 4px;
  font-family: inherit;
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

.feed-composer-slot {
  width: 100%;
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

.tab-button:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* Error Message */
.error-message {
  background: transparent;
  border: 0;
  color: inherit;
  padding: 0;
  margin-bottom: 16px;
  font-size: 0.9rem;
  display: block;
}

.error-message :deep(.inlineStatus) {
  margin: 0;
}

/* Loading Skeleton */
.skeleton-container {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0;
}

.skeleton-post {
  background: transparent;
  border-bottom: 1px solid var(--color-divider);
  border-radius: 0;
  padding: 0.46rem 0.54rem 0.54rem;
  overflow: hidden;
}

.skeleton-header {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.skeleton-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
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
  height: 150px;
  border-radius: var(--radius-md);
}

.skeleton-line {
  height: 12px;
  border-radius: 4px;
}

.skeleton-name {
  width: 120px;
}
.skeleton-time {
  width: 80px;
}
.skeleton-text:first-child {
  width: 100%;
}
.skeleton-text:last-child {
  width: 70%;
}

/* Modern Post Cards */
.feed-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0;
  min-width: 0;
  border-top: 1px solid var(--color-divider);
}

.post-card {
  background: #151d28;
  border: 0;
  border-bottom: 1px solid var(--color-divider);
  border-radius: 0;
  padding: 0.46rem 0.54rem 0.54rem;
  transition: background-color var(--motion-fast);
  cursor: pointer;
  position: relative;
  overflow: visible;
  width: 100%;
  min-width: 0;
}

.post-card:last-child {
  border-bottom: none;
}

.post-card:hover {
  background: #1b2532;
}

.post-card:active {
  background: var(--interactive-active);
}

.post-card--new {
  animation: newPostReveal 760ms cubic-bezier(0.2, 0.8, 0.2, 1);
  background: rgb(var(--color-accent-rgb) / 0.08);
}

@keyframes newPostReveal {
  0% {
    opacity: 0.35;
    transform: translateY(-10px) scale(0.985);
  }
  55% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  100% {
    background: #151d28;
  }
}

/* Pinned posts */
.post-card--pinned {
  background: rgb(var(--color-accent-rgb) / 0.08);
}

.post-card--pinned:hover {
  background: rgb(var(--color-accent-rgb) / 0.11);
}

/* AstroBot posts */
.post-card--astrobot {
  background: #151d28;
}

.post-card--astrobot:hover {
  background: #1b2532;
}

/* Post Layout */
.post-card {
  display: flex;
  gap: 0.36rem;
}

.post-avatar {
  flex-shrink: 0;
}

.post-content {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.2rem;
}

.embedded-observation-card {
  margin-top: 0.2rem;
  margin-bottom: 0.42rem;
}

/* Modern Avatar */
.avatar-button {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  border-radius: 50%;
  overflow: hidden;
  transition: transform var(--motion-fast);
}

.avatar-button:hover {
  transform: scale(1.05);
}

.avatar-button:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.avatar-image {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 1px solid var(--border-subtle);
}

.avatar-fallback {
  --default-avatar-size: 32px;
  display: block;
  border: 1px solid var(--border-subtle);
}

/* Modern Post Header */
.post-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.42rem;
  margin-bottom: 0;
}

.post-meta {
  flex: 1;
  min-width: 0;
}

.post-author {
  display: flex;
  align-items: center;
  gap: 0.26rem;
  flex-wrap: wrap;
  margin-bottom: 0.04rem;
  min-width: 0;
}

.author-name {
  background: transparent;
  border: 1px solid transparent;
  padding: 0.04rem 0.22rem;
  color: var(--color-text-primary);
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  border-radius: var(--radius-pill);
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.author-name:hover {
  border-color: var(--color-border);
  background: var(--interactive-hover);
}

.author-name:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.author-username {
  color: var(--color-text-secondary);
  font-size: 0.66rem;
  font-weight: 400;
}

.author-bot-badge {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-accent-rgb) / 0.46);
  background: rgb(var(--color-accent-rgb) / 0.16);
  color: var(--color-accent);
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.author-time {
  color: var(--color-text-secondary);
  font-size: 0.65rem;
  font-weight: 500;
}

.pinned-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.02em;
  background: rgb(var(--color-accent-rgb) / 0.14);
  color: var(--color-text-primary);
  border: 1px solid rgb(var(--color-accent-rgb) / 0.3);
}

.post-time {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  color: var(--text-secondary);
  font-size: 0.74rem;
  font-weight: 400;
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

.bot-meta-row {
  margin-top: 2px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.bot-source-label {
  display: inline-flex;
  align-items: center;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.01em;
  text-transform: uppercase;
  opacity: 0.9;
}

.action-button {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-button:hover {
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.action-button:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modern Post Content */
.post-text {
  margin-bottom: 0;
  color: var(--color-text-primary);
  white-space: pre-wrap;
  line-height: 1.34;
  font-size: 0.8rem;
  word-wrap: break-word;
  word-break: break-word;
  overflow-wrap: break-word;
  max-width: 56ch;
}

.inline-edit-textarea {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.52);
  color: var(--color-text-primary);
  padding: 0.48rem 0.56rem;
  resize: vertical;
  font: inherit;
}

.inline-edit-actions {
  margin-top: 0.36rem;
  display: flex;
  gap: 0.3rem;
}

.editActionBtn {
  min-height: var(--control-height-sm);
  padding-inline: 0.82rem;
  font-size: var(--font-size-xs);
}

.post-text a {
  color: var(--color-accent);
  text-decoration: none;
  font-weight: 500;
  border-bottom: 1px solid transparent;
  transition: border-color 0.2s ease;
}

.post-text a:hover {
  border-bottom-color: var(--color-accent);
}

.bot-translation-toggle {
  margin-bottom: 8px;
  display: inline-flex;
  border: 1px solid var(--border-subtle);
  border-radius: 999px;
  overflow: hidden;
}

.bot-toggle-btn {
  border: none;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 11px;
  font-weight: 600;
  min-width: 34px;
  padding: 3px 8px;
  cursor: pointer;
}

.bot-toggle-btn--active {
  background: rgb(var(--color-accent-rgb) / 0.16);
  color: var(--color-accent);
}

.bot-toggle-btn:hover {
  color: var(--color-text-primary);
}

.show-more-btn {
  margin-top: 3px;
  border: 1px solid var(--color-border);
  background: rgb(var(--bg-app-rgb) / 0.42);
  color: var(--color-text-primary);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 500;
  padding: 3px 9px;
  cursor: pointer;
}

.show-more-btn:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.45);
  color: var(--color-accent);
}

/* Source URL */
.source-url {
  margin-top: 0;
}

.source-url--bot {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.source-attribution {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 600;
}

.source-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border-radius: 0;
  background: transparent;
  border: 0;
  color: var(--accent-primary);
  text-decoration: none;
  font-size: 0.74rem;
  font-weight: 500;
  transition: all 0.2s ease;
  opacity: 0.95;
}

.source-link:hover {
  text-decoration: underline;
  opacity: 1;
}

/* Media */
.post-media {
  margin-top: 0.16rem;
}

.post-media--stela {
  margin-top: 0.2rem;
}

.post-media--gif {
  margin-top: 0.2rem;
}

.attached-event-card {
  margin-top: 0.28rem;
  border: 1px solid rgb(var(--primary-rgb) / 0.26);
  border-radius: var(--radius-md);
  background: rgb(var(--primary-rgb) / 0.1);
  padding: 0.36rem 0.44rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.46rem;
}

.attached-event-copy {
  min-width: 0;
}

.attached-event-title {
  margin: 0;
  color: var(--text-primary);
  font-size: 0.76rem;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.attached-event-date {
  margin: 0.12rem 0 0;
  color: var(--text-secondary);
  font-size: 0.66rem;
}

.attached-event-btn {
  min-height: var(--control-height-sm);
  padding-inline: 0.58rem;
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
}

.attached-event-btn:hover {
  color: var(--text-primary);
}

.gifEmbed {
  width: 100%;
  max-height: 280px;
  object-fit: cover;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
  display: block;
}

.media-removed {
  border: 1px dashed var(--border-default);
  border-radius: var(--radius-sm);
  padding: 14px;
  text-align: center;
  color: rgb(var(--text-secondary-rgb) / 0.9);
  font-size: 13px;
}

/* File Attachment */
.file-attachment {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0.48rem 0.54rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
  background: rgb(var(--bg-app-rgb) / 0.24);
  color: var(--text-primary);
  text-decoration: none;
  transition: all 0.2s ease;
}

.file-attachment:hover {
  border-color: rgb(var(--primary-rgb) / 0.3);
  background: rgb(var(--bg-app-rgb) / 0.34);
  transform: translateY(-1px);
}

.file-icon {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    rgb(var(--primary-rgb) / 0.15) 0%,
    rgb(var(--primary-rgb) / 0.08) 100%
  );
  border: 1px solid rgb(var(--primary-rgb) / 0.2);
  font-size: 16px;
}

.file-info {
  flex: 1;
  min-width: 0;
}

.file-title {
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 2px;
}

.file-name {
  color: var(--text-secondary);
  font-size: 11px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-arrow {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-secondary);
}

/* Modern Action Buttons */
.post-actions {
  margin-top: 0.3rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.2rem;
  border-top: 0;
  min-width: 0;
}

.post-actions-left,
.post-actions-right {
  display: flex;
  align-items: center;
  gap: 0.82rem;
}

.post-actions-right {
  margin-left: auto;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  padding: 0.24rem 0.44rem;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  border-radius: 999px;
  font-size: 0.77rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--motion-fast);
  min-height: var(--control-height-sm);
  min-width: var(--control-height-sm);
  text-decoration: none;
}

.action-btn:hover:not(:disabled) {
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
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
  color: var(--text-secondary);
}

.action-btn--report:hover:not(:disabled) {
  background: rgb(var(--danger-rgb) / 0.1);
  color: var(--danger);
}

.action-btn--reply {
  color: var(--text-secondary);
}

.action-btn--reply:hover:not(:disabled) {
  background: rgb(var(--primary-rgb) / 0.1);
  color: var(--accent-primary);
}

.action-btn--share {
  color: var(--text-secondary);
}

.action-btn--share:hover:not(:disabled) {
  background: rgb(var(--success-rgb) / 0.1);
  color: var(--success);
}

.action-btn--like {
  color: var(--text-secondary);
  position: relative;
}

.action-btn--like:hover:not(:disabled) {
  background: rgb(var(--danger-rgb) / 0.1);
  color: var(--danger);
}

.action-btn--like.action-btn--liked {
  color: var(--danger);
  font-weight: 600;
}

.action-btn--like.action-btn--bump {
  animation: likePop 220ms ease;
}

.action-btn--thread {
  color: var(--text-secondary);
}

.action-btn--thread:hover:not(:disabled) {
  background: rgb(var(--primary-rgb) / 0.1);
  color: var(--accent-primary);
}

.action-btn--bookmark {
  color: var(--text-secondary);
}

.action-btn--bookmark:hover:not(:disabled) {
  background: rgb(var(--warning-rgb) / 0.1);
  color: var(--warning);
}

.action-btn--bookmark.action-btn--bookmarked {
  color: var(--warning);
  font-weight: 600;
}

.post-actions-more {
  display: inline-flex;
  align-items: center;
}

.post-actions-more :deep(.dropdownRoot) {
  display: inline-flex;
  align-items: center;
}

.post-actions-more :deep(.dropdownTrigger) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: var(--control-height-sm);
  min-width: var(--control-height-sm);
  padding: 0.24rem 0.44rem;
  border-radius: 999px;
  color: var(--text-secondary);
  transition: all var(--motion-fast);
}

.post-actions-more :deep(.dropdownTrigger:hover:not(:disabled)) {
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.action-btn--delete {
  color: var(--danger);
}

.action-btn--delete:hover:not(:disabled) {
  background: rgb(var(--danger-rgb) / 0.1);
}

.action-count {
  font-size: 0.72rem;
  font-weight: 500;
  min-width: 16px;
  text-align: center;
}

.view-count {
  font-size: 0.72rem;
  color: var(--text-secondary);
  font-weight: 500;
  line-height: 1;
}

.action-text {
  font-size: 12px;
  font-weight: 500;
}

@keyframes likePop {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.15);
  }
  100% {
    transform: scale(1);
  }
}

/* Load More Button */
.load-more {
  display: flex;
  justify-content: center;
  padding: 0.64rem 0.52rem 0.82rem;
}

.load-more-btn {
  min-height: var(--control-height-sm);
  padding-inline: 14px;
  font-size: 12px;
  font-weight: 500;
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
  background: var(--bg-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: var(--space-5);
}

.report-content {
  background: var(--color-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-6);
  max-width: 400px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: var(--shadow-medium);
}

.report-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: 16px;
  font-family: inherit;
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
  color: var(--color-text-secondary);
}

.form-select,
.form-textarea {
  font-size: var(--font-size-md);
}

.form-select:focus-visible,
.form-textarea:focus-visible {
  outline: none;
  border-color: rgb(var(--color-accent-rgb) / 0.8);
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

.reportBtn {
  min-height: var(--control-height-sm);
  font-size: var(--font-size-sm);
}

.delete-copy {
  color: var(--text-secondary);
  margin: 0 0 0.9rem;
}

@media (max-width: 480px) {
  .feed-container {
    padding: 0;
  }

  .feed-header {
    margin-bottom: 0;
  }

  .post-card {
    padding: 0.42rem 0.48rem 0.48rem;
    gap: 0.32rem;
  }

  .post-header {
    align-items: center;
    gap: 0.4rem;
  }

  .avatar-image {
    width: 30px;
    height: 30px;
  }

  .avatar-fallback {
    --default-avatar-size: 30px;
  }

  .author-name {
    font-size: 0.78rem;
  }

  .author-username {
    font-size: 0.64rem;
  }

  .author-time {
    font-size: 0.62rem;
  }

  .post-text {
    font-size: 0.78rem;
    line-height: 1.32;
  }

  .post-time {
    font-size: 0.72rem;
  }

  .action-btn {
    padding: 0.24rem 0.34rem;
    font-size: 0.72rem;
    min-height: 32px;
    min-width: 32px;
  }

  .action-count {
    font-size: 0.68rem;
  }

  .report-content {
    padding: 16px;
    margin: 10px;
  }
}

@media (min-width: 481px) and (max-width: 768px) {
  .feed-container {
    padding: 0;
  }

  .post-card {
    padding: 0.44rem 0.52rem 0.52rem;
    gap: 0.34rem;
  }

  .post-header {
    gap: 10px;
  }

  .avatar-image {
    width: 32px;
    height: 32px;
  }

  .avatar-fallback {
    --default-avatar-size: 32px;
  }

  .author-name {
    font-size: 0.8rem;
  }

  .author-username {
    font-size: 0.66rem;
  }

  .author-time {
    font-size: 0.63rem;
  }

  .post-text {
    font-size: 0.8rem;
    line-height: 1.34;
  }

  .report-content {
    padding: 16px;
    margin: 16px;
  }
}

@media (min-width: 769px) {
  .feed-container {
    padding: 0;
  }

  .post-card {
    padding: 0.46rem 0.54rem 0.54rem;
    gap: 0.36rem;
  }
}
</style>
