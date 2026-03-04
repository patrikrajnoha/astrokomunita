import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const disallowedLegacyRoutes = [
  '/admin/event-sources',
  '/admin/event-candidates',
  '/admin/candidates',
  '/admin/blog',
  '/admin/newsletter',
  '/admin/users',
  '/admin/moderation',
  '/admin/reports',
]

const filesToCheck = [
  'src/layouts/AdminHubLayout.vue',
  'src/views/admin/AdminDashboardView.vue',
  'src/views/admin/CandidateDetailView.vue',
  'src/views/admin/CandidatesListView.vue',
  'src/components/MainNavbar.vue',
]

describe('admin legacy navigation links', () => {
  it('does not use legacy admin routes in internal RouterLink/router.push navigation', () => {
    for (const relativePath of filesToCheck) {
      const absolutePath = join(process.cwd(), relativePath)
      const content = readFileSync(absolutePath, 'utf8')

      for (const legacyRoute of disallowedLegacyRoutes) {
        expect(content.includes(`to="${legacyRoute}"`), `${relativePath} contains to=\"${legacyRoute}\"`).toBe(false)
        expect(content.includes(`router.push('${legacyRoute}'`), `${relativePath} contains router.push('${legacyRoute}')`).toBe(false)
        expect(content.includes(`router.push(\"${legacyRoute}\"`), `${relativePath} contains router.push(\"${legacyRoute}\")`).toBe(false)
      }
    }
  })
})
