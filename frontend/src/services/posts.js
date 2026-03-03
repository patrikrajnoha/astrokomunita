import api from '@/services/api'

/*
Manual test checklist (create-post upload):
1) In UI pick PNG/GIF and submit post.
2) DevTools Network -> POST /api/posts has Content-Type "multipart/form-data; boundary=...".
3) Payload contains "attachment" as binary.
4) Console (DEV only) prints selected file + FormData entries.
5) Response is 201 with attachment_path/attachment_url/attachment_mime.
*/
export async function createPost(payload = {}, config = {}) {
  if (payload instanceof FormData) {
    logCreatePostDiagnostics(payload.get('attachment'), payload)
    return api.post('/posts', payload, config)
  }

  const content = String(payload?.content || '').trim()
  const body = new FormData()
  body.append('content', content)

  if (payload?.attachment instanceof Blob) {
    body.append('attachment', payload.attachment)
  }

  const gif = payload?.gif && typeof payload.gif === 'object' ? payload.gif : null
  if (gif) {
    body.append('gif[id]', String(gif.id || ''))
    body.append('gif[title]', String(gif.title || ''))
    body.append('gif[preview_url]', String(gif.preview_url || ''))
    body.append('gif[original_url]', String(gif.original_url || ''))

    const gifWidth = Number(gif.width || 0)
    const gifHeight = Number(gif.height || 0)
    if (gifWidth > 0) body.append('gif[width]', String(gifWidth))
    if (gifHeight > 0) body.append('gif[height]', String(gifHeight))
  }

  const eventId = Number(payload?.eventId || 0)
  if (eventId > 0) {
    body.append('event_id', String(eventId))
  }

  const poll = payload?.poll && typeof payload.poll === 'object' ? payload.poll : null
  if (poll) {
    const durationValue = clampPollDuration(poll.durationSeconds)
    body.append('poll[duration_seconds]', String(durationValue))

    const options = Array.isArray(poll.options) ? poll.options.slice(0, 4) : []
    options.forEach((option, index) => {
      body.append(`poll[options][${index}][text]`, String(option?.text || '').trim())
      if (option?.imageFile instanceof Blob) {
        body.append(`poll[options][${index}][image]`, option.imageFile)
      }
    })
  }

  logCreatePostDiagnostics(payload?.attachment, body)
  return api.post('/posts', body, config)
}

function clampPollDuration(value) {
  const POLL_MIN_SECONDS = 300
  const POLL_MAX_SECONDS = 604800
  const numberValue = Number(value || 0)
  if (!Number.isFinite(numberValue)) return 86400
  return Math.max(POLL_MIN_SECONDS, Math.min(POLL_MAX_SECONDS, Math.round(numberValue)))
}

function logCreatePostDiagnostics(selectedFile, formData) {
  if (!isDevMode()) {
    return
  }

  const entries = []
  for (const [field, value] of formData.entries()) {
    if (value instanceof Blob) {
      entries.push({
        field,
        kind: isFileObject(value) ? 'file' : 'blob',
        name: isFileObject(value) ? value.name : '',
        type: String(value.type || ''),
        size: Number(value.size || 0),
      })
      continue
    }

    entries.push({
      field,
      kind: 'value',
      value: String(value),
    })
  }

  const fileMeta = selectedFile instanceof Blob
    ? {
      fileObject: selectedFile,
      name: isFileObject(selectedFile) ? selectedFile.name : '',
      type: String(selectedFile.type || ''),
      size: Number(selectedFile.size || 0),
    }
    : null

  console.info('[createPost] upload diagnostics', {
    selectedFile: fileMeta,
    formDataEntries: entries,
  })
}

function isDevMode() {
  return typeof import.meta !== 'undefined'
    && Boolean(import.meta?.env?.DEV)
}

function isFileObject(value) {
  return typeof File !== 'undefined' && value instanceof File
}
