<template>
  <div class="page">
    <header class="topbar">
      <button class="iconBtn" @click="goHome">&larr;</button>
      <div class="topmeta">
        <div class="topname">{{ displayName }}</div>
        <div class="topsmall">{{ auth.user ? `${stats.posts} postov` : `@${handle}` }}</div>
      </div>
    </header>

    <AsyncState
      v-if="!auth.initialized"
      mode="loading"
      title="Nacitavam profil"
      loading-style="skeleton"
      :skeleton-rows="4"
      compact
    />

    <template v-else>
      <AsyncState
        v-if="!auth.user"
        mode="empty"
        title="Profil je dostupny po prihlaseni"
        message="Prihlas sa a uvidis svoje prispevky, pozorovania, media a zalozky."
        action-label="Prihlasit sa"
        compact
        @action="goLogin"
      />

      <section v-if="auth.user" class="profileShell ui-profile-shell">
        <div class="cover" :class="{ uploading: coverUploading }">
          <img
            v-if="coverSrc && !coverLoadFailed"
            class="coverImg"
            :src="coverSrc"
            alt="cover"
            @error="onCoverImageError"
          />
          <div class="coverGlow"></div>
          <button
            v-if="auth.user"
            class="mediaBtn coverBtn"
            type="button"
            :disabled="coverUploading"
            @click="openPicker('cover')"
          >
            {{ coverUploading ? 'Nahravam...' : 'Zmenit titulnu fotku' }}
          </button>
          <input
            ref="coverInput"
            class="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            @change="onMediaChange('cover', $event)"
          />
        </div>

        <div class="profileHead">
          <div
            class="avatar avatarEditable"
            :class="{ uploading: avatarUploading, canEdit: !!auth.user }"
            @click="openAvatarEditor"
          >
            <UserAvatar
              class="avatarImg"
              :user="auth.user"
              :avatar-url="avatarSrc"
              :alt="`${displayName} avatar`"
            />
            <button
              v-if="auth.user"
              type="button"
              class="mediaBtn avatarBtn avatarEditTrigger"
              :disabled="avatarUploading || avatarRemoving"
              aria-label="Upravit profilovy avatar"
              @click.stop="openAvatarEditor"
            >
              <svg class="avatarBtnIcon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M9 4h6l1.2 1.8H19a3 3 0 0 1 3 3v7.2a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8.8a3 3 0 0 1 3-3h2.8z"
                />
                <circle cx="12" cy="12.4" r="3.3" />
              </svg>
            </button>
          </div>

          <div class="headActions">
            <button
              v-if="auth.user"
              class="ui-btn ui-btn--secondary"
              @click="goToProfileEdit"
            >
              Upravit profil
            </button>
            <button class="ui-btn ui-btn--ghost copyBtn" @click="copyProfileLink">{{ copyLabel }}</button>
          </div>
        </div>

        <InlineStatus
          v-if="mediaErr"
          variant="error"
          :message="mediaErr"
        />

        <div class="identity ui-profile-header ui-profile-identity">
          <div class="nameRow">
            <h1 class="name">{{ displayName }}</h1>
            <span v-if="auth.user?.is_admin" class="badge">Admin</span>
            <span
              v-if="auth.user?.is_bot || String(auth.user?.role || '').toLowerCase() === 'bot'"
              class="badge badgeBot"
            >
              BOT
            </span>
          </div>

          <div class="handle">@{{ handle }}</div>

          <p v-if="auth.user?.bio" class="bio">{{ auth.user.bio }}</p>
          <p v-else class="bio muted">Zatial bez popisu.</p>
        </div>

        <div class="statsRow ui-profile-stats" aria-label="Profilove statistiky">
          <div class="stat ui-profile-stat">
            <strong>{{ stats.posts }}</strong>
            <span>Prispevky</span>
          </div>
          <div class="stat ui-profile-stat">
            <strong>{{ tabState.observations.total || '--' }}</strong>
            <span>Pozorovania</span>
          </div>
          <div class="stat ui-profile-stat">
            <strong>{{ tabState.events.total || '--' }}</strong>
            <span>Sledovane udalosti</span>
          </div>
        </div>

      </section>

      <BaseModal
        v-if="auth.user"
        v-model:open="avatarModalOpen"
        title="Upravit profilovy avatar"
        test-id="profile-avatar-modal"
        close-test-id="close-profile-avatar-modal"
      >
        <template #description>
          <p class="avatarCardSub avatarModalSub">Vyber si fotku alebo personalizovany avatar.</p>
        </template>

        <div class="avatarEditorBody">
          <div class="avatarModeSwitch" role="tablist" aria-label="Rezim profiloveho avatara">
            <button
              type="button"
              class="modeBtn"
              :class="{ active: avatarDraft.mode === 'image' }"
              @click="setAvatarMode('image')"
            >
              Fotka
            </button>
            <button
              type="button"
              class="modeBtn"
              :class="{ active: avatarDraft.mode === 'generated' }"
              @click="setAvatarMode('generated')"
            >
              Avatar
            </button>
          </div>

          <div class="avatarPreviewWrap">
            <div class="avatar avatarPreviewAvatar">
              <UserAvatar
                class="avatarImg"
                :user="auth.user"
                :size="112"
                :avatar-url="avatarSrc"
                :mode="avatarDraft.mode"
                :prefer-image="avatarDraft.mode === 'image'"
                :color-index="avatarDraft.color"
                :icon-index="avatarDraft.icon"
                :seed="avatarDraft.seed"
                :alt="`${displayName} avatar`"
              />
            </div>
            <p class="avatarHint">Pri mode Fotka bez obrazka ostava fallback avatar.</p>
          </div>

          <InlineStatus v-if="avatarErr" variant="error" :message="avatarErr" class="avatarMsg" />

          <template v-if="avatarDraft.mode === 'image'">
            <div class="avatarImageActions">
              <button
                type="button"
                class="ui-btn ui-btn--secondary"
                :disabled="avatarUploading || avatarRemoving"
                @click="openPicker('avatar')"
              >
                {{ avatarUploading ? 'Nahravam...' : 'Nahrat fotku' }}
              </button>
              <button
                type="button"
                class="ui-btn ui-btn--ghost"
                :disabled="avatarUploading || avatarRemoving"
                @click="removeAvatarImage"
              >
                {{ avatarRemoving ? 'Odstranujem...' : 'Odstranit fotku' }}
              </button>
              <input
                ref="avatarInput"
                class="fileInput"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="onMediaChange('avatar', $event)"
              />
            </div>
            <p class="avatarHint">Odporucana velkost: aspon 512x512 px, JPG/PNG/WebP, max 3 MB.</p>
          </template>

          <template v-else>
            <div class="avatarPicker">
              <div class="avatarPickerLabel">Symbol</div>
              <div class="avatarIconGrid">
                <button
                  v-for="option in iconOptions"
                  :key="option.index"
                  type="button"
                  class="avatarChoice iconChoice"
                  :class="{ active: avatarResolved.iconIndex === option.index }"
                  @click="selectAvatarIcon(option.index)"
                >
                  <DefaultAvatar
                    class="choiceAvatar"
                    :size="40"
                    :color-index="avatarResolved.colorIndex"
                    :icon-index="option.index"
                  />
                  <span class="choiceLabel">{{ option.label }}</span>
                </button>
              </div>
            </div>

            <div class="avatarPicker">
              <div class="avatarPickerLabel">Farba</div>
              <div class="avatarColorGrid">
                <button
                  v-for="(color, index) in AVATAR_COLORS"
                  :key="color"
                  type="button"
                  class="avatarChoice colorChoice"
                  :class="{ active: avatarResolved.colorIndex === index }"
                  :style="{ '--avatar-choice-color': color }"
                  @click="selectAvatarColor(index)"
                >
                  <span class="colorSwatch" aria-hidden="true"></span>
                  <span class="choiceLabel">Farba {{ index + 1 }}</span>
                </button>
              </div>
            </div>

            <div class="avatarActionRow">
              <button type="button" class="ui-btn ui-btn--secondary" :disabled="avatarSaving" @click="randomizeAvatar">
                Nahodne
              </button>
              <button type="button" class="ui-btn ui-btn--ghost" :disabled="avatarSaving" @click="resetGeneratedAvatar">
                Reset
              </button>
            </div>
          </template>

          <div class="avatarActionRow avatarActionRowSave">
            <button type="button" class="ui-btn ui-btn--ghost" :disabled="avatarSaving" @click="avatarModalOpen = false">
              Zavriet
            </button>
            <button type="button" class="ui-btn ui-btn--primary" :disabled="avatarSaving" @click="saveAvatarPreferences">
              {{ avatarSaving ? 'Ukladam...' : 'Ulozit' }}
            </button>
          </div>
        </div>
      </BaseModal>

      <section v-if="pinnedPost" class="card pinCard">
        <div class="pinHeader">
          <div class="pinTitle">Pripnuty prispevok</div>
          <button class="ui-btn ui-btn--ghost" @click="clearPinned">Odopnut</button>
        </div>
        <div class="pinBody">
          <div class="pinContent">{{ pinnedPost.content }}</div>
          <div v-if="pinnedPost.attachment_url" class="attachment">
            <img
              v-if="isImage(pinnedPost)"
              class="attachmentImg"
              :src="pinnedPost.attachment_url"
              alt="attachment"
            />
            <a v-else class="attachmentFile" :href="pinnedPost.attachment_url" target="_blank" rel="noreferrer">
              {{ pinnedPost.attachment_original_name || 'Priloha' }}
            </a>
          </div>
        </div>
      </section>


      <section v-if="auth.user" class="feedShell">
        <div class="tabs">
          <button
            v-for="t in tabs"
            :key="t.key"
            class="tab"
            :class="{ active: activeTab === t.key }"
            @click="setActiveTab(t.key)"
          >
            {{ t.label }}
            <span v-if="t.key === 'observations' && tabState.observations.total !== null" class="tabCount">
              {{ tabState.observations.total }}
            </span>
          </button>
        </div>

        <InlineStatus v-if="actionMsg" variant="success" :message="actionMsg" />
        <InlineStatus v-if="actionErr" variant="error" :message="actionErr" />

        <InlineStatus
          v-if="tabState[activeTab].err"
          variant="error"
          :message="tabState[activeTab].err"
          action-label="Skusit znova"
          @action="loadTab(activeTab, true)"
        />

        <AsyncState
          v-if="shouldShowLoadingState"
          mode="loading"
          title="Nacitavam obsah"
          loading-style="skeleton"
          :skeleton-rows="4"
          compact
        />

        <AsyncState
          v-else-if="shouldShowEmptyState"
          mode="empty"
          :title="globalEmptyTitle"
          :message="globalEmptyMessage"
          compact
        />

          <div v-else-if="activeTab === 'observations'" class="observationsList">
            <ObservationCard
              v-for="item in tabState.observations.items"
              :key="item.id"
              :observation="item"
              :clickable="true"
              :show-author="false"
              @open="openObservation"
            />
          </div>

          <div v-else-if="activeTab === 'events'">
            <div class="eventSegments" role="tablist" aria-label="Filter udalosti">
              <button
                v-for="segment in eventSegments"
                :key="segment.key"
                type="button"
                class="eventSegment"
                :class="{ active: activeEventSegment === segment.key }"
                role="tab"
                :aria-selected="activeEventSegment === segment.key ? 'true' : 'false'"
                @click="activeEventSegment = segment.key"
              >
                <span>{{ segment.label }}</span>
                <span class="eventSegment__count">{{ eventSegmentCounts[segment.key] || 0 }}</span>
              </button>
            </div>

            <AsyncState
              v-if="activeEventItems.length === 0"
              mode="empty"
              :title="eventSegmentEmptyTitle"
              :message="eventSegmentEmptyMessage"
              compact
            />

            <div v-else class="eventGrid">
              <ProfileEventCard
                v-for="eventItem in activeEventItems"
                :key="eventItem.id"
                :event="eventItem"
                @open="openFollowedEvent"
              />
            </div>
          </div>

          <div v-else class="postList ui-stream">
            <article v-for="p in tabState[activeTab].items" :key="p.id" class="postItem ui-stream-item" :class="{ pinned: pinnedPost?.id === p.id }">
              <div class="avatar sm">
                <UserAvatar
                  class="avatarImg"
                  :user="auth.user"
                  :alt="`${displayName} avatar`"
                />
              </div>

              <div class="postBody">
                <div class="postMeta ui-stream-item__meta">
                  <div class="postName">{{ displayName }}</div>
                  <div class="dot">&middot;</div>
                  <div class="postTime">{{ formatPostTimestamp(p) }}</div>
                </div>

                <div v-if="p.parent && activeTab === 'replies'" class="replyContext">
                  Odpoved na: <span class="replyAuthor">@{{ parentHandle(p) }}</span>
                  <span class="replyText">{{ shorten(p.parent.content) }}</span>
                </div>

                <HashtagText class="postContent ui-stream-item__body" :content="p.content" />

                <div v-if="attachedEventForPost(p)" class="attachedEventCard">
                  <div class="attachedEventCopy">
                    <p class="attachedEventTitle">{{ attachedEventForPost(p).title || 'Udalost' }}</p>
                    <p class="attachedEventDate">
                      {{ formatEventRange(attachedEventForPost(p).start_at, attachedEventForPost(p).end_at) }}
                    </p>
                  </div>
                  <button type="button" class="ui-btn ui-btn--secondary" @click="openAttachedEvent(p)">
                    Otvorit udalost
                  </button>
                </div>

                <div v-if="postGifUrl(p)" class="attachment">
                  <img class="attachmentImg" :src="postGifUrl(p)" :alt="postGifTitle(p)" />
                </div>

                <div v-if="p.attachment_url" class="attachment">
                  <img
                    v-if="isImage(p)"
                    class="attachmentImg"
                    :src="p.attachment_url"
                    alt="attachment"
                  />
                  <a v-else class="attachmentFile" :href="p.attachment_url" target="_blank" rel="noreferrer">
                    {{ p.attachment_original_name || 'Priloha' }}
                  </a>
                </div>

                <div class="postActions ui-stream-item__actions" @click.stop>
                  <button
                    class="postActionIconBtn"
                    type="button"
                    title="Zobrazit vlakno"
                    aria-label="Zobrazit vlakno"
                    @click.stop="openPost(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 8.3 8.3 0 0 1-3.6-.8L3 21l1.8-5.8a8.3 8.3 0 0 1-.8-3.7A8.5 8.5 0 0 1 12.5 3h.5A8.5 8.5 0 0 1 21 11.5z" />
                    </svg>
                    <span class="postActionLabel">Zobrazit vlakno</span>
                  </button>
                  <button
                    class="postActionIconBtn"
                    :class="{ active: pinnedPost?.id === p.id }"
                    type="button"
                    :title="pinnedPost?.id === p.id ? 'Odopnut' : 'Pripnut'"
                    :aria-label="pinnedPost?.id === p.id ? 'Odopnut' : 'Pripnut'"
                    @click.stop="togglePin(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M14 3v5.2l4 3V13h-5v7l-2-1.9V13H6v-1.8l4-3V3z" />
                    </svg>
                    <span class="postActionLabel">{{ pinnedPost?.id === p.id ? 'Odopnut' : 'Pripnut' }}</span>
                  </button>
                  <button
                    class="postActionIconBtn danger"
                    type="button"
                    title="Vymazat"
                    aria-label="Vymazat"
                    :disabled="deleteLoadingId === p.id"
                    @click.stop="deletePost(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M4 7h16" />
                      <path d="M9 7V5h6v2" />
                      <path d="M7 7l1 12h8l1-12" />
                      <path d="M10 11v5M14 11v5" />
                    </svg>
                    <span class="postActionLabel">{{ deleteLoadingId === p.id ? 'Mazem...' : 'Vymazat' }}</span>
                  </button>
                </div>
              </div>
            </article>
          </div>

        <div class="loadMore">
          <button
            v-if="tabState[activeTab].next"
            class="ui-btn ui-btn--secondary"
            :disabled="tabState[activeTab].loading"
            @click="loadTab(activeTab, false)"
          >
            {{ tabState[activeTab].loading ? 'Nacitavam...' : 'Nacitat viac' }}
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventFollowsStore } from '@/stores/eventFollows'
import http from '@/services/api'
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import ProfileEventCard from '@/components/profile/ProfileEventCard.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from '@/components/HashtagText.vue'
import { listObservations } from '@/services/observations'
import { EVENT_TIMEZONE, formatEventDate, formatEventDateKey } from '@/utils/eventTime'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { normalizeAvatarUrl, resolveAvatarState } from '@/utils/avatar'
import { avatarDebug } from '@/utils/avatarDebug'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  hashAvatarString,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'

const router = useRouter()
const auth = useAuthStore()
const eventFollows = useEventFollowsStore()
const { confirm } = useConfirm()
const toast = useToast()
const PROFILE_MEDIA_TARGET_MAX_BYTES = 3072 * 1024
const PROFILE_MEDIA_UPLOAD_MAX_BYTES = 20480 * 1024

function logAvatarProfileState(scope, extra = {}) {
  avatarDebug(`ProfileView:${scope}`, {
    userId: auth.user?.id ?? null,
    username: auth.user?.username ?? null,
    persisted: auth.user
      ? {
          avatar_mode: auth.user?.avatar_mode ?? auth.user?.avatarMode ?? null,
          avatar_path: auth.user?.avatar_path ?? null,
          avatar_url: auth.user?.avatar_url ?? auth.user?.avatarUrl ?? null,
          avatar_color: auth.user?.avatar_color ?? auth.user?.avatarColor ?? null,
          avatar_icon: auth.user?.avatar_icon ?? auth.user?.avatarIcon ?? null,
          avatar_seed: auth.user?.avatar_seed ?? auth.user?.avatarSeed ?? null,
        }
      : null,
    draft: {
      mode: avatarDraft?.mode ?? null,
      color: avatarDraft?.color ?? null,
      icon: avatarDraft?.icon ?? null,
      seed: avatarDraft?.seed ?? null,
    },
    avatarSrc: avatarSrc?.value ?? null,
    ...extra,
  })
}

const tabs = [
  { key: 'posts', label: 'Prispevky', kind: 'roots' },
  { key: 'observations', label: 'Pozorovania', kind: 'observations' },
  { key: 'events', label: 'Udalosti', kind: 'events' },
  { key: 'bookmarks', label: 'Zalozky', kind: 'bookmarks' },
  { key: 'media', label: 'Media', kind: 'media' },
  { key: 'likes', label: 'Paci sa', kind: 'likes' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')
const eventSegments = [
  { key: 'planned', label: 'Planovane' },
  { key: 'following', label: 'Sledujes' },
]
const activeEventSegment = ref('planned')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  events: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  bookmarks: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  likes: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})
const plannedEventItems = computed(() => (
  tabState.events.items.filter((item) => hasEventPlanData(item))
))
const followingEventItems = computed(() => (
  tabState.events.items.filter((item) => !hasEventPlanData(item))
))
const activeEventItems = computed(() => (
  activeEventSegment.value === 'planned' ? plannedEventItems.value : followingEventItems.value
))
const eventSegmentCounts = computed(() => ({
  planned: plannedEventItems.value.length,
  following: followingEventItems.value.length,
}))
const activeTabItems = computed(() => {
  if (activeTab.value === 'events') {
    return tabState.events.items
  }

  const state = tabState[activeTab.value]
  return Array.isArray(state?.items) ? state.items : []
})
const shouldShowLoadingState = computed(() => (
  Boolean(tabState[activeTab.value]?.loading) && activeTabItems.value.length === 0
))
const shouldShowEmptyState = computed(() => (
  !tabState[activeTab.value]?.loading && activeTabItems.value.length === 0
))
const globalEmptyTitle = computed(() => (
  activeTab.value === 'events'
    ? 'Zatial nesledujes ziadne udalosti'
    : activeTab.value === 'observations'
      ? 'Zatial ziadne pozorovania'
      : 'Zatial ziadny obsah'
))
const globalEmptyMessage = computed(() => (
  activeTab.value === 'events'
    ? 'Sleduj udalost a zobrazime ju tu.'
    : activeTab.value === 'observations'
      ? 'Pridaj prve pozorovanie a zobrazime ho tu.'
      : 'Tento feed je momentalne prazdny.'
))
const eventSegmentEmptyTitle = computed(() => (
  activeEventSegment.value === 'planned'
    ? 'Zatial nemas planovane udalosti'
    : 'Zatial nemas udalosti v sledovani'
))
const eventSegmentEmptyMessage = computed(() => (
  activeEventSegment.value === 'planned'
    ? 'Pridaj k udalosti poznamku, pripomienku alebo cas a zobrazime ju medzi planovanymi.'
    : 'Sleduj udalost a zobrazime ju v segmente Sledujes.'
))

const copyLabel = ref('Kopirovat link')
const actionMsg = ref('')
const actionErr = ref('')
const deleteLoadingId = ref(null)
const mediaErr = ref('')
const avatarUploading = ref(false)
const avatarRemoving = ref(false)
const avatarSaving = ref(false)
const avatarErr = ref('')
const avatarModalOpen = ref(false)
const coverUploading = ref(false)
const coverLoadFailed = ref(false)
const avatarPreview = ref('')
const coverPreview = ref('')
const avatarInput = ref(null)
const coverInput = ref(null)
const avatarSnapshot = ref({
  mode: 'image',
  color: null,
  icon: null,
  seed: '',
})
const avatarDraft = reactive({
  mode: 'image',
  color: null,
  icon: null,
  seed: '',
})

const pinnedPost = ref(null)

const displayName = computed(() => {
  const name = toNonEmptyText(auth.user?.name)
  if (name && !looksLikeEmail(name)) return name

  const username = toNonEmptyText(auth.user?.username)
  return username || 'Profil'
})
const handle = computed(() => {
  const username = toNonEmptyText(auth.user?.username)
  if (username) return safeHandle(username)

  const name = toNonEmptyText(auth.user?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)

  return 'user'
})

const avatarSrc = computed(() =>
  avatarPreview.value || normalizeAvatarUrl(auth.user?.avatar_url || auth.user?.avatarUrl || '')
)
const coverSrc = computed(() =>
  coverPreview.value || normalizeAvatarUrl(auth.user?.cover_url || auth.user?.coverUrl || '')
)
const avatarResolved = computed(() =>
  resolveAvatarState(auth.user, {
    avatarUrl: avatarSrc.value,
    mode: avatarDraft.mode,
    colorIndex: avatarDraft.color,
    iconIndex: avatarDraft.icon,
    seed: avatarDraft.seed,
  }),
)
const iconOptions = computed(() =>
  AVATAR_ICONS.map((iconKey, index) => ({
    key: iconKey,
    index,
    label: formatIconLabel(iconKey),
  })),
)
const pinKey = computed(() => {
  const username = auth.user?.username || 'me'
  return `pinned_post_${username}`
})

function normalizeAvatarIndex(value, max) {
  const index = coerceAvatarIndex(value, max)
  return index === null ? null : index
}

function buildAvatarSnapshot(user) {
  const imageUrl = normalizeAvatarUrl(user?.avatar_url || user?.avatarUrl || '')

  return {
    mode: imageUrl ? 'image' : normalizeAvatarMode(user?.avatar_mode || user?.avatarMode),
    color: normalizeAvatarIndex(user?.avatar_color ?? user?.avatarColor, AVATAR_COLORS.length - 1),
    icon: normalizeAvatarIndex(user?.avatar_icon ?? user?.avatarIcon, AVATAR_ICONS.length - 1),
    seed: String(user?.avatar_seed || user?.avatarSeed || '').trim(),
  }
}

function applyAvatarSnapshot(snapshot) {
  avatarDraft.mode = snapshot.mode
  avatarDraft.color = snapshot.color
  avatarDraft.icon = snapshot.icon
  avatarDraft.seed = snapshot.seed
}

function syncAvatarDraftFromUser() {
  const snapshot = buildAvatarSnapshot(auth.user)
  avatarSnapshot.value = snapshot
  applyAvatarSnapshot(snapshot)
}

function formatIconLabel(iconKey) {
  const map = {
    planet: 'Planeta',
    star: 'Hviezda',
    comet: 'Kometa',
    constellation: 'Suhvezdie',
    moon: 'Mesiac',
  }
  return map[iconKey] || iconKey
}

function openAvatarEditor() {
  if (!auth.user) return
  syncAvatarDraftFromUser()
  avatarErr.value = ''
  logAvatarProfileState('open-editor')
  avatarModalOpen.value = true
}

function setAvatarMode(mode) {
  avatarDraft.mode = mode === 'generated' ? 'generated' : 'image'
  avatarErr.value = ''
  logAvatarProfileState('set-mode', { nextMode: avatarDraft.mode })
}

function selectAvatarColor(index) {
  avatarDraft.color = normalizeAvatarIndex(index, AVATAR_COLORS.length - 1)
  avatarErr.value = ''
}

function selectAvatarIcon(index) {
  avatarDraft.icon = normalizeAvatarIndex(index, AVATAR_ICONS.length - 1)
  avatarErr.value = ''
}

function resetGeneratedAvatar() {
  avatarDraft.color = null
  avatarDraft.icon = null
  avatarDraft.seed = ''
  avatarErr.value = ''
}

function buildRandomAvatarSeed() {
  const base = `${auth.user?.id || 'user'}:${Date.now()}:${Math.random()}`
  return `rnd-${hashAvatarString(base).toString(36)}`
}

async function randomizeAvatar() {
  if (!auth.user || avatarSaving.value) return

  const seed = buildRandomAvatarSeed()
  avatarDraft.seed = seed
  avatarDraft.color = pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)
  avatarDraft.icon = pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)

  await saveAvatarPreferences('Nahodny avatar ulozeny.')
}

async function saveAvatarPreferences(successMessage = 'Avatar ulozeny.') {
  if (!auth.user || avatarSaving.value) return

  avatarErr.value = ''
  avatarSaving.value = true
  const previousSnapshot = { ...avatarSnapshot.value }
  logAvatarProfileState('save-preferences:start')

  try {
    await auth.csrf()

    const payload = {
      avatar_mode: avatarDraft.mode,
      avatar_color: avatarDraft.color,
      avatar_icon: avatarDraft.icon,
      avatar_seed: avatarDraft.seed || null,
    }

    const { data } = await http.patch('/me/avatar', payload)
    avatarDebug('ProfileView:save-preferences:response', {
      payload,
      response: data,
    })

    auth.user = {
      ...auth.user,
      ...data,
      activity: auth.user?.activity || null,
    }

    syncAvatarDraftFromUser()
    logAvatarProfileState('save-preferences:success')
    toast.success(successMessage)
  } catch (e) {
    avatarDebug('ProfileView:save-preferences:error', {
      status: e?.response?.status ?? null,
      response: e?.response?.data ?? null,
      message: e?.message ?? null,
    })
    applyAvatarSnapshot(previousSnapshot)
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 422 && data?.errors) {
      avatarErr.value =
        extractFirstError(data.errors, 'avatar_mode') ||
        extractFirstError(data.errors, 'avatar_color') ||
        extractFirstError(data.errors, 'avatar_icon') ||
        extractFirstError(data.errors, 'avatar_seed') ||
        'Skontroluj nastavenia avatara.'
    } else if (status === 401) {
      avatarErr.value = 'Prihlas sa.'
    } else {
      avatarErr.value = data?.message || 'Ukladanie avatara zlyhalo.'
    }

    toast.error('Avatar sa nepodarilo ulozit.')
  } finally {
    avatarSaving.value = false
  }
}

async function removeAvatarImage() {
  if (!auth.user || avatarRemoving.value || avatarUploading.value) return

  const approved = await confirm({
    title: 'Odstranit profilovu fotku',
    message: 'Naozaj chces odstranit profilovu fotku?',
    confirmText: 'Odstranit',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!approved) return

  avatarErr.value = ''
  mediaErr.value = ''
  avatarRemoving.value = true
  logAvatarProfileState('remove-image:start')

  try {
    await auth.csrf()
    const { data } = await http.delete('/me/avatar-image')
    avatarDebug('ProfileView:remove-image:response', { response: data })

    if (avatarPreview.value) {
      URL.revokeObjectURL(avatarPreview.value)
      avatarPreview.value = ''
    }

    if (data && typeof data === 'object') {
      auth.user = {
        ...auth.user,
        ...data,
        activity: auth.user?.activity || null,
      }
    }

    syncAvatarDraftFromUser()
    logAvatarProfileState('remove-image:success')
    toast.success('Profilova fotka bola odstranena.')
  } catch (e) {
    avatarDebug('ProfileView:remove-image:error', {
      status: e?.response?.status ?? null,
      response: e?.response?.data ?? null,
      message: e?.message ?? null,
    })
    const status = e?.response?.status
    const data = e?.response?.data
    if (status === 401) {
      avatarErr.value = 'Prihlas sa.'
    } else {
      avatarErr.value = data?.message || 'Odstranenie fotky zlyhalo.'
    }
  } finally {
    avatarRemoving.value = false
  }
}

function goHome() {
  router.push({ name: 'home' })
}

function goLogin() {
  router.push({ name: 'login', query: { redirect: '/profile' } })
}

function goToProfileEdit() {
  router.push({ name: 'profile.edit' })
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function setActiveTab(key) {
  activeTab.value = key
}

function fmt(iso) {
  return formatDateTimeCompact(iso)
}

function formatPostTimestamp(post) {
  if (activeTab.value === 'bookmarks') {
    return fmt(post?.bookmarked_at || post?.created_at)
  }
  return fmt(post?.created_at)
}

function shorten(text) {
  if (!text) return ''
  const clean = String(text).trim()
  return clean.length > 80 ? clean.slice(0, 77) + '...' : clean
}

function isImage(post) {
  const mime = post?.attachment_mime || ''
  return mime.startsWith('image/')
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
  if (event && typeof event === 'object') return event

  const fallbackId = Number(post?.meta?.event?.event_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) return null

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

function openFollowedEvent(event) {
  const eventId = Number(event?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function openObservation(observation) {
  const observationId = Number(observation?.id || 0)
  if (!Number.isInteger(observationId) || observationId <= 0) return
  router.push(`/observations/${observationId}`)
}

function mergeUniqueById(existingItems, incomingItems) {
  const seen = new Set()
  const merged = []

  const append = (item) => {
    const id = Number(item?.id || 0)
    if (!Number.isInteger(id) || id <= 0) {
      merged.push(item)
      return
    }
    if (seen.has(id)) return
    seen.add(id)
    merged.push(item)
  }

  ;(Array.isArray(existingItems) ? existingItems : []).forEach(append)
  ;(Array.isArray(incomingItems) ? incomingItems : []).forEach(append)

  return merged
}

function resetObservationTabState() {
  tabState.observations.items = []
  tabState.observations.next = null
  tabState.observations.err = ''
  tabState.observations.loaded = false
  tabState.observations.loading = false
  tabState.observations.total = null
}

function hasEventPlanData(item) {
  const plan = item?.plan && typeof item.plan === 'object' ? item.plan : null
  if (!plan) return false
  if (plan.has_data === true) return true

  return [
    toNonEmptyText(plan.personal_note),
    toNonEmptyText(plan.reminder_at),
    toNonEmptyText(plan.planned_time),
    toNonEmptyText(plan.planned_location_label),
  ].some((value) => value !== null)
}

function toNonEmptyText(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function formatEventRange(startAt, endAt) {
  const startLabel = formatShortEventDate(startAt, true)
  const endLabel = formatShortEventDate(endAt, true)

  if (!startLabel && !endLabel) return 'Datum upresnime'
  if (startLabel && !endLabel) return startLabel
  if (!startLabel && endLabel) return endLabel

  const sameDay = formatEventDateKey(startAt, EVENT_TIMEZONE) === formatEventDateKey(endAt, EVENT_TIMEZONE)
  return sameDay ? startLabel : `${startLabel} - ${endLabel}`
}

function formatShortEventDate(value, includeYear = false) {
  if (!value) return ''

  const label = formatEventDate(value, EVENT_TIMEZONE, {
    day: '2-digit',
    month: 'short',
    ...(includeYear ? { year: 'numeric' } : {}),
  })

  return label === '-' ? '' : label
}

function parentHandle(post) {
  const parentUser = post?.parent?.user
  const username = toNonEmptyText(parentUser?.username)
  if (username) return safeHandle(username)

  const name = toNonEmptyText(parentUser?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)

  return 'user'
}

function safeHandle(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function looksLikeEmail(value) {
  if (typeof value !== 'string') return false
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function extractFirstError(errorsObj, field) {
  const v = errorsObj?.[field]
  return Array.isArray(v) && v.length ? String(v[0]) : ''
}

function openPicker(type) {
  const input = type === 'avatar' ? avatarInput.value : coverInput.value
  if (input && !avatarUploading.value && !avatarRemoving.value && !coverUploading.value) {
    input.click()
  }
}

function setPreview(type, file) {
  const url = URL.createObjectURL(file)
  if (type === 'avatar') {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
    avatarPreview.value = url
  } else {
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
    coverPreview.value = url
  }
}

function clearPreview(type) {
  if (type === 'avatar') {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
    avatarPreview.value = ''
    return
  }

  if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
  coverPreview.value = ''
}

async function uploadMedia(type, file) {
  if (!auth.user) {
    mediaErr.value = 'Prihlas sa.'
    return
  }

  mediaErr.value = ''
  avatarErr.value = ''
  if (type === 'avatar') avatarUploading.value = true
  else coverUploading.value = true
  logAvatarProfileState('upload-media:start', {
    type,
    fileName: file?.name ?? null,
    fileSize: file?.size ?? null,
    fileType: file?.type ?? null,
  })

  try {
    await auth.csrf()

    const form = new FormData()
    form.append('file', file)
    let response = null

    if (type === 'avatar') {
      response = await http.post('/me/avatar-image', form)
    } else {
      form.append('type', type)
      response = await http.post('/profile/media', form)
    }
    avatarDebug('ProfileView:upload-media:response', {
      type,
      response: response?.data ?? null,
    })

    clearPreview(type)

    const nextUser = response?.data
    if (nextUser && typeof nextUser === 'object') {
      auth.user = {
        ...auth.user,
        ...nextUser,
        activity: auth.user?.activity || null,
      }
    }

    syncAvatarDraftFromUser()
    logAvatarProfileState('upload-media:success', { type })

    if (type === 'avatar') {
      toast.success('Profilova fotka bola ulozena.')
    }
  } catch (e) {
    avatarDebug('ProfileView:upload-media:error', {
      type,
      status: e?.response?.status ?? null,
      response: e?.response?.data ?? null,
      message: e?.message ?? null,
    })
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 401) {
      mediaErr.value = 'Prihlas sa.'
    } else if (status === 422 && data?.errors) {
      mediaErr.value =
        extractFirstError(data.errors, 'file') ||
        extractFirstError(data.errors, 'type') ||
        'Skontroluj subor.'
    } else {
      mediaErr.value = data?.message || 'Upload zlyhal.'
    }

    if (type === 'avatar') {
      avatarErr.value = mediaErr.value
    }

    // Never keep local preview after failed upload; show only persisted server state.
    clearPreview(type)
  } finally {
    if (type === 'avatar') avatarUploading.value = false
    else coverUploading.value = false
  }
}

async function onMediaChange(type, event) {
  const selectedFile = event?.target?.files?.[0]
  if (!selectedFile) return
  event.target.value = ''

  mediaErr.value = ''
  if (type === 'avatar') {
    avatarErr.value = ''
  }

  let uploadFile = selectedFile

  try {
    uploadFile = await compressImageFileToMaxBytes(selectedFile, {
      maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
    })
  } catch {
    // Fallback to original file when browser-side compression is unavailable.
    uploadFile = selectedFile
  }

  if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
    mediaErr.value = 'Subor je prilis velky. Maximalna velkost je 20 MB.'
    if (type === 'avatar') {
      avatarErr.value = mediaErr.value
    }
    return
  }

  setPreview(type, uploadFile)
  uploadMedia(type, uploadFile)
}

function onCoverImageError() {
  coverLoadFailed.value = true
}

async function copyProfileLink() {
  const url = `${window.location.origin}/profile`
  try {
    await navigator.clipboard.writeText(url)
    copyLabel.value = 'Skopirovane'
  } catch {
    copyLabel.value = 'Nepodarilo sa kopirovat'
  }
  setTimeout(() => {
    copyLabel.value = 'Kopirovat link'
  }, 1500)
}

function loadPinned() {
  try {
    const raw = localStorage.getItem(pinKey.value)
    pinnedPost.value = raw ? JSON.parse(raw) : null
  } catch {
    pinnedPost.value = null
  }
}

function savePinned(post) {
  pinnedPost.value = post
  localStorage.setItem(pinKey.value, JSON.stringify(post))
}

function clearPinned() {
  pinnedPost.value = null
  localStorage.removeItem(pinKey.value)
}

function togglePin(post) {
  if (!post?.id) return
  if (pinnedPost.value?.id === post.id) {
    clearPinned()
  } else {
    savePinned(post)
  }
}

async function deletePost(post) {
  if (!post?.id || deleteLoadingId.value) return
  const ok = await confirm({
    title: 'Vymazat prispevok',
    message: 'Naozaj chces vymazat tento prispevok?',
    confirmText: 'Vymazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!ok) return

  actionMsg.value = ''
  actionErr.value = ''
  deleteLoadingId.value = post.id

  try {
    await auth.csrf()
    await http.delete(`/posts/${post.id}`)

    for (const key of Object.keys(tabState)) {
      tabState[key].items = tabState[key].items.filter((x) => x.id !== post.id)
      if (typeof tabState[key].total === 'string' && tabState[key].total !== '--') {
        const n = Number(tabState[key].total)
        tabState[key].total = Number.isFinite(n) && n > 0 ? String(n - 1) : tabState[key].total
      }
    }

    if (pinnedPost.value?.id === post.id) {
      clearPinned()
    }

    actionMsg.value = 'Prispevok bol vymazany.'
    await loadCounts()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) actionErr.value = 'Prihlas sa.'
    else if (status === 403) actionErr.value = 'Nemas opravnenie.'
    else actionErr.value = e?.response?.data?.message || 'Mazanie zlyhalo.'
  } finally {
    deleteLoadingId.value = null
  }
}

async function loadCounts() {
  if (!auth.user) return

  const kinds = [
    { key: 'posts', kind: 'roots' },
    { key: 'replies', kind: 'replies' },
    { key: 'media', kind: 'media' },
  ]

  for (const k of kinds) {
    try {
      const { data } = await http.get('/posts', {
        params: { scope: 'me', kind: k.kind, per_page: 1 },
      })

      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      stats[k.key] = String(total)
      if (tabState[k.key]) {
        tabState[k.key].total = String(total)
      }
    } catch (e) {
      if (e?.response?.status === 401) {
        stats[k.key] = '--'
        if (tabState[k.key]) {
          tabState[k.key].total = '--'
        }
      } else {
        stats[k.key] = '--'
        if (tabState[k.key]) {
          tabState[k.key].total = '--'
        }
      }
    }
  }

  try {
    const { data } = await listObservations({
      mine: 1,
      page: 1,
      per_page: 1,
    })
    const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
    tabState.observations.total = String(total)
  } catch {
    tabState.observations.total = '--'
  }
}

async function loadTab(key, reset = true) {
  const tab = tabs.find((t) => t.key === key)
  const state = tabState[key]
  if (!tab || !state) return

  if (!auth.user) {
    state.err = 'Prihlas sa.'
    return
  }

  if (state.loading) return
  state.loading = true
  state.err = ''

  try {
    if (tab.kind === 'observations') {
      const page = reset ? 1 : Number(state.next || 0)
      if (!page) return

      const { data } = await listObservations({
        mine: 1,
        page,
        per_page: 10,
      })

      const rows = Array.isArray(data?.data) ? data.data : []
      state.items = reset
        ? mergeUniqueById([], rows)
        : mergeUniqueById(state.items, rows)

      const currentPage = Number(data?.current_page || page)
      const lastPage = Number(data?.last_page || currentPage)
      state.next = currentPage < lastPage ? currentPage + 1 : null
      state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
      state.loaded = true
      return
    }

    if (tab.kind === 'likes') {
      state.items = []
      state.next = null
      state.total = '0'
      state.loaded = true
      return
    }

    const url = reset
      ? tab.kind === 'bookmarks'
        ? '/me/bookmarks'
        : tab.kind === 'events'
          ? '/me/followed-events'
          : '/posts'
      : state.next
    if (!url) return

    const { data } = await http.get(url, {
      params:
        reset
          ? tab.kind === 'bookmarks'
            ? { per_page: 10 }
            : tab.kind === 'events'
              ? { per_page: 10 }
            : { scope: 'me', kind: tab.kind, per_page: 10 }
          : undefined,
    })

    const rows = data?.data ?? []
    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    if (tab.kind === 'events') {
      eventFollows.hydrateFromEvents(rows)
    }

    state.next = data?.next_page_url ?? null
    state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
    state.loaded = true
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) state.err = 'Prihlas sa.'
    else state.err = e?.response?.data?.message || 'Nacitanie zlyhalo.'
  } finally {
    state.loading = false
  }
}

watch(
  () => activeTab.value,
  (key) => {
    if (auth.user && !tabState[key].loaded) {
      loadTab(key, true)
    }
  }
)

watch(
  () => eventFollows.revision,
  () => {
    if (!auth.user || activeTab.value !== 'events' || !tabState.events.loaded) return
    tabState.events.loaded = false
    loadTab('events', true)
  }
)

watch(
  () => auth.user?.id,
  (nextUserId, prevUserId) => {
    if (nextUserId === prevUserId) return
    resetObservationTabState()
  }
)

watch(
  () => [
    auth.user?.id,
    auth.user?.avatar_mode,
    auth.user?.avatar_color,
    auth.user?.avatar_icon,
    auth.user?.avatar_seed,
    auth.user?.avatar_url,
    auth.user?.avatar_path,
  ],
  () => {
    if (!auth.user) return
    syncAvatarDraftFromUser()
    logAvatarProfileState('watch:user-avatar-fields')
  },
  { immediate: true },
)

watch(
  () => coverSrc.value,
  () => {
    coverLoadFailed.value = false
  },
  { immediate: true },
)

watch(
  () => avatarModalOpen.value,
  (isOpen, wasOpen) => {
    if (!isOpen && wasOpen) {
      syncAvatarDraftFromUser()
      avatarErr.value = ''
    }
  },
)

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()

  if (auth.user) {
    syncAvatarDraftFromUser()
    logAvatarProfileState('mounted-with-user')
    loadPinned()
    await loadCounts()
    await loadTab(activeTab.value, true)
  }
})

onBeforeUnmount(() => {
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
  if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
})
</script>

<style scoped>
.page {
  width: 100%;
  margin: 0 auto;
  padding: 0 0 var(--space-6);
  max-width: var(--content-max-width);
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(var(--bg-app-rgb) / 0.94);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--divider-color);
  padding: 0.45rem 0.86rem;
  display: flex;
  gap: var(--space-3);
  align-items: center;
}

.iconBtn {
  width: var(--control-height-lg);
  height: var(--control-height-lg);
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-default);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  transition: background-color var(--motion-fast), border-color var(--motion-fast), transform var(--motion-fast), box-shadow var(--motion-fast);
}
.iconBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.4);
  background: var(--interactive-hover);
  transform: translateY(-1px);
}
.iconBtn:active { transform: translateY(1px); }
.iconBtn:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 850; color: var(--text-primary); font-size: 1.05rem; }
.topsmall { color: var(--text-secondary); font-size: var(--font-size-xs); }

