<template>
  <div class="wrap">
    <div class="card">
      <!-- top -->
      <div class="top">
        <router-link class="back" to="/">&larr; Späť</router-link>
      </div>

      <!-- loading -->
      <AsyncState
        v-if="loading"
        mode="loading"
        title="Nacitavam prispevok..."
        loading-style="skeleton"
        :skeleton-rows="4"
        compact
      />

      <!-- error -->
      <div v-else-if="error" class="inlineErrorWrap">
        <InlineStatus
          variant="error"
          :message="error"
          action-label="Skusit znova"
          @action="loadPost"
        />
      </div>

      <!-- content -->
      <div v-else>
        <!-- ROOT POST -->
        <article class="postCard">
          <div class="postLeft">
            <button class="avatar profileLink" type="button" @click="openProfile(root?.user)">
              <UserAvatar class="avatarFallback" :user="root?.user" :alt="root?.user?.name || 'avatar'" />
            </button>
          </div>

          <div class="postMain">
            <div class="postHead">
              <div class="who">
                <div class="nameRow">
                  <button class="name linkBtn" type="button" @click="openProfile(root?.user)">
                    {{ root?.user?.name ?? 'Pouzivatel' }}
                  </button>
                  <span class="nameTime">{{ fmt(root?.created_at) }}</span>
                </div>
                <div class="meta">
                  <span v-if="isBotPost(root)" class="botVerifiedBadge">BOT</span>
                </div>
              </div>
            </div>

            <div class="postText">
              <template v-if="isEditingPost(root)">
                <textarea
                  v-model="editContentDraft"
                  class="inlineEditTextarea"
                  rows="6"
                  maxlength="5000"
                ></textarea>
                <div class="inlineEditActions">
                  <button
                    class="replyBtn"
                    type="button"
                    :disabled="editSavingId === root?.id"
                    @click="saveInlineEdit(root)"
                  >
                    {{ editSavingId === root?.id ? 'Ukladam...' : 'Ulozit' }}
                  </button>
                  <button
                    class="replyBtn"
                    type="button"
                    :disabled="editSavingId === root?.id"
                    @click="cancelInlineEdit"
                  >
                    Zrusit
                  </button>
                </div>
              </template>
              <HashtagText v-else :content="root?.content" />
            </div>

            <PollCard
              v-if="root?.poll"
              :poll="root.poll"
              :post-id="root.id"
              :is-authed="auth.isAuthed"
              @updated="updateRootPoll"
              @login-required="onPollLoginRequired"
            />

            <div v-if="attachedEventForPost(root)" class="attachedEventCard">
              <div class="attachedEventCopy">
                <p class="attachedEventTitle">{{ attachedEventForPost(root).title || 'Udalost' }}</p>
                <p class="attachedEventDate">
                  {{ formatEventRange(attachedEventForPost(root).start_at, attachedEventForPost(root).end_at) }}
                </p>
              </div>
              <button class="replyBtn" type="button" @click="openAttachedEvent(root)">
                Otvorit udalost
              </button>
            </div>

            <div v-if="postGifUrl(root)" class="mediaWrap">
              <img class="gifEmbed" :src="postGifUrl(root)" :alt="postGifTitle(root)" loading="lazy" />
            </div>

            <div v-if="root?.attachment_url" class="mediaWrap">
              <div v-if="isAttachmentBlocked(root)" class="removedMedia">Odstranene</div>
              <PostMediaImage
                v-else-if="isImage(root)"
                :src="attachmentSrc(root)"
                alt="Priloha prispevku"
                :blurred="isAttachmentPending(root)"
                pending-label="Kontrolujem..."
              />

              <a
                v-else-if="!isAttachmentPending(root)"
                class="fileCard"
                :href="attachmentSrc(root)"
                target="_blank"
                rel="noopener"
              >
                <div class="fileIcon">[file]</div>
                <div class="fileInfo">
                  <div class="fileTitle">Priloha</div>
                  <div class="fileName">
                    {{ root?.attachment_original_name || 'Subor' }}
                  </div>
                </div>
                <div class="fileArrow">-></div>
              </a>
            </div>

            <PostActionBar
              v-if="root"
              :item="root"
              :reply-count="Number(root?.replies_count ?? repliesCount)"
              :like-count="Number(root?.likes_count ?? 0)"
              :like-loading="isLikeLoading(root)"
              :bookmark-loading="isBookmarkLoading(root)"
              :like-bump="likeBumpId === root.id"
              :is-authed="auth.isAuthed"
              :menu-items="menuItemsForPost(root)"
              @reply="focusReplyComposer"
              @like="toggleLike(root)"
              @bookmark="toggleBookmark(root)"
              @share="openShareModal(root)"
              @menu-select="(item) => onMenuAction(item, root)"
            />
          </div>
        </article>

        <div class="composerWrap">
          <ReplyComposer
            ref="rootComposerRef"
            v-if="root?.id"
            :parent-id="root.id"
            @created="onReplyCreated"
          />
        </div>

        <!-- REPLIES -->
        <div id="replies" class="replies">
          <div class="repliesHead">
            <div class="repliesHeading">
              <div class="repliesTitle">Odpovede</div>
              <div class="repliesSub">
                {{ repliesCountLabel }}
              </div>
            </div>
          </div>

          <div v-if="replies.length === 0" class="repliesEmpty">
            <p class="repliesEmptyTitle">Zatial bez odpovedi.</p>
            <p class="repliesEmptyText">Bud prvy, kto odpovie na tento post.</p>
          </div>

          <div v-else class="replyList">
            <article
              v-for="r in replies"
              :key="r.id"
              class="replyCard"
              :class="{ replyCardNew: Number(highlightReplyId) === Number(r.id) }"
              :data-reply-id="r.id"
            >
              <div class="replyLeft">
                <button class="avatar avatarSm profileLink" type="button" @click="openProfile(r?.user)">
                  <UserAvatar class="avatarFallback" :user="r?.user" :alt="r?.user?.name || 'avatar'" />
                </button>
              </div>

              <div class="replyMain">
                <div class="replyHead">
                  <div class="replyHeadMain">
                    <div class="nameRow">
                      <button class="name linkBtn" type="button" @click="openProfile(r?.user)">
                        {{ r?.user?.name ?? 'Pouzivatel' }}
                      </button>
                      <span class="nameTime">{{ fmt(r?.created_at) }}</span>
                    </div>
                  </div>
                  <div class="postActionsMenu">
                    <DropdownMenu
                      v-if="menuItemsForPost(r).length"
                      :items="menuItemsForPost(r)"
                      label="Dalsie akcie"
                      menu-label="Akcie odpovede"
                      @select="(item) => onMenuAction(item, r)"
                    />
                  </div>
                </div>

                <div class="replyText">
                  <template v-if="isEditingPost(r)">
                    <textarea
                      v-model="editContentDraft"
                      class="inlineEditTextarea"
                      rows="5"
                      maxlength="5000"
                    ></textarea>
                    <div class="inlineEditActions">
                      <button
                        class="replyBtn"
                        type="button"
                        :disabled="editSavingId === r.id"
                        @click="saveInlineEdit(r)"
                      >
                        {{ editSavingId === r.id ? 'Ukladam...' : 'Ulozit' }}
                      </button>
                      <button
                        class="replyBtn"
                        type="button"
                        :disabled="editSavingId === r.id"
                        @click="cancelInlineEdit"
                      >
                        Zrusit
                      </button>
                    </div>
                  </template>
                  <HashtagText v-else :content="r.content" />
                </div>

                <div v-if="postGifUrl(r)" class="mediaWrapSm">
                  <img class="gifEmbed" :src="postGifUrl(r)" :alt="postGifTitle(r)" loading="lazy" />
                </div>

                <div v-if="r.attachment_url" class="mediaWrapSm">
                  <div v-if="isAttachmentBlocked(r)" class="removedMedia">Odstranene</div>
                  <PostMediaImage
                    v-else-if="isImage(r)"
                    :src="attachmentSrc(r)"
                    alt="Priloha prispevku"
                    :blurred="isAttachmentPending(r)"
                    pending-label="Kontrolujem..."
                  />

                  <a
                    v-else-if="!isAttachmentPending(r)"
                    class="fileCard fileCardSm"
                    :href="attachmentSrc(r)"
                    target="_blank"
                    rel="noopener"
                  >
                    <div class="fileIcon">[file]</div>
                    <div class="fileInfo">
                      <div class="fileTitle">Priloha</div>
                      <div class="fileName">
                        {{ r.attachment_original_name || 'Subor' }}
                      </div>
                    </div>
                    <div class="fileArrow">-></div>
                  </a>
                </div>

                <div class="replyActions">
                  <button
                    v-if="r.depth === 1"
                    class="replyBtn"
                    type="button"
                    @click="toggleReplyComposer(r.id)"
                  >
                    {{ activeReplyId === r.id ? 'Zavriet' : 'Odpovedat' }}
                  </button>
                </div>

                <div v-if="activeReplyId === r.id" class="composerWrapSm">
                  <ReplyComposer :parent-id="r.id" compact autofocus @created="onReplyCreated" />
                </div>

                <div v-if="r.replies && r.replies.length" class="replyChildren">
                  <article
                    v-for="c in r.replies"
                    :key="c.id"
                    class="replyCard replyCardChild"
                    :class="{ replyCardNew: Number(highlightReplyId) === Number(c.id) }"
                    :data-reply-id="c.id"
                  >
                    <div class="replyLeft">
                      <button class="avatar avatarSm profileLink" type="button" @click="openProfile(c?.user)">
                        <UserAvatar class="avatarFallback" :user="c?.user" :alt="c?.user?.name || 'avatar'" />
                      </button>
                    </div>

                    <div class="replyMain">
                      <div class="replyHead">
                        <div class="replyHeadMain">
                          <div class="nameRow">
                            <button class="name linkBtn" type="button" @click="openProfile(c?.user)">
                              {{ c?.user?.name ?? 'Pouzivatel' }}
                            </button>
                            <span class="nameTime">{{ fmt(c?.created_at) }}</span>
                          </div>
                        </div>
                        <div class="postActionsMenu">
                          <DropdownMenu
                            v-if="menuItemsForPost(c).length"
                            :items="menuItemsForPost(c)"
                            label="Dalsie akcie"
                            menu-label="Akcie odpovede"
                            @select="(item) => onMenuAction(item, c)"
                          />
                        </div>
                      </div>

                    <div class="replyText">
                      <template v-if="isEditingPost(c)">
                        <textarea
                          v-model="editContentDraft"
                          class="inlineEditTextarea"
                          rows="5"
                          maxlength="5000"
                        ></textarea>
                        <div class="inlineEditActions">
                          <button
                            class="replyBtn"
                            type="button"
                            :disabled="editSavingId === c.id"
                            @click="saveInlineEdit(c)"
                          >
                            {{ editSavingId === c.id ? 'Ukladam...' : 'Ulozit' }}
                          </button>
                          <button
                            class="replyBtn"
                            type="button"
                            :disabled="editSavingId === c.id"
                            @click="cancelInlineEdit"
                          >
                            Zrusit
                          </button>
                        </div>
                      </template>
                      <HashtagText v-else :content="c.content" />
                    </div>

                      <div v-if="postGifUrl(c)" class="mediaWrapSm">
                        <img class="gifEmbed" :src="postGifUrl(c)" :alt="postGifTitle(c)" loading="lazy" />
                      </div>

                      <div v-if="c.attachment_url" class="mediaWrapSm">
                        <div v-if="isAttachmentBlocked(c)" class="removedMedia">Odstranene</div>
                        <PostMediaImage
                          v-else-if="isImage(c)"
                          :src="attachmentSrc(c)"
                          alt="Priloha prispevku"
                          :blurred="isAttachmentPending(c)"
                          pending-label="Kontrolujem..."
                        />

                        <a
                          v-else-if="!isAttachmentPending(c)"
                          class="fileCard fileCardSm"
                          :href="attachmentSrc(c)"
                          target="_blank"
                          rel="noopener"
                        >
                          <div class="fileIcon">[file]</div>
                          <div class="fileInfo">
                            <div class="fileTitle">Priloha</div>
                            <div class="fileName">
                              {{ c.attachment_original_name || 'Subor' }}
                            </div>
                          </div>
                          <div class="fileArrow">-></div>
                        </a>
                      </div>
                    </div>
                  </article>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
      <div v-if="reportNotice" class="reportNotice">
        {{ reportNotice }}
      </div>
      <div v-if="reportTarget" class="reportBox">
        <div class="reportTitle">Nahlásiť príspevok</div>
        <div class="reportRow">
          <label>Dovod</label>
          <select v-model="reportReason">
            <option value="spam">Spam</option>
            <option value="abuse">Nevhodny obsah</option>
            <option value="misinfo">Dezinformacie</option>
            <option value="other">Ine</option>
          </select>
        </div>
        <div class="reportRow">
          <label>Sprava (volitelne)</label>
          <textarea v-model="reportMessage" rows="3" placeholder="Popis..." />
        </div>
        <div class="reportActions">
          <button class="replyBtn" type="button" @click="closeReport">Zrusit</button>
          <button class="replyBtn" type="button" @click="submitReport">Odoslat</button>
        </div>
      </div>
      <ShareModal :open="!!shareTarget" :post="shareTarget" @close="closeShareModal" />
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import HashtagText from '@/components/HashtagText.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import PollCard from '@/components/PollCard.vue'
import PostActionBar from '@/components/PostActionBar.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import ShareModal from '@/components/share/ShareModal.vue'
import api from '@/services/api'
import ReplyComposer from '@/components/ReplyComposer.vue'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookmarksStore } from '@/stores/bookmarks'
import { useToast } from '@/composables/useToast'
import { canReportPost } from '@/utils/postPermissions'
import { formatRelativeShort } from '@/utils/dateUtils'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const bookmarks = useBookmarksStore()
const { info: toastInfo, error: toastError } = useToast()

