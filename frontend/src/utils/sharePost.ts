import { createApp, h, nextTick } from 'vue'
import { toBlob } from 'html-to-image'
import PostShareCard from '@/components/share/PostShareCard.vue'

type GenericRecord = Record<string, any>

const SHARE_WIDTH = 1080
const SHARE_HEIGHT = 1350

export function buildPostUrl(post: GenericRecord): string {
  const id = post?.id
  if (!id) return typeof window !== 'undefined' ? window.location.href : ''

  const origin = typeof window !== 'undefined' ? window.location.origin : ''
  return `${origin}/posts/${id}`
}

export async function shareLink(post: GenericRecord): Promise<void> {
  const url = buildPostUrl(post)
  const title = String(post?.title || 'Prispevok')
  const text = String(post?.content || '').trim().slice(0, 120) || 'Pozri si tento prispevok.'

  if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
    try {
      await navigator.share({ title, text, url })
      return
    } catch (error: any) {
      if (error?.name === 'AbortError') return
    }
  }

  await copyText(url)
}

export async function generateShareImage(
  post: GenericRecord,
): Promise<{ blob: Blob; file: File; dataUrl: string }> {
  let blob: Blob

  try {
    blob = await renderShareCard(post, false)
  } catch {
    blob = await renderShareCard(post, true)
  }

  const dataUrl = await blobToDataUrl(blob)
  const file = new File([blob], `nebesky-sprievodca-${post?.id || 'post'}.png`, {
    type: 'image/png',
  })

  return { blob, file, dataUrl }
}

export async function shareImage(post: GenericRecord): Promise<void> {
  const generated = await generateShareImage(post)

  if (
    typeof navigator !== 'undefined' &&
    typeof navigator.canShare === 'function' &&
    navigator.canShare({ files: [generated.file] }) &&
    typeof navigator.share === 'function'
  ) {
    try {
      await navigator.share({
        title: String(post?.title || 'Prispevok'),
        text: 'Zdielam post z Astrokomunity',
        files: [generated.file],
      })
      return
    } catch (error: any) {
      if (error?.name === 'AbortError') return
    }
  }

  downloadDataUrl(generated.dataUrl, generated.file.name)
}

async function renderShareCard(post: GenericRecord, forcePlaceholderAvatar: boolean): Promise<Blob> {
  if (typeof document === 'undefined') {
    throw new Error('Image generation is only available in browser context.')
  }

  const container = document.createElement('div')
  container.style.position = 'fixed'
  container.style.left = '-10000px'
  container.style.top = '0'
  container.style.opacity = '0'
  container.style.pointerEvents = 'none'
  container.style.zIndex = '-1'

  document.body.appendChild(container)

  const app = createApp({
    render() {
      return h(PostShareCard, {
        post,
        author: post?.user || null,
        brandDomain: 'nebesky-sprievodca.sk',
        forcePlaceholderAvatar,
      })
    },
  })

  app.mount(container)

  try {
    await nextTick()
    await waitForPaint()

    if (document.fonts?.ready) {
      await document.fonts.ready
    }

    const cardElement = container.firstElementChild as HTMLElement | null
    if (!cardElement) {
      throw new Error('Share card element missing.')
    }

    const blob = await toBlob(cardElement, {
      width: SHARE_WIDTH,
      height: SHARE_HEIGHT,
      pixelRatio: 1,
      cacheBust: true,
      backgroundColor: '#0b0f14',
    })

    if (!blob) {
      throw new Error('Image conversion failed.')
    }

    return blob
  } finally {
    app.unmount()
    container.remove()
  }
}

function waitForPaint(): Promise<void> {
  return new Promise((resolve) => {
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => resolve()))
  })
}

function blobToDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(new Error('Failed to read generated image.'))
    reader.readAsDataURL(blob)
  })
}

async function copyText(value: string): Promise<void> {
  if (!value) return

  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value)
    return
  }

  const input = document.createElement('textarea')
  input.value = value
  input.setAttribute('readonly', '')
  input.style.position = 'fixed'
  input.style.left = '-9999px'
  document.body.appendChild(input)
  input.select()

  try {
    document.execCommand('copy')
  } finally {
    document.body.removeChild(input)
  }
}

function downloadDataUrl(dataUrl: string, filename: string): void {
  const link = document.createElement('a')
  link.href = dataUrl
  link.download = filename
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}