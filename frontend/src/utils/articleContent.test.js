import { describe, expect, it } from 'vitest'
import { renderArticleContent, sanitizeArticleHtml } from './articleContent'

describe('articleContent media url contract', () => {
  it('normalizes relative media API image urls to absolute urls', () => {
    const rendered = renderArticleContent('<p><img src="/api/media/file/blog-inline/12/sample.png" alt="sample" /></p>')
    expect(rendered.html).toContain('/api/media/file/blog-inline/12/sample.png')
    expect(rendered.html).toMatch(/src="https?:\/\/[^"]+\/api\/media\/file\/blog-inline\/12\/sample\.png"/)
  })

  it('accepts schema-less www image urls inserted via link', () => {
    const html = sanitizeArticleHtml('<p><img src="www.example.com/photo.jpg" alt="photo" /></p>')
    expect(html).toContain('src="https://www.example.com/photo.jpg"')
  })
})