const post = ref(null)
const root = ref(null)
const replies = ref([])
const activeReplyId = ref(null)
const rootComposerRef = ref(null)
const highlightReplyId = ref(null)

const loading = ref(true)
const error = ref('')
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const reportNotice = ref('')
const editingPostId = ref(null)
const editContentDraft = ref('')
const editSavingId = ref(null)
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const shareTarget = ref(null)
const lastTrackedViewKey = ref('')
let viewAnimationFrame = null
let highlightReplyTimer = null

function openProfile(user) {
  const username = user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function fmt(iso) {
  return formatRelativeShort(iso)
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

function isAttachmentPending(item) {
  return item?.attachment_moderation_status === 'pending' || item?.attachment_is_blurred === true
}

function isAttachmentBlocked(item) {
  return item?.attachment_moderation_status === 'blocked' || !!item?.attachment_hidden_at
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

function postGifUrl(post) {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = normalizeAbsoluteUrl(gif.original_url)
  if (original) return original

  return normalizeAbsoluteUrl(gif.preview_url)
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

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function normalizeAbsoluteUrl(url) {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}

function clearReplyHighlightTimer() {
  if (highlightReplyTimer !== null) {
    window.clearTimeout(highlightReplyTimer)
    highlightReplyTimer = null
  }
}

function highlightReply(replyId) {
  highlightReplyId.value = replyId
  clearReplyHighlightTimer()
  highlightReplyTimer = window.setTimeout(() => {
    if (Number(highlightReplyId.value) === Number(replyId)) {
      highlightReplyId.value = null
    }
    highlightReplyTimer = null
  }, 2400)
}

function scrollReplyIntoView(replyId) {
  nextTick(() => {
    const node = document.querySelector(`[data-reply-id="${replyId}"]`)
    if (node instanceof HTMLElement) {
      node.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }
  })
}

function onReplyCreated(newReply) {
  if (!newReply?.id) return

  const rootId = root.value?.id
  if (!rootId) return

  if (Number(newReply.parent_id) === Number(rootId)) {
    const next = [...replies.value, { ...newReply, replies: [] }]
    next.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
    replies.value = next
  } else {
    const parent = replies.value.find((r) => Number(r.id) === Number(newReply.parent_id))
    if (parent) {
      const children = Array.isArray(parent.replies) ? [...parent.replies, newReply] : [newReply]
      children.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
      parent.replies = children
      replies.value = [...replies.value]
    }
  }

  // keep local counter (if exists)
  if (root.value && typeof root.value === 'object') {
    const curr = Number(root.value.replies_count ?? replies.value.length - 1)
    root.value.replies_count = Number.isFinite(curr) ? curr + 1 : replies.value.length
  }

  highlightReply(newReply.id)
  scrollReplyIntoView(newReply.id)
  activeReplyId.value = null
}

function toggleReplyComposer(id) {
  activeReplyId.value = activeReplyId.value === id ? null : id
}

function focusReplyComposer() {
  activeReplyId.value = null
  nextTick(() => {
    rootComposerRef.value?.focusInput?.()
  })
}

function isLikeLoading(item) {
  return likeLoadingIds.value.has(item?.id)
}

function setLikeLoading(id, on) {
  const next = new Set(likeLoadingIds.value)
  if (on) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function bumpLike(id) {
  likeBumpId.value = id
  window.setTimeout(() => {
    if (likeBumpId.value === id) likeBumpId.value = null
  }, 220)
}

function isBookmarkLoading(item) {
  return bookmarks.isLoading(item?.id)
}

async function toggleLike(item) {
  if (!item?.id || isLikeLoading(item)) return
  if (!auth.isAuthed) {
    reportNotice.value = 'Prihlas sa pre lajkovanie.'
    return
  }

  reportNotice.value = ''
  const prevLiked = !!item.liked_by_me
  const prevCount = Number(item.likes_count ?? 0) || 0

  item.liked_by_me = !prevLiked
  item.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(item.id)
  setLikeLoading(item.id, true)

  try {
    await auth.csrf()
    const res = prevLiked
      ? await api.delete(`/posts/${item.id}/like`)
      : await api.post(`/posts/${item.id}/like`)

    const data = res?.data
    if (data?.likes_count !== undefined) item.likes_count = data.likes_count
    if (data?.liked_by_me !== undefined) item.liked_by_me = data.liked_by_me
  } catch (e) {
    item.liked_by_me = prevLiked
    item.likes_count = prevCount
    reportNotice.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(item.id, false)
  }
}

async function toggleBookmark(item) {
  if (!item?.id || isBookmarkLoading(item)) return
  if (!auth.isAuthed) {
    reportNotice.value = 'Prihlas sa pre zalozky.'
    return
  }

  reportNotice.value = ''
  const prevBookmarked = !!item.is_bookmarked
  const prevBookmarkedAt = item.bookmarked_at || null
  const nextBookmarked = !prevBookmarked

  item.is_bookmarked = nextBookmarked
  item.bookmarked_at = nextBookmarked ? new Date().toISOString() : null
  bookmarks.setBookmarked(item.id, nextBookmarked)

  try {
    await auth.csrf()
    const state = await bookmarks.toggleBookmark(item.id, prevBookmarked)
    item.is_bookmarked = state
    item.bookmarked_at = state ? item.bookmarked_at || new Date().toISOString() : null
  } catch (e) {
    item.is_bookmarked = prevBookmarked
    item.bookmarked_at = prevBookmarkedAt
    bookmarks.setBookmarked(item.id, prevBookmarked)
    reportNotice.value = e?.response?.data?.message || 'Ulozenie zalozky zlyhalo.'
  }
}

function openShareModal(item) {
  if (!item?.id) return
  shareTarget.value = item
}

function closeShareModal() {
  shareTarget.value = null
}

function openReport(post) {
  if (!post?.id) return
  reportTarget.value = post
  reportNotice.value = ''
}

function normalizeToken(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
}

function isBotPost(post) {
  if (!post || typeof post !== 'object') return false

  const authorKind = normalizeToken(post?.author_kind)
  if (authorKind === 'bot') return true

  if (normalizeToken(post?.source_name) === 'astrobot') return true

  if (post?.user?.is_bot === true) return true
  if (normalizeToken(post?.user?.role) === 'bot') return true

  return false
}

function canAdminEditBotPost(post) {
  const isAdmin = Boolean(auth.user?.is_admin || auth.user?.role === 'admin')
  if (!isAdmin) return false

  if (!isBotPost(post)) return false

  const identity = normalizeToken(post?.bot_identity)
  return identity === 'kozmo' || identity === 'stela'
}

function menuItemsForPost(post) {
  const items = []

  if (hasOriginalDownload(post)) {
    items.push({ key: 'download_original', label: 'Stiahnut v plnej kvalite', danger: false })
  }

  if (canReportPost(post, auth.user)) {
    items.push({ key: 'report', label: 'Nahlasit', danger: false })
  }

  if (canAdminEditBotPost(post)) {
    items.push({ key: 'edit', label: 'Upravit', danger: false })
  }

  return items
}

function onMenuAction(item, post) {
  if (!item?.key || !post?.id) return
  if (item.key === 'download_original') {
    downloadOriginalAttachment(post)
    return
  }
  if (item.key === 'report') {
    openReport(post)
    return
  }
  if (item.key === 'edit') {
    startInlineEdit(post)
  }
}

function isEditingPost(post) {
  return Number(editingPostId.value) === Number(post?.id)
}

function startInlineEdit(post) {
  if (!post?.id || !canAdminEditBotPost(post)) return

  editingPostId.value = Number(post.id)
  editContentDraft.value = String(post?.content || '')
}

function cancelInlineEdit() {
  editingPostId.value = null
  editContentDraft.value = ''
}

async function saveInlineEdit(post) {
  if (!post?.id || !isEditingPost(post) || editSavingId.value) return
  if (!canAdminEditBotPost(post)) return

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
    reportNotice.value = message
    toastError(message)
  } finally {
    editSavingId.value = null
  }
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

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

function updateRootPoll(nextPoll) {
  if (!root.value || !nextPoll) return
  root.value.poll = nextPoll
}

function onPollLoginRequired() {
  reportNotice.value = 'Prihlas sa pre hlasovanie.'
}

function stopViewAnimation() {
  if (viewAnimationFrame !== null) {
    cancelAnimationFrame(viewAnimationFrame)
    viewAnimationFrame = null
  }
}

function animateRootViewsTo(targetViews) {
  if (!root.value) return

  const target = Number(targetViews)
  if (!Number.isFinite(target)) return

  const start = Number(root.value.views ?? 0)
  if (!Number.isFinite(start) || start === target) {
    root.value.views = target
    return
  }

  stopViewAnimation()

  const durationMs = 220
  const startedAt = performance.now()

  const tick = (now) => {
    if (!root.value) {
      stopViewAnimation()
      return
    }

    const progress = Math.min(1, (now - startedAt) / durationMs)
    const eased = 1 - Math.pow(1 - progress, 3)
    root.value.views = Math.round(start + (target - start) * eased)

    if (progress < 1) {
      viewAnimationFrame = requestAnimationFrame(tick)
      return
    }

    viewAnimationFrame = null
  }

  viewAnimationFrame = requestAnimationFrame(tick)
}

async function registerPostView(postId) {
  if (!postId) return

  const key = String(postId)
  if (lastTrackedViewKey.value === key) return
  lastTrackedViewKey.value = key

  try {
    const res = await api.post(`/posts/${postId}/view`)
    const nextViews = Number(res?.data?.views)
    if (Number.isFinite(nextViews) && root.value) {
      animateRootViewsTo(nextViews)
    }
  } catch {
    // Intentionally silent: view tracking must never block UI.
  }
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
    reportNotice.value = 'Dakujeme, nahlasenie sme prijali.'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) reportNotice.value = 'Prihlas sa.'
    else if (status === 409) reportNotice.value = 'Už si reportoval tento post.'
    else reportNotice.value = e?.response?.data?.message || 'Nahlasenie zlyhalo.'
  } finally {
    closeReport()
  }
}

async function loadPost() {
  loading.value = true
  error.value = ''
  post.value = null
  root.value = null
  replies.value = []
  activeReplyId.value = null
  highlightReplyId.value = null
  clearReplyHighlightTimer()

  try {
    const res = await api.get(`/posts/${route.params.id}`)
    const payload = res.data || {}

    post.value = payload.post ?? null
    root.value = payload.root ?? payload.post ?? null
    if (root.value?.id) {
      bookmarks.hydrateFromPosts([root.value])
    }

    if (Array.isArray(payload.replies) && payload.replies.length > 0) {
      replies.value = payload.replies
    } else {
      const thread = Array.isArray(payload.thread) ? payload.thread : []
      const rootId = root.value?.id
      const byParent = thread.reduce((acc, p) => {
        const key = p?.parent_id ?? null
        if (!acc[key]) acc[key] = []
        acc[key].push(p)
        return acc
      }, {})

      const rootReplies = (byParent[rootId] || []).map((p) => ({
        ...p,
        replies: (byParent[p.id] || []).slice(),
      }))

      replies.value = rootReplies
    }

    void registerPostView(root.value?.id ?? route.params.id)
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Prispevok sa nepodarilo nacitat.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPost()
})

onBeforeUnmount(() => {
  stopViewAnimation()
  clearReplyHighlightTimer()
})

watch(
  () => route.params.id,
  () => loadPost()
)

const repliesCount = computed(() => {
  return replies.value.reduce((acc, r) => {
    const childCount = Array.isArray(r.replies) ? r.replies.length : 0
    return acc + 1 + childCount
  }, 0)
})

const repliesCountLabel = computed(() => {
  const count = Number(repliesCount.value || 0)
  if (count === 1) return '1 odpoved'
  if (count >= 2 && count <= 4) return `${count} odpovede`
  return `${count} odpovedi`
})
</script>

<style scoped>
.wrap {
  display: grid;
  gap: 1rem;
  width: 100%;
  min-width: 0;
}

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  border-radius: 1.6rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
  padding: clamp(0.85rem, 1.8vw, 1.15rem);
  width: 100%;
  min-width: 0;
  overflow: clip;
}

/* top */
.top {
  margin-bottom: 0.75rem;
}

.inlineErrorWrap {
  margin-bottom: 0.8rem;
}

.back {
  display: inline-flex;
  padding: 0.5rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}
.back:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

/* post layout (same as feed) */
.postCard {
  display: grid;
  grid-template-columns: 52px 1fr;
  gap: 0.85rem;
  padding: 0.95rem;
  border-radius: 1.35rem;
  background: rgb(var(--color-bg-rgb) / 0.2);
  min-width: 0;
  align-items: start;
}

.postMain,
.replyMain,
.who {
  min-width: 0;
}

.avatar {
  width: 48px;
  height: 48px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  background: transparent;
  overflow: hidden;
  flex-shrink: 0;
}

.avatarImg,
.avatarFallback {
  width: 100%;
  height: 100%;
  border-radius: inherit;
  display: block;
}

.avatarImg {
  object-fit: cover;
}

.avatarFallback {
  --default-avatar-size: 100%;
}
.profileLink {
  border: 0;
  padding: 0;
  cursor: pointer;
}
.linkBtn {
  background: transparent;
  border: 0;
  padding: 0;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.linkBtn:hover {
  text-decoration: underline;
}

.postHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.postActionsMenu {
  flex-shrink: 0;
}

.name {
  color: var(--color-surface);
  font-weight: 950;
  max-width: 100%;
  overflow-wrap: anywhere;
}

.nameRow {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
}

.nameTime {
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  font-weight: 700;
  white-space: nowrap;
}

.meta {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
  overflow-wrap: anywhere;
}
.dot { opacity: 0.65; }

.postText {
  margin-top: 0.5rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.inlineEditTextarea {
  width: 100%;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  padding: 0.55rem 0.65rem;
  resize: vertical;
  font: inherit;
}

.inlineEditActions {
  margin-top: 0.45rem;
  display: flex;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.attachedEventCard {
  margin-top: 0.6rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 0.95rem;
  padding: 0.6rem 0.65rem;
  background: rgb(var(--color-primary-rgb) / 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.55rem;
}

.attachedEventCopy {
  min-width: 0;
}

.attachedEventTitle {
  margin: 0;
  color: var(--color-surface);
  font-weight: 900;
  font-size: 0.9rem;
  overflow-wrap: anywhere;
}

.attachedEventDate {
  margin: 0.2rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.76rem;
}

/* media */
.mediaWrap {
  margin-top: 0.75rem;
}

.gifEmbed {
  width: 100%;
  max-height: 420px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  display: block;
}

.removedMedia {
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 10px;
  padding: 10px;
  text-align: center;
  font-size: 12px;
  opacity: 0.85;
}

/* file */
.fileCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem;
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-surface);
  min-width: 0;
  overflow: hidden;
}
.fileIcon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgb(var(--color-primary-rgb) / 0.14);
}
.fileInfo {
  flex: 1;
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}
.fileTitle {
  font-weight: 900;
}
.fileName {
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fileArrow {
  font-weight: 900;
}

/* composer wrap */
.composerWrap {
  margin-top: 1.25rem;
}

/* Replies section */
.replies {
  margin-top: 1.55rem;
  padding-top: 0.15rem;
  border-top: 0;
}
.repliesHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  padding: 0.1rem 0.25rem 0.55rem;
}
.repliesHeading {
  display: grid;
  gap: 0.26rem;
  min-width: 0;
}
.repliesTitle {
  color: var(--color-surface);
  font-weight: 950;
  font-size: 1.05rem;
  letter-spacing: 0.01em;
}
.repliesSub {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.78rem;
  font-weight: 800;
  border-radius: 999px;
  padding: 0.18rem 0.55rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  width: fit-content;
}

.repliesEmpty {
  padding: 0.85rem;
  border-radius: 1rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.4);
  background: rgb(var(--color-bg-rgb) / 0.15);
  color: var(--color-text-secondary);
  display: grid;
  gap: 0.45rem;
  justify-items: start;
}
.repliesEmptyTitle,
.repliesEmptyText {
  margin: 0;
}
.repliesEmptyTitle {
  color: var(--color-surface);
  font-weight: 800;
}
.repliesEmptyText {
  font-size: 0.88rem;
}

.replyList {
  display: grid;
  gap: 1rem;
  padding: 0 0.15rem 0.35rem;
}
.replyCard {
  display: grid;
  grid-template-columns: 44px 1fr;
  gap: 0.78rem;
  padding: 0.85rem;
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background:
    radial-gradient(circle at 0% 0%, rgb(var(--color-primary-rgb) / 0.08), transparent 36%),
    rgb(var(--color-bg-rgb) / 0.16);
  min-width: 0;
  align-items: start;
  transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
}
.replyCard:hover {
  transform: translateY(-1px);
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background:
    radial-gradient(circle at 0% 0%, rgb(var(--color-primary-rgb) / 0.12), transparent 40%),
    rgb(var(--color-bg-rgb) / 0.22);
}
.replyCardNew {
  border-color: rgb(var(--color-primary-rgb) / 0.58);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.16);
  animation: replyFlash 1.3s ease;
}
.replyChildren {
  position: relative;
  display: grid;
  gap: 0.62rem;
  margin: 0.4rem 0 0.15rem clamp(0.9rem, 2.8vw, 1.65rem);
  padding-left: 0.65rem;
}
.replyChildren::before {
  content: '';
  position: absolute;
  left: 0.12rem;
  top: 0.15rem;
  bottom: 0.15rem;
  width: 2px;
  border-radius: 99px;
  background: linear-gradient(
    180deg,
    rgb(var(--color-primary-rgb) / 0.45),
    rgb(var(--color-text-secondary-rgb) / 0.16)
  );
}
.replyCardChild {
  background:
    linear-gradient(145deg, rgb(var(--color-bg-rgb) / 0.18), rgb(var(--color-bg-rgb) / 0.1));
  border-radius: 1rem;
}

@keyframes replyFlash {
  0% {
    box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0.35);
  }
  100% {
    box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0);
  }
}