.profileShell {
  border: 1px solid var(--border-default);
  border-radius: var(--radius-xl);
  overflow: hidden;
  margin-top: var(--space-3);
  background: #151d28;
}

.cover {
  height: 154px;
  position: relative;
  background:
    radial-gradient(860px 200px at 18% 22%, rgb(var(--primary-rgb) / 0.24), transparent 60%),
    radial-gradient(680px 200px at 85% 30%, rgb(var(--primary-rgb) / 0.1), transparent 62%),
    linear-gradient(180deg, rgb(var(--bg-app-rgb) / 0.28), rgb(var(--bg-app-rgb) / 0.84));
  border-bottom: 1px solid var(--divider-color);
}
.coverImg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
}
.coverGlow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 20% 30%, rgb(var(--text-primary-rgb) / 0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgb(var(--text-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgb(var(--text-primary-rgb) / 0.2), transparent 60%);
  opacity: 0.6;
}

.profileHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding: 0 var(--space-4);
  transform: translateY(-28px);
}

.avatar {
  width: 92px;
  height: 92px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--bg-app-rgb) / 0.94);
  outline: 1px solid rgb(var(--primary-rgb) / 0.52);
  background: rgb(var(--primary-rgb) / 0.16);
  color: var(--text-primary);
  font-weight: 900;
  font-size: 1.25rem;
}
.avatarEditable {
  position: relative;
  overflow: hidden;
}
.avatarEditable.canEdit {
  cursor: pointer;
}
.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 999px;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
  border-width: 1px;
  outline: 1px solid rgb(var(--primary-rgb) / 0.35);
}

