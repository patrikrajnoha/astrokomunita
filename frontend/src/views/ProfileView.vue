<template>
  <div class="page">
    <header class="topbar">
      <button class="iconBtn" @click="goHome">&larr;</button>
      <div class="topmeta">
        <div class="topname">{{ displayName }}</div>
        <div class="topsmall">{{ auth.user ? `${stats.posts} postov` : `@${handle}` }}</div>
      </div>
    </header>

    <div v-if="!auth.initialized" class="card muted">Nacitavam profil...</div>

    <template v-else>
      <div v-if="!auth.user" class="card info">
        <div class="infoTitle">Profil je dostupny po prihlaseni.</div>
        <div class="infoSub">Prihlas sa a uvidis svoje prispevky, pozorovania, media a zalozky.</div>
        <button class="btn" @click="goLogin">Prihlasit sa</button>
      </div>

      <section class="profileShell">
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
          <div class="avatar avatarEditable" :class="{ uploading: avatarUploading }">
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
              @click="openAvatarEditor"
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
              class="btn outline"
              @click="goToProfileEdit"
            >
              Upraviť profil
            </button>
            <button class="btn ghost copyBtn" @click="copyProfileLink">{{ copyLabel }}</button>
          </div>
        </div>

        <div v-if="mediaErr" class="msg err">{{ mediaErr }}</div>

        <div class="identity">
          <div class="nameRow">
            <h1 class="name">{{ displayName }}</h1>
            <span v-if="auth.user?.is_admin" class="badge">Admin</span>
          </div>

          <div class="handle">@{{ handle }}</div>

          <p v-if="auth.user?.bio" class="bio">{{ auth.user.bio }}</p>
          <p v-else class="bio muted">Zatial bez popisu.</p>

          <div class="meta">
            <span class="metaItem">Lokalita: {{ canonicalLocationLabel || 'nenastavená' }}</span>
            <button
              v-if="auth.user"
              type="button"
              :class="canonicalLocationLabel ? 'metaActionBtn' : 'btn metaSetupBtn'"
              @click="goToLocationEditor"
            >
              {{ canonicalLocationLabel ? 'Upraviť polohu' : 'Nastaviť polohu' }}
            </button>
            <span v-if="auth.user?.email" class="metaItem">E-mail: {{ auth.user.email }}</span>
          </div>
        </div>

      </section>

      <section v-if="auth.user" class="card avatarCard avatarCardCompact">
        <div class="avatarCardHead">
          <div>
            <h2 class="avatarCardTitle">Profilovy avatar</h2>
            <p class="avatarCardSub">Fotka alebo generovany avatar.</p>
          </div>
          <button type="button" class="btn outline avatarOpenBtn" @click="openAvatarEditor">Upravit</button>
        </div>

        <div class="avatarCardMeta">
          <div class="avatar sm avatarCardPreview">
            <UserAvatar
              class="avatarImg"
              :user="auth.user"
              :avatar-url="avatarSrc"
              :alt="`${displayName} avatar`"
            />
          </div>

          <div class="avatarCardInfo">
            <div class="avatarModePill">{{ persistedAvatarMode === 'generated' ? 'Rezim Avatar' : 'Rezim Fotka' }}</div>
            <p class="avatarHint avatarCardHint">Bez fotky sa automaticky pouzije generovany fallback.</p>
          </div>
        </div>

        <div v-if="avatarErr" class="msg err avatarMsg">{{ avatarErr }}</div>
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

          <div v-if="avatarErr" class="msg err avatarMsg">{{ avatarErr }}</div>

          <template v-if="avatarDraft.mode === 'image'">
            <div class="avatarImageActions">
              <button
                type="button"
                class="btn outline"
                :disabled="avatarUploading || avatarRemoving"
                @click="openPicker('avatar')"
              >
                {{ avatarUploading ? 'Nahravam...' : 'Nahrat fotku' }}
              </button>
              <button
                type="button"
                class="btn ghost"
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
              <button type="button" class="btn outline" :disabled="avatarSaving" @click="randomizeAvatar">
                Nahodne
              </button>
              <button type="button" class="btn ghost" :disabled="avatarSaving" @click="resetGeneratedAvatar">
                Reset
              </button>
            </div>
          </template>

          <div class="avatarActionRow avatarActionRowSave">
            <button type="button" class="btn ghost" :disabled="avatarSaving" @click="avatarModalOpen = false">
              Zavriet
            </button>
            <button type="button" class="btn" :disabled="avatarSaving" @click="saveAvatarPreferences">
              {{ avatarSaving ? 'Ukladam...' : 'Ulozit' }}
            </button>
          </div>
        </div>
      </BaseModal>

      <section v-if="pinnedPost" class="card pinCard">
        <div class="pinHeader">
          <div class="pinTitle">Pripnutý príspevok</div>
          <button class="btn ghost" @click="clearPinned">Odopnúť</button>
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
              {{ pinnedPost.attachment_original_name || 'Príloha' }}
            </a>
          </div>
        </div>
      </section>


      <section class="feedShell">
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

        <div v-if="!auth.user" class="msg info">Prihlas sa.</div>

        <template v-else>
          <div v-if="actionMsg" class="msg ok">{{ actionMsg }}</div>
          <div v-if="actionErr" class="msg err">{{ actionErr }}</div>

          <div v-if="tabState[activeTab].err" class="msg err">{{ tabState[activeTab].err }}</div>

          <div v-if="tabState[activeTab].loading && tabState[activeTab].items.length === 0" class="muted padTop">
            Nacitavam...
          </div>

          <div v-else-if="!tabState[activeTab].loading && tabState[activeTab].items.length === 0" class="muted padTop">
            {{
              activeTab === 'events'
                ? 'Zatial nesledujes ziadne udalosti.'
                : activeTab === 'observations'
                  ? 'Zatial ziadne pozorovania.'
                  : 'Zatial ziadny obsah.'
            }}
          </div>

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

          <div v-else-if="activeTab === 'events'" class="eventGrid">
            <ProfileEventCard
              v-for="eventItem in tabState.events.items"
              :key="eventItem.id"
              :event="eventItem"
              @open="openFollowedEvent"
            />
          </div>

          <div v-else class="postList">
            <article v-for="p in tabState[activeTab].items" :key="p.id" class="postItem" :class="{ pinned: pinnedPost?.id === p.id }">
              <div class="avatar sm">
                <UserAvatar
                  class="avatarImg"
                  :user="auth.user"
                  :alt="`${displayName} avatar`"
                />
              </div>

              <div class="postBody">
                <div class="postMeta">
                  <div class="postName">{{ displayName }}</div>
                  <div class="dot">·</div>
                  <div class="postTime">{{ formatPostTimestamp(p) }}</div>
                </div>

                <div v-if="p.parent && activeTab === 'replies'" class="replyContext">
                  Odpoveď na: <span class="replyAuthor">@{{ parentHandle(p) }}</span>
                  <span class="replyText">{{ shorten(p.parent.content) }}</span>
                </div>

                <HashtagText class="postContent" :content="p.content" />

                <div v-if="attachedEventForPost(p)" class="attachedEventCard">
                  <div class="attachedEventCopy">
                    <p class="attachedEventTitle">{{ attachedEventForPost(p).title || 'Udalost' }}</p>
                    <p class="attachedEventDate">
                      {{ formatEventRange(attachedEventForPost(p).start_at, attachedEventForPost(p).end_at) }}
                    </p>
                  </div>
                  <button type="button" class="btn outline" @click="openAttachedEvent(p)">
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
                    {{ p.attachment_original_name || 'Príloha' }}
                  </a>
                </div>

                <div class="postActions" @click.stop>
                  <button
                    class="postActionIconBtn"
                    type="button"
                    title="Zobraziť vlákno"
                    aria-label="Zobraziť vlákno"
                    @click.stop="openPost(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 8.3 8.3 0 0 1-3.6-.8L3 21l1.8-5.8a8.3 8.3 0 0 1-.8-3.7A8.5 8.5 0 0 1 12.5 3h.5A8.5 8.5 0 0 1 21 11.5z" />
                    </svg>
                    <span class="postActionLabel">Zobraziť vlákno</span>
                  </button>
                  <button
                    class="postActionIconBtn"
                    :class="{ active: pinnedPost?.id === p.id }"
                    type="button"
                    :title="pinnedPost?.id === p.id ? 'Odopnúť' : 'Pripnúť'"
                    :aria-label="pinnedPost?.id === p.id ? 'Odopnúť' : 'Pripnúť'"
                    @click.stop="togglePin(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M14 3v5.2l4 3V13h-5v7l-2-1.9V13H6v-1.8l4-3V3z" />
                    </svg>
                    <span class="postActionLabel">{{ pinnedPost?.id === p.id ? 'Odopnúť' : 'Pripnúť' }}</span>
                  </button>
                  <button
                    class="postActionIconBtn danger"
                    type="button"
                    title="Vymazať"
                    aria-label="Vymazať"
                    :disabled="deleteLoadingId === p.id"
                    @click.stop="deletePost(p)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M4 7h16" />
                      <path d="M9 7V5h6v2" />
                      <path d="M7 7l1 12h8l1-12" />
                      <path d="M10 11v5M14 11v5" />
                    </svg>
                    <span class="postActionLabel">{{ deleteLoadingId === p.id ? 'Mažem...' : 'Vymazať' }}</span>
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div class="loadMore">
            <button
              v-if="tabState[activeTab].next"
              class="btn outline"
              :disabled="tabState[activeTab].loading"
              @click="loadTab(activeTab, false)"
            >
              {{ tabState[activeTab].loading ? 'Nacitavam...' : 'Nacitat viac' }}
            </button>
          </div>
        </template>
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
const PROFILE_MEDIA_UPLOAD_MAX_BYTES = 24576 * 1024
const PROFILE_MEDIA_FALLBACK_TARGET_MAX_BYTES = Math.floor(PROFILE_MEDIA_UPLOAD_MAX_BYTES * 0.92)

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
  { key: 'posts', label: 'Príspevky', kind: 'roots' },
  { key: 'observations', label: 'Pozorovania', kind: 'observations' },
  { key: 'events', label: 'Udalosti', kind: 'events' },
  { key: 'bookmarks', label: 'Záložky', kind: 'bookmarks' },
  { key: 'media', label: 'Médiá', kind: 'media' },
  { key: 'likes', label: 'Páči sa', kind: 'likes' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  events: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  bookmarks: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  likes: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})

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

