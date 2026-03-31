import { describe, expect, it } from 'vitest'
import { attachmentSrc, isImage } from './profileView.utils'

describe('profileView utils', () => {
  it('treats attachment paths with image extensions as images even when the original name is generic', () => {
    expect(isImage({
      attachment_mime: '',
      attachment_original_name: 'attachment',
      attachment_path: 'posts/15/images/web.webp',
    })).toBe(true)
  })

  it('resolves relative attachment URLs against the API base URL', () => {
    expect(attachmentSrc({ attachment_url: '/api/media/15' }, 'https://astrokomunita.test/api')).toBe(
      'https://astrokomunita.test/api/media/15',
    )
  })
})