.fileInput {
  display: none;
}

.mediaBtn {
  position: absolute;
  border-radius: 999px;
  border: 1px solid var(--border-default);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  font-size: var(--font-size-xs);
  opacity: 0;
  transition: opacity var(--motion-fast), background-color var(--motion-fast), border-color var(--motion-fast), transform var(--motion-fast);
  z-index: 2;
}
.mediaBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.38);
  background: var(--interactive-hover);
  transform: translateY(-1px);
}
.mediaBtn:active { transform: translateY(1px); }
.mediaBtn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.coverBtn {
  right: 12px;
  bottom: 12px;
}
.avatarBtn {
  right: 4px;
  bottom: 4px;
  width: 30px;
  height: 30px;
  padding: 0;
  display: grid;
  place-items: center;
}
.avatarBtnIcon {
  width: 14px;
  height: 14px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
}
.cover:hover .mediaBtn,
.avatarEditable:hover .mediaBtn {
  opacity: 1;
}
@media (hover: none) {
  .mediaBtn {
    opacity: 1;
  }
}

.headActions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-left: auto;
}

.identity {
  padding: 0 var(--space-4) var(--space-4);
  margin-top: -6px;
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.9rem; font-weight: 900; color: var(--text-primary); line-height: 1.05; }
.badge {
  font-size: var(--font-size-xs);
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.42);
  background: rgb(var(--primary-rgb) / 0.14);
  color: var(--text-primary);
}

.badgeBot {
  border-color: rgb(var(--primary-rgb) / 0.5);
  background: rgb(var(--primary-rgb) / 0.2);
  color: var(--text-primary);
}
.handle { color: var(--text-secondary); margin-top: 0.15rem; }
.bio { margin: 0.55rem 0 0; color: var(--text-primary); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.6rem;
  color: var(--text-secondary);
  font-size: 0.84rem;
}
.metaItem { white-space: nowrap; }

.avatarCardTitle {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-primary);
}

.avatarCardSub {
  margin: 0.2rem 0 0;
  color: var(--text-secondary);
  font-size: 0.84rem;
}

.avatarCardCompact {
  padding: 0.65rem 0.72rem;
}

.avatarCardHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.65rem;
}

.avatarOpenBtn {
  min-height: 36px;
  padding: 0 0.9rem;
  font-size: 0.84rem;
}

.avatarCardMeta {
  margin-top: 0.65rem;
  padding: 0.52rem 0.62rem;
  border-radius: 0.9rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.35);
  display: flex;
  align-items: center;
  gap: 0.62rem;
}

.avatarCardPreview {
  flex: 0 0 auto;
}

.avatarCardInfo {
  min-width: 0;
  display: grid;
  gap: 0.28rem;
}

.avatarModePill {
  width: fit-content;
  max-width: 100%;
  padding: 0.18rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.35);
  background: rgb(var(--primary-rgb) / 0.14);
  color: var(--text-primary);
  font-size: 0.76rem;
  font-weight: 700;
}

.avatarCardHint {
  text-align: left;
  font-size: 0.78rem;
}

.avatarModalSub {
  margin: 0.15rem 0 0;
}

.avatarEditorBody {
  display: grid;
  gap: 0.8rem;
}

.avatarModeSwitch {
  margin-top: 0;
  padding: 0.2rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-surface-2);
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.25rem;
}

.modeBtn {
  min-height: 38px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--text-secondary);
  font-weight: 700;
}

.modeBtn.active {
  background: rgb(var(--primary-rgb) / 0.18);
  color: var(--text-primary);
}

.avatarPreviewWrap {
  margin-top: 0;
  display: grid;
  justify-items: center;
  gap: 0.4rem;
}

.avatarPreviewAvatar {
  width: 112px;
  height: 112px;
  margin: 0;
}

.avatarImageActions {
  margin-top: 0;
  display: flex;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.avatarHint {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.82rem;
  text-align: center;
}

.avatarMsg {
  margin-top: 0;
}

.avatarPicker {
  margin-top: 0;
}

.avatarPickerLabel {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-bottom: 0.45rem;
}

.avatarIconGrid,
.avatarColorGrid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.45rem;
}

.avatarColorGrid {
  grid-template-columns: repeat(6, minmax(0, 1fr));
}

.avatarChoice {
  border: 1px solid var(--border);
  border-radius: 0.85rem;
  padding: 0.38rem;
  background: rgb(var(--bg-app-rgb) / 0.35);
  display: grid;
  justify-items: center;
  gap: 0.28rem;
  color: var(--text-primary);
  transition: border-color 160ms ease, background-color 160ms ease;
}

.avatarChoice.active {
  border-color: rgb(var(--primary-rgb) / 0.8);
  background: rgb(var(--primary-rgb) / 0.16);
}

.choiceAvatar {
  width: 40px;
  height: 40px;
}

.choiceLabel {
  font-size: 0.7rem;
  color: var(--text-secondary);
  line-height: 1.2;
}

.colorChoice {
  min-height: 62px;
}

.colorSwatch {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--text-primary-rgb) / 0.25);
  background: var(--avatar-choice-color);
}

.avatarActionRow {
  margin-top: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.avatarActionRowSave {
  justify-content: flex-end;
}

.card {
  border: 1px solid var(--border-default);
  background: rgb(var(--bg-surface-rgb) / 0.88);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  margin-top: var(--space-3);
}

.infoTitle { font-weight: 900; color: var(--text-primary); }
.infoSub { color: var(--text-secondary); margin-top: 0.35rem; }

.editCard { margin-top: 0.7rem; }

.pinCard { margin-top: 0.7rem; }
.pinHeader { display: flex; justify-content: space-between; align-items: center; }
.pinTitle { font-weight: 900; color: var(--text-primary); }
.pinBody { margin-top: 0.5rem; }
.pinContent { color: var(--text-primary); white-space: pre-wrap; }

.form { margin-top: 0.75rem; display: grid; gap: 0.9rem; }

.field label {
  display: block;
  font-size: var(--font-size-sm);
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-default);
  background: rgb(var(--bg-app-rgb) / 0.44);
  color: var(--text-primary);
  outline: none;
}
.input:focus-visible {
  border-color: rgb(var(--primary-rgb) / 0.8);
  box-shadow: var(--focus-ring);
}
.textarea { resize: vertical; }