const displayName = computed(() => auth.user?.name || 'Profil')
const canonicalLocationLabel = computed(() => {
  const fromCanonical = parseStringValue(auth.user?.location_data?.label)
  if (fromCanonical) return fromCanonical

  const fromStoredLabel = parseStringValue(auth.user?.location_label)
  if (fromStoredLabel) return fromStoredLabel

  return parseStringValue(auth.user?.location) || ''
})

const handle = computed(() => {
  const email = auth.user?.email || ''
  const base = email.split('@')[0] || auth.user?.name || 'user'
  return String(base).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
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
const persistedAvatarMode = computed(() => {
  const hasImage = String(avatarSrc.value || '').trim() !== ''
  if (hasImage) return 'image'
  return normalizeAvatarMode(auth.user?.avatar_mode || auth.user?.avatarMode)
})
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

function goToLocationEditor() {
  router.push({ name: 'profile.edit', hash: '#location' })
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
  const base = parentUser?.email?.split('@')[0] || parentUser?.name || 'user'
  return String(base).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function extractFirstError(errorsObj, field) {
  const v = errorsObj?.[field]
  return Array.isArray(v) && v.length ? String(v[0]) : ''
}

function parseStringValue(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed !== '' ? trimmed : null
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

async function compressForProfileUpload(file) {
  let candidate = file

  const passes = [
    {
      maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
    },
    {
      maxBytes: PROFILE_MEDIA_FALLBACK_TARGET_MAX_BYTES,
      maxDimension: 2200,
      initialQuality: 0.82,
      minQuality: 0.28,
      qualityStep: 0.06,
      scaleStep: 0.82,
      maxAttempts: 28,
      minSide: 240,
    },
  ]

  for (const options of passes) {
    if ((candidate?.size || 0) <= options.maxBytes) break

    try {
      const compressed = await compressImageFileToMaxBytes(candidate, options)
      if (compressed && (compressed?.size || 0) > 0 && (compressed.size <= (candidate?.size || Number.MAX_SAFE_INTEGER))) {
        candidate = compressed
      }
    } catch {
      // Keep the best candidate so far and continue with upload checks below.
    }
  }

  return candidate
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
      const fileError = extractFirstError(data.errors, 'file')
      if (/failed to upload|nepodarilo sa nahrat|nepodarilo nahrat/i.test(fileError || '')) {
        mediaErr.value = 'Subor je prilis velky pre server. Skus subor mensi ako 24 MB.'
      } else {
      mediaErr.value =
        fileError ||
        extractFirstError(data.errors, 'type') ||
        'Skontroluj subor.'
      }
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

  uploadFile = await compressForProfileUpload(selectedFile)

  if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
    mediaErr.value = 'Subor je prilis velky a nepodarilo sa ho skomprimovat pod 24 MB.'
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
    title: 'Vymazať príspevok',
    message: 'Naozaj chceš vymazať tento príspevok?',
    confirmText: 'Vymazať',
    cancelText: 'Zrušiť',
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

    actionMsg.value = 'Príspevok bol vymazaný.'
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
  padding: 0 0 1.2rem;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(var(--bg-app-rgb) / 0.92);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border);
  padding: 0.4rem 0.8rem;
  display: flex;
  gap: 0.65rem;
  align-items: center;
}

.iconBtn {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  transition: background-color 180ms ease, border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}
.iconBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.35);
  background: rgb(var(--text-primary-rgb) / 0.09);
  transform: translateY(-1px);
}
.iconBtn:active { transform: translateY(1px); }
.iconBtn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.32);
}

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 850; color: var(--text-primary); font-size: 1.05rem; }
.topsmall { color: var(--text-secondary); font-size: 0.78rem; }

.profileShell {
  border: 0;
  border-radius: 0;
  overflow: hidden;
  margin-top: 0;
  background: transparent;
}

.cover {
  height: 158px;
  position: relative;
  background:
    radial-gradient(900px 220px at 20% 20%, rgb(var(--color-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(700px 220px at 80% 30%, rgb(var(--color-primary-rgb) / 0.12), transparent 60%),
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.2), rgb(var(--color-bg-rgb) / 0.9));
  border-bottom: 1px solid var(--border);
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
  padding: 0 0.85rem;
  transform: translateY(-26px);
}

.avatar {
  width: 92px;
  height: 92px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--text-primary);
  font-weight: 900;
  font-size: 1.25rem;
}
.avatarEditable {
  position: relative;
  overflow: hidden;
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
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.35);
}

.fileInput {
  display: none;
}

.mediaBtn {
  position: absolute;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  font-size: 0.72rem;
  opacity: 0;
  transition: opacity 0.15s ease, background-color 180ms ease, border-color 180ms ease, transform 180ms ease;
  z-index: 2;
}
.mediaBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.35);
  background: rgb(var(--text-primary-rgb) / 0.09);
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
  padding: 0 0.85rem 0.85rem;
  margin-top: -6px;
  border-bottom: 1px solid var(--border);
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.9rem; font-weight: 900; color: var(--text-primary); line-height: 1.05; }
.badge {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.45);
  background: rgb(var(--primary-rgb) / 0.12);
  color: var(--primary);
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
.metaActionBtn {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--primary);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
}
.metaActionBtn:hover {
  text-decoration: underline;
}
.btn.metaSetupBtn {
  min-height: 30px;
  padding: 0.2rem 0.75rem;
  font-size: 0.78rem;
}

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
  border: 1px solid var(--border);
  background: var(--bg-surface);
  border-radius: 1rem;
  padding: 0.72rem;
  margin-top: 0.6rem;
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
  font-size: 0.8rem;
  color: var(--text-primary);
  margin-bottom: 0.35rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.35);
  color: var(--text-primary);
  outline: none;
}
.input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.2);
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
  font-size: 0.85rem;
  color: var(--primary-active);
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.25rem;
  justify-content: flex-end;
}