.avatarSm {
  width: 40px;
  height: 40px;
  font-size: 0.9rem;
}

.replyHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.replyHeadMain {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}

.replyText {
  margin-top: 0.45rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.55;
  font-size: 0.98rem;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.mediaWrapSm {
  margin-top: 0.6rem;
}
.fileCardSm {
  padding: 0.7rem 0.75rem;
  border-radius: 1.05rem;
}

.replyActions {
  margin-top: 0.62rem;
  display: flex;
  gap: 0.45rem;
  flex-wrap: wrap;
}
.reportNotice {
  margin-top: 0.75rem;
  color: var(--color-text-secondary);
}
.reportBox {
  margin-top: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.35);
  display: grid;
  gap: 0.6rem;
}
.reportTitle {
  font-weight: 800;
}
.reportRow {
  display: grid;
  gap: 0.35rem;
}
.reportRow label {
  font-size: 0.8rem;
  opacity: 0.8;
}
.reportRow select,
.reportRow textarea {
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.7);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: inherit;
  padding: 0.5rem 0.65rem;
}
.reportActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  border: 1px solid rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
  background: rgb(var(--color-success-rgb) / 0.25);
}
.botLabel {
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  font-style: italic;
  opacity: 0.8;
}

.botVerifiedBadge {
  display: inline-flex;
  align-items: center;
  padding: 0.13rem 0.5rem;
  border-radius: 999px;
  font-size: 0.67rem;
  font-weight: 900;
  letter-spacing: 0.04em;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.48);
  color: var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.15);
}
.repliesDisabledNotice {
  margin: 1rem 0;
  padding: 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  text-align: center;
  color: var(--color-text-secondary);
  font-style: italic;
}
.replyBtn {
  padding: 0.45rem 0.72rem;
  border-radius: 0.78rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.32);
  white-space: nowrap;
  font-size: 0.82rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: border-color 0.16s ease, background 0.16s ease, transform 0.16s ease;
}
.replyBtn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.72);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.14);
  transform: translateY(-1px);
}
.composerWrapSm { margin-top: 0.6rem; }

