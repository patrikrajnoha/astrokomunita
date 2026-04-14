import { expect, test, type Page } from '@playwright/test'

async function openFiltersPanel(page: Page): Promise<void> {
  const toggle = page.locator('button.filter-toggle-btn:visible').first()
  await expect(toggle).toBeVisible()

  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }

  await expect(page.locator('.filters-content:visible').first()).toBeVisible()
}

test.describe('events filters ux', () => {
  test('quick period presets and active chips stay in sync', async ({ page }) => {
    await page.goto('/events')

    await expect(page.getByRole('heading', { level: 1, name: /astronomick.*udalost/i })).toBeVisible()

    await openFiltersPanel(page)
    const filtersPanel = page.locator('.filters-content:visible').first()

    await filtersPanel.getByRole('button', { name: 'Tento rok' }).first().click()
    await expect(page).toHaveURL(/\/events\?.*period=year/i)

    const scopeTablist = page
      .locator('.toolbar-row:visible')
      .first()
      .getByRole('tablist', { name: /Časový rozsah udalostí|Casovy rozsah udalosti/i })
      .first()
    await scopeTablist.getByRole('button', { name: /Minulé|Minule/i }).click()
    await expect(page).toHaveURL(/\/events\?.*scope=past/i)

    const searchInput = filtersPanel.getByRole('searchbox', { name: /hľadaj|hladaj/i }).first()
    await searchInput.fill('mars')
    await expect(searchInput).toHaveValue('mars')
    await searchInput.fill('')
    await expect(searchInput).toHaveValue('')

    const regionSelect = filtersPanel.getByLabel(/región|region/i).first()
    await regionSelect.selectOption('global')

    await page.locator('.active-filters .filter-chip-clear').first().click()

    await expect(page).toHaveURL(/\/events\?.*scope=future/i)
    await expect(searchInput).toHaveValue('')
    await expect(page.locator('.active-filters')).toHaveCount(0)
  })
})