.btn {
  min-height: 44px;
  padding: 0 1.25rem;
  border-radius: 999px;
  border: 1px solid transparent;
  background: var(--primary);
  color: var(--text-primary);
  font-weight: 600;
  font-size: 0.92rem;
  line-height: 1;
  transition: background-color 180ms ease, border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease, color 180ms ease;
}
.btn:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
}
.btn:active { transform: translateY(1px); }
.btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.32);
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

.btn.outline {
  background: var(--bg-surface-2);
  border-color: var(--border);
  color: var(--text-secondary);
}
.btn.outline:hover { border-color: rgb(var(--primary-rgb) / 0.35); color: var(--text-primary); background: rgb(var(--text-primary-rgb) / 0.09); }
.btn.outline.danger { border-color: var(--primary-active); color: var(--primary-active); }
.btn.outline.danger:hover { border-color: var(--primary-active); background: rgb(var(--primary-active-rgb) / 0.12); color: var(--text-primary); }

.btn.ghost {
  border-color: var(--border);
  background: transparent;
  color: var(--text-secondary);
}
.btn.ghost:hover { border-color: rgb(var(--primary-rgb) / 0.35); background: rgb(var(--text-primary-rgb) / 0.06); color: var(--text-primary); }

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
  background: rgb(var(--bg-app-rgb) / 0.86);
  backdrop-filter: blur(8px);
  padding: 0 0 0.25rem;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}