/* No divider lines on post detail */
.card,
.back,
.avatar,
.inlineEditTextarea,
.attachedEventCard,
.gifEmbed,
.removedMedia,
.fileCard,
.repliesEmpty,
.replyCard,
.reportBox,
.reportRow select,
.reportRow textarea,
.badge,
.botVerifiedBadge,
.repliesDisabledNotice,
.replyBtn {
  border: 0 !important;
}

.replyBtn:hover {
  border-color: transparent;
}

:deep(.post-actions .action-btn),
:deep(.post-actions-more .dropdownTrigger) {
  border: 0 !important;
}

:deep(.post-actions .action-btn:hover:not(:disabled)),
:deep(.post-actions-more .dropdownTrigger:hover:not(:disabled)) {
  border-color: transparent !important;
}

@media (max-width: 768px) {
  .card {
    border-radius: 1.2rem;
  }

  .postCard,
  .replyCard {
    grid-template-columns: 42px 1fr;
    gap: 0.6rem;
    padding: 0.74rem;
    border-radius: 1rem;
  }

  .replyChildren {
    margin-left: 0.62rem;
    padding-left: 0.52rem;
  }

  .avatar,
  .avatarSm {
    width: 38px;
    height: 38px;
    font-size: 0.82rem;
  }

  .meta {
    font-size: 0.82rem;
    gap: 0.35rem;
  }

  .repliesHead {
    align-items: flex-start;
  }

  .reportActions {
    justify-content: flex-start;
  }
}

@media (max-width: 480px) {
  .card {
    padding: 0.72rem;
    border-radius: 1rem;
  }

  .replyCardChild {
    margin-left: 0.15rem;
  }

  .postText,
  .replyText {
    font-size: 0.92rem;
  }

  .fileCard,
  .fileCardSm {
    padding: 0.62rem;
  }

  .repliesHead {
    flex-direction: column;
    align-items: stretch;
  }

}
</style>
