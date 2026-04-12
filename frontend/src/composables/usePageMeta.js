/**
 * Centralized SEO meta management for the SPA.
 *
 * Why: A Vue SPA renders in the browser, so search engines see the static
 * index.html first. Each detail page must overwrite <title>, <meta>, OG tags,
 * and a <link rel="canonical"> as soon as content loads. This composable
 * provides a single, consistent way to do that — matching the manual-DOM
 * pattern already used in LearnDetailView.
 */

const BASE_URL = 'https://astrokomunita.sk'
const SITE_NAME = 'Astrokomunita'
const DEFAULT_TITLE = `${SITE_NAME} – astronomická komunita na Slovensku`
const DEFAULT_DESCRIPTION =
  'Astrokomunita je slovenská platforma pre milovníkov astronómie. Sledujte astronomické udalosti, zdieľajte pozorovania a čítajte články o hviezdach a vesmíre.'
const DEFAULT_IMAGE = `${BASE_URL}/og-default.jpg`

export function usePageMeta() {
  function _ensureMeta(attrType, attrValue) {
    const selector = `meta[${attrType}="${attrValue}"]`
    let tag = document.querySelector(selector)
    if (!tag) {
      tag = document.createElement('meta')
      tag.setAttribute(attrType, attrValue)
      document.head.appendChild(tag)
    }
    return tag
  }

  function _ensureLink(rel) {
    let tag = document.querySelector(`link[rel="${rel}"]`)
    if (!tag) {
      tag = document.createElement('link')
      tag.setAttribute('rel', rel)
      document.head.appendChild(tag)
    }
    return tag
  }

  /**
   * @param {object} opts
   * @param {string|null} opts.title     - Page title (without site name suffix)
   * @param {string|null} opts.description
   * @param {string|null} opts.image     - Absolute image URL for OG/Twitter
   * @param {string|null} opts.url       - Canonical/OG URL for this page
   * @param {string}      opts.type      - og:type ('website' | 'article')
   */
  function setMeta({ title = null, description = null, image = null, url = null, type = 'website' } = {}) {
    if (typeof document === 'undefined') return

    const fullTitle = title ? `${title} | ${SITE_NAME}` : DEFAULT_TITLE
    // Clamp description to 160 chars (Google's visible limit)
    const metaDescription = (description || DEFAULT_DESCRIPTION).slice(0, 160)
    const metaImage = image || DEFAULT_IMAGE
    const metaUrl = url || (typeof window !== 'undefined' ? window.location.href : BASE_URL)

    document.title = fullTitle

    _ensureMeta('name', 'description').setAttribute('content', metaDescription)

    _ensureMeta('property', 'og:title').setAttribute('content', fullTitle)
    _ensureMeta('property', 'og:description').setAttribute('content', metaDescription)
    _ensureMeta('property', 'og:image').setAttribute('content', metaImage)
    _ensureMeta('property', 'og:url').setAttribute('content', metaUrl)
    _ensureMeta('property', 'og:type').setAttribute('content', type)
    _ensureMeta('property', 'og:site_name').setAttribute('content', SITE_NAME)

    _ensureMeta('name', 'twitter:card').setAttribute('content', 'summary_large_image')
    _ensureMeta('name', 'twitter:title').setAttribute('content', fullTitle)
    _ensureMeta('name', 'twitter:description').setAttribute('content', metaDescription)
    _ensureMeta('name', 'twitter:image').setAttribute('content', metaImage)

    _ensureLink('canonical').setAttribute('href', metaUrl)
  }

  /**
   * Inject a JSON-LD <script> block into <head>.
   * Replaces any previously injected block on the same page.
   */
  function setJsonLd(data) {
    if (typeof document === 'undefined') return
    const id = 'page-json-ld'
    let script = document.getElementById(id)
    if (!script) {
      script = document.createElement('script')
      script.id = id
      script.type = 'application/ld+json'
      document.head.appendChild(script)
    }
    script.textContent = JSON.stringify(data)
  }

  /** Remove the JSON-LD block (call in onBeforeUnmount). */
  function clearJsonLd() {
    if (typeof document === 'undefined') return
    const script = document.getElementById('page-json-ld')
    if (script) script.remove()
  }

  /**
   * Restore index.html defaults.
   * Call in onBeforeUnmount so navigating away doesn't leave stale tags.
   */
  function resetMeta() {
    setMeta({ url: BASE_URL })
    clearJsonLd()
  }

  return { setMeta, setJsonLd, clearJsonLd, resetMeta }
}