.tab {
  padding: 0.7rem 0.25rem 0.55rem;
  border-radius: 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--text-primary);
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
  border-bottom-color: var(--primary);
  color: var(--text-primary);
}

.padTop { margin-top: 0.75rem; }

.observationsList {
  display: grid;
  gap: 0.9rem;
  margin-top: 0.85rem;
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
  gap: 0.68rem;
  padding: 0.55rem 0.45rem;
  border-radius: 0.9rem;
  border-top: 1px solid var(--border);
  transition: background-color 170ms ease, border-color 170ms ease;
}
.postItem:first-child { border-top: 0; }
.postItem:hover {
  background: rgb(var(--text-primary-rgb) / 0.04);
}
.postItem.pinned {
  background: rgb(var(--primary-rgb) / 0.08);
  border-color: rgb(var(--primary-rgb) / 0.28);
}

.postBody {
  min-width: 0;
  display: grid;
  gap: 0.34rem;
}

.postMeta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: var(--text-secondary);
  font-size: 0.9rem;
}
.postName { color: var(--text-primary); font-weight: 950; }
.dot { opacity: 0.6; }
.postTime {
  color: var(--text-secondary);
  font-size: 0.82rem;
}

.replyContext {
  margin-top: 0.2rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.75rem;
  background: rgb(var(--bg-app-rgb) / 0.5);
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.replyAuthor { color: var(--text-primary); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: var(--text-primary); margin-left: 0.25rem; }

.postContent {
  margin-top: 0.04rem;
  color: var(--text-primary);
  font-size: 0.95rem;
  white-space: pre-wrap;
  line-height: 1.45;
  --hashtag-color: #3b82f6;
  --hashtag-hover-color: #2563eb;
}

.attachedEventCard {
  margin-top: 0.55rem;
  border: 1px solid rgb(var(--primary-rgb) / 0.45);
  background: rgb(var(--primary-rgb) / 0.08);
  border-radius: 0.85rem;
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
  border-radius: 0.9rem;
  border: 1px solid var(--border);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border);
  color: var(--text-primary);
  text-decoration: none;
}

.postActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.32rem;
  margin-top: 0.24rem;
}

.postActionIconBtn {
  min-height: 30px;
  padding: 0.3rem 0.56rem;
  border-radius: 999px;
  border: 1px solid transparent;
  background: rgb(var(--text-primary-rgb) / 0.04);
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  transition: background-color 160ms ease, color 160ms ease, transform 160ms ease;
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
  background: rgb(var(--text-primary-rgb) / 0.11);
  border-color: rgb(var(--text-primary-rgb) / 0.12);
  color: var(--text-primary);
}
.postActionIconBtn:active:not(:disabled) {
  transform: scale(0.96);
}
.postActionIconBtn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px rgb(var(--primary-rgb) / 0.35);
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
  color: rgb(var(--primary-active-rgb) / 0.9);
  border-color: rgb(var(--primary-active-rgb) / 0.2);
}
.postActionIconBtn.danger:hover:not(:disabled) {
  color: var(--primary-active);
  background: rgb(var(--primary-active-rgb) / 0.14);
  border-color: rgb(var(--primary-active-rgb) / 0.36);
}

.loadMore {
  display: flex;
  justify-content: center;
  padding-top: 0.75rem;
}

.msg {
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.95rem;
}
.msg.ok { border: 1px solid var(--primary); background: rgb(var(--primary-rgb) / 0.1); color: var(--primary); }
.msg.err { border: 1px solid var(--primary-active); background: rgb(var(--primary-active-rgb) / 0.1); color: var(--primary-active); }
.msg.info { border: 1px solid rgb(var(--primary-rgb) / 0.45); background: rgb(var(--primary-rgb) / 0.12); color: var(--primary); }

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

  .avatarActionRow .btn {
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
