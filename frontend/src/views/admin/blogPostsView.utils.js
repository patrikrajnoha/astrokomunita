import { stripHtml } from '@/utils/articleContent'

export function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

export function toDateTimeLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (number) => String(number).padStart(2, '0')
  const year = date.getFullYear()
  const month = pad(date.getMonth() + 1)
  const day = pad(date.getDate())
  const hours = pad(date.getHours())
  const minutes = pad(date.getMinutes())
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

export function fromDateTimeLocal(value) {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

export function computeStatus(post) {
  if (!post?.published_at) return 'draft'
  const date = new Date(post.published_at)
  if (Number.isNaN(date.getTime())) return 'draft'
  if (Boolean(post?.is_hidden) && date.getTime() <= Date.now()) return 'hidden'
  return date.getTime() <= Date.now() ? 'published' : 'scheduled'
}

export function statusLabel(value) {
  switch (value) {
    case 'published':
      return 'Publikovany'
    case 'hidden':
      return 'Skryty'
    case 'scheduled':
      return 'Naplanovany'
    case 'draft':
    default:
      return 'Koncept'
  }
}

export function getInitialListDensity(storageKey) {
  if (typeof window === 'undefined') return 'comfortable'
  try {
    const stored = window.localStorage.getItem(storageKey)
    if (stored === 'comfortable' || stored === 'dense') return stored
  } catch {
    // Ignore unavailable localStorage and fallback to default.
  }
  return 'comfortable'
}

export function persistListDensity(storageKey, value) {
  if (typeof window === 'undefined') return
  try {
    window.localStorage.setItem(storageKey, value)
  } catch {
    // Ignore unavailable localStorage.
  }
}

export function slugifyHeading(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .slice(0, 80)
}

function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

export function inlineMarkdown(text) {
  const safe = escapeHtml(text)
  let html = safe
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>')
  html = html.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
  )
  return html
}

export function parseContentBlocks(text) {
  const raw = String(text || '')
  if (!raw.trim()) return []

  const lines = raw.split(/\r?\n/)
  const blocks = []
  let paragraphBuffer = []
  let listBuffer = []
  let keyIndex = 0

  const nextKey = () => `b-${keyIndex++}`

  const flushParagraph = () => {
    const paragraphText = paragraphBuffer.join(' ').trim()
    if (paragraphText) {
      blocks.push({ type: 'p', html: inlineMarkdown(paragraphText), key: nextKey() })
    }
    paragraphBuffer = []
  }

  const flushList = () => {
    if (listBuffer.length) {
      blocks.push({
        type: 'ul',
        items: listBuffer.map((item) => inlineMarkdown(item)),
        key: nextKey(),
      })
      listBuffer = []
    }
  }

  lines.forEach((line) => {
    const trimmed = line.trim()
    const isH2 = trimmed.startsWith('## ')
    const isH3 = trimmed.startsWith('### ')
    const isList = trimmed.startsWith('- ') || trimmed.startsWith('* ')

    if (isH2 || isH3) {
      flushList()
      flushParagraph()
      const title = trimmed.replace(/^###?\s+/, '')
      blocks.push({
        type: isH3 ? 'h3' : 'h2',
        text: title,
        id: slugifyHeading(title),
        key: nextKey(),
      })
      return
    }

    if (trimmed === '') {
      flushList()
      flushParagraph()
      return
    }

    if (isList) {
      flushParagraph()
      listBuffer.push(trimmed.replace(/^[-*]\s+/, ''))
      return
    }

    paragraphBuffer.push(trimmed)
  })

  flushList()
  flushParagraph()
  return blocks
}

export function readTimeFor(text) {
  const words = stripHtml(text || '')
    .split(/\s+/)
    .filter(Boolean).length
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min citania`
}

export function toMetricCount(value) {
  const parsed = Number(value)
  if (!Number.isFinite(parsed) || parsed < 0) return null
  return Math.floor(parsed)
}

export function postReadCount(post) {
  const readMetric =
    toMetricCount(post?.read_count) ??
    toMetricCount(post?.reads_count) ??
    toMetricCount(post?.reads)
  if (readMetric !== null) return readMetric
  return toMetricCount(post?.views_count) ?? toMetricCount(post?.views) ?? 0
}

export function postClickCount(post) {
  const clickMetric =
    toMetricCount(post?.click_count) ??
    toMetricCount(post?.clicks_count) ??
    toMetricCount(post?.clicks)
  if (clickMetric !== null) return clickMetric
  return postReadCount(post)
}

export function formatMetricCount(value) {
  return Number(value || 0).toLocaleString('sk-SK')
}