.hint {
  margin-top: 0.35rem;
  color: var(--text-secondary);
  font-size: 0.85rem;
  text-align: right;
}

.fieldErr {
  margin-top: 0.35rem;
  font-size: var(--font-size-sm);
  color: var(--danger);
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.25rem;
  justify-content: flex-end;
}

.feedShell {
  margin-top: 0.6rem;
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: 0;
}

.tabs {
  display: flex;
  gap: 0.15rem;
  position: sticky;
  top: calc(var(--app-header-h, 56px) + 4px);
  z-index: 8;
  background: rgb(var(--bg-app-rgb) / 0.9);
  backdrop-filter: blur(8px);
  padding: 0 0 0.25rem;
  border-bottom: 1px solid var(--divider-color);
  overflow-x: auto;
  scrollbar-width: none;
}

.tab {
  padding: 0.7rem 0.25rem 0.55rem;
  border-radius: 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 0.86rem;
  display: inline-flex;
  gap: 0.35rem;
  justify-content: center;
  align-items: center;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
}

.tabCount {
  font-size: 0.72rem;
  line-height: 1;
  padding: 0.12rem 0.42rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--text-secondary-rgb) / 0.3);
  color: var(--text-secondary);
}

.tab.active {
  border-bottom-color: var(--accent-primary);
  color: var(--text-primary);
}

.observationsList {
  display: grid;
  gap: 0.9rem;
  margin-top: 0.85rem;
}

.eventSegments {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  margin-top: 0.85rem;
  padding: 0.2rem;
  border-radius: 999px;
  border: 1px solid var(--border-default);
  background: rgb(var(--bg-surface-rgb) / 0.72);
}

.eventSegment {
  min-height: 2.05rem;
  border-radius: 999px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.77rem;
  font-weight: 650;
  padding: 0 0.72rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.eventSegment__count {
  font-size: 0.7rem;
  line-height: 1;
  padding: 0.14rem 0.36rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--text-secondary-rgb) / 0.34);
  color: inherit;
}

.eventSegment.active {
  border-color: rgb(var(--primary-rgb) / 0.32);
  background: rgb(var(--primary-rgb) / 0.16);
  color: var(--text-primary);
}

.eventSegment.active .eventSegment__count {
  border-color: rgb(var(--primary-rgb) / 0.44);
}

.eventGrid {
  display: grid;
  gap: 0.9rem;
  margin-top: 0.85rem;
}

.postList {
  margin-top: 0;
  display: grid;
}

.postItem {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: 0;
  transition: background-color var(--motion-fast), border-color var(--motion-fast);
}
.postItem:hover {
  background: var(--interactive-hover);
}
.postItem.pinned {
  background: rgb(var(--primary-rgb) / 0.08);
  border-color: rgb(var(--primary-rgb) / 0.26);
}

.postBody {
  min-width: 0;
  display: grid;
  gap: var(--space-2);
}

.postMeta {
  font-size: var(--font-size-sm);
}
.postName { color: var(--text-primary); font-weight: 950; }
.dot { opacity: 0.6; }
.postTime {
  color: var(--text-secondary);
  font-size: var(--font-size-xs);
}

.replyContext {
  margin-top: 0.2rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.75rem;
  background: rgb(var(--bg-app-rgb) / 0.58);
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.replyAuthor { color: var(--text-primary); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: var(--text-primary); margin-left: 0.25rem; }

.postContent {
  font-size: 0.95rem;
  white-space: pre-wrap;
  line-height: 1.5;
  max-width: 66ch;
  --hashtag-color: var(--color-accent);
  --hashtag-hover-color: var(--color-primary-hover);
}

.attachedEventCard {
  margin-top: 0.55rem;
  border: 1px solid rgb(var(--primary-rgb) / 0.34);
  background: rgb(var(--primary-rgb) / 0.09);
  border-radius: var(--radius-md);
  padding: 0.55rem 0.7rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
}

.attachedEventTitle {
  margin: 0;
  color: var(--text-primary);
  font-weight: 800;
}

.attachedEventDate {
  margin: 0.2rem 0 0;
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.attachment { margin-top: 0.6rem; }
.attachmentImg {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  text-decoration: none;
}

.postActions {
  gap: var(--space-1);
  margin-top: var(--space-1);
}

.postActionIconBtn {
  min-height: var(--control-height-sm);
  min-width: var(--control-height-sm);
  padding: 0.3rem 0.56rem;
  border-radius: 999px;
  border: 1px solid transparent;
  background: rgb(var(--bg-app-rgb) / 0.42);
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  transition: background-color var(--motion-fast), color var(--motion-fast), transform var(--motion-fast), border-color var(--motion-fast);
}
.postActionIconBtn svg {
  width: 15px;
  height: 15px;
  stroke: currentColor;
  fill: none;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.postActionLabel {
  font-size: 0.76rem;
  font-weight: 600;
  line-height: 1;
}
.postActionIconBtn:hover:not(:disabled) {
  background: var(--interactive-hover);
  border-color: var(--border-subtle);
  color: var(--text-primary);
}
.postActionIconBtn:active:not(:disabled) {
  transform: scale(0.96);
}
.postActionIconBtn:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}
.postActionIconBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.postActionIconBtn.active {
  color: var(--primary);
  border-color: rgb(var(--primary-rgb) / 0.34);
  background: rgb(var(--primary-rgb) / 0.12);
}
.postActionIconBtn.danger {
  color: rgb(var(--danger-rgb) / 0.9);
  border-color: rgb(var(--danger-rgb) / 0.26);
}
.postActionIconBtn.danger:hover:not(:disabled) {
  color: var(--danger);
  background: rgb(var(--danger-rgb) / 0.14);
  border-color: rgb(var(--danger-rgb) / 0.4);
}

.loadMore {
  display: flex;
  justify-content: center;
  padding-top: 0.75rem;
}

.muted { color: var(--text-secondary); }

@media (max-width: 767px) {
  .page {
    padding-bottom: 1.4rem;
  }

  .cover {
    height: 136px;
  }

  .avatar {
    width: 78px;
    height: 78px;
  }

  .avatarBtn {
    width: 28px;
    height: 28px;
  }

  .avatarCardHead {
    align-items: center;
  }

  .avatarOpenBtn {
    min-height: 34px;
    padding: 0 0.82rem;
  }

  .avatarCardMeta {
    align-items: flex-start;
  }

  .avatarIconGrid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .avatarColorGrid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .avatarActionRow .ui-btn {
    flex: 1 1 auto;
  }

  .tabs {
    top: calc(var(--app-header-h, 56px) + 2px);
  }

  .copyBtn {
    display: none;
  }
}

@media (min-width: 768px) {
  .topbar {
    padding-left: 0.65rem;
    padding-right: 0.65rem;
  }

  .eventGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .copyBtn {
    display: none;
  }
}
</style>


