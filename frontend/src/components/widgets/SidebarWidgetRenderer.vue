<template>
  <section class="widgetRoot panel" :class="[`widget-${widgetType}`, { inactive: !isActive }]">
    <header class="widgetHead">
      <span class="widgetTypeLabel">{{ widgetTypeLabel }}</span>
      <span v-if="!isActive" class="inactiveBadge">Neaktivny</span>
    </header>

    <template v-if="widgetType === SIDEBAR_WIDGET_TYPES.CTA">
      <div v-if="ctaConfig.imageUrl" class="widgetMedia">
        <img :src="ctaConfig.imageUrl" alt="" loading="lazy" />
      </div>
      <h3 class="widgetTitle">{{ ctaConfig.headline || 'CTA widget' }}</h3>
      <p class="widgetText">{{ ctaConfig.body || 'Dopln text pre CTA widget.' }}</p>
      <component
        :is="ctaLinkComponent"
        v-bind="ctaLinkProps"
        class="widgetAction"
      >
        {{ ctaConfig.buttonText || 'Otvorit' }}
      </component>
    </template>

    <template v-else-if="widgetType === SIDEBAR_WIDGET_TYPES.INFO_CARD">
      <h3 class="widgetTitle">
        <span v-if="infoCardConfig.icon" class="icon">{{ infoCardConfig.icon }}</span>
        {{ infoCardConfig.title || 'Info karta' }}
      </h3>
      <p class="widgetText">{{ infoCardConfig.content || 'Dopln obsah info karty.' }}</p>
    </template>

    <template v-else-if="widgetType === SIDEBAR_WIDGET_TYPES.LINK_LIST">
      <h3 class="widgetTitle">{{ linkListConfig.title || 'Uzitocne odkazy' }}</h3>
      <ul class="linkList">
        <li v-for="(link, index) in validLinks" :key="`link-${index}-${link.label}`">
          <component :is="resolveLinkComponent(link.href)" v-bind="resolveLinkProps(link.href)">
            {{ link.label || link.href }}
          </component>
        </li>
      </ul>
      <p v-if="validLinks.length === 0" class="widgetHint">Zatial ziadne odkazy.</p>
    </template>

    <template v-else-if="widgetType === SIDEBAR_WIDGET_TYPES.CONTEST">
      <div v-if="contestConfig.imageUrl" class="widgetMedia">
        <img :src="contestConfig.imageUrl" alt="" loading="lazy" />
      </div>
      <h3 class="widgetTitle">{{ contestConfig.title || 'SUTAZ' }}</h3>
      <p class="widgetText">{{ contestConfig.description || 'Dopln kratky popis sutaze.' }}</p>
    </template>

    <template v-else>
      <div class="htmlWidget" v-html="htmlConfig.html || '<p>Dopln HTML obsah widgetu.</p>'"></div>
    </template>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  SIDEBAR_WIDGET_TYPES,
  getWidgetTypeLabel,
  normalizeWidgetConfig,
  normalizeWidgetType,
} from '@/sidebar/customWidgets/types'

const props = defineProps({
  component: {
    type: Object,
    default: null,
  },
  widget: {
    type: Object,
    default: null,
  },
  preview: {
    type: Boolean,
    default: false,
  },
})

const sourceWidget = computed(() => {
  if (props.widget && typeof props.widget === 'object') {
    return props.widget
  }

  if (props.component && typeof props.component === 'object') {
    return props.component
  }

  return {
    type: SIDEBAR_WIDGET_TYPES.CTA,
    is_active: true,
    config_json: {},
  }
})

const widgetType = computed(() => normalizeWidgetType(sourceWidget.value?.type))

const widgetTypeLabel = computed(() => getWidgetTypeLabel(widgetType.value))

const isActive = computed(() => {
  if (props.preview) return true
  return Boolean(sourceWidget.value?.is_active ?? true)
})

const normalizedConfig = computed(() => {
  const rawConfig = sourceWidget.value?.config_json ?? sourceWidget.value?.config ?? {}
  return normalizeWidgetConfig(widgetType.value, rawConfig)
})

const ctaConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.CTA, normalizedConfig.value))
const infoCardConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.INFO_CARD, normalizedConfig.value))
const linkListConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.LINK_LIST, normalizedConfig.value))
const htmlConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.HTML, normalizedConfig.value))
const contestConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.CONTEST, normalizedConfig.value))

const validLinks = computed(() => {
  const links = Array.isArray(linkListConfig.value.links) ? linkListConfig.value.links : []
  return links.filter((item) => String(item?.href || '').trim() !== '')
})

const isInternalHref = (href) => String(href || '').startsWith('/')

const ctaLinkComponent = computed(() => {
  if (isInternalHref(ctaConfig.value.buttonHref)) {
    return RouterLink
  }

  return 'a'
})

const ctaLinkProps = computed(() => {
  const href = String(ctaConfig.value.buttonHref || '/events')

  if (isInternalHref(href)) {
    return { to: href }
  }

  return {
    href,
    target: '_blank',
    rel: 'noopener noreferrer',
  }
})

const resolveLinkComponent = (href) => {
  return isInternalHref(href) ? RouterLink : 'a'
}

const resolveLinkProps = (href) => {
  const safeHref = String(href || '')

  if (isInternalHref(safeHref)) {
    return { to: safeHref, class: 'widgetLink' }
  }

  return {
    href: safeHref,
    target: '_blank',
    rel: 'noopener noreferrer',
    class: 'widgetLink',
  }
}
</script>

<style scoped>
.widgetRoot {
  display: grid;
  gap: 0.65rem;
  padding: 0.75rem;
  border-radius: 0.9rem;
  background: linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.5), rgb(var(--color-bg-rgb) / 0.32));
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.18);
}

.widgetRoot.inactive {
  opacity: 0.72;
}

.widgetHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.widgetTypeLabel {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.inactiveBadge {
  font-size: 0.68rem;
  border-radius: 999px;
  padding: 0.14rem 0.5rem;
  background: rgb(var(--color-warning-rgb, 255 178 64) / 0.2);
  color: rgb(var(--color-warning-rgb, 255 178 64));
}

.widgetMedia {
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.widgetMedia img {
  width: 100%;
  display: block;
  object-fit: cover;
}

.widgetTitle {
  margin: 0;
  font-size: 0.95rem;
  color: var(--color-surface);
}

.widgetText,
.widgetHint {
  margin: 0;
  font-size: 0.82rem;
  color: var(--color-text-secondary);
  line-height: 1.45;
}

.widgetAction {
  width: fit-content;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  padding: 0.48rem 0.72rem;
}

.linkList {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.42rem;
}

.widgetLink {
  display: inline-flex;
  color: var(--color-surface);
  text-decoration: none;
  font-size: 0.8rem;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  padding-bottom: 0.1rem;
}

.widgetLink:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.56);
}

.icon {
  margin-right: 0.34rem;
  opacity: 0.8;
}

.htmlWidget {
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  line-height: 1.5;
}

.htmlWidget :deep(a) {
  color: var(--color-surface);
}

.htmlWidget :deep(p) {
  margin: 0 0 0.5rem;
}

.htmlWidget :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
