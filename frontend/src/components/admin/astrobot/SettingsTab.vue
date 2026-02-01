<template>
  <div class="settingsTab">
    <div class="settingsCard">
      <h2 class="settingsTitle">AstroBot Settings</h2>
      <p class="settingsSubtitle">Configure RSS pipeline and automation (placeholder MVP)</p>

      <div class="settingsSection">
        <h3>Auto-fetch</h3>
        <label class="toggleLabel">
          <input
            v-model="autoFetchEnabled"
            type="checkbox"
            class="toggleInput"
          />
          <span class="toggleSlider"></span>
          Enable auto-fetch (every 15 minutes)
        </label>
        <p class="settingsNote">
          This setting is UI-only. To enable/disable scheduler, edit <code>routes/console.php</code>.
        </p>
      </div>

      <div class="settingsSection">
        <h3>Default schedule</h3>
        <label class="toggleLabel">
          <input
            v-model="autoPublishEnabled"
            type="checkbox"
            class="toggleInput"
          />
          <span class="toggleSlider"></span>
          Auto-publish scheduled items (every minute)
        </label>
        <p class="settingsNote">
          Scheduler runs <code>astrobot:publish-scheduled</code> each minute.
        </p>
      </div>

      <div class="settingsSection">
        <h3>Info</h3>
        <ul class="infoList">
          <li><strong>Source:</strong> NASA News Release RSS</li>
          <li><strong>Deduplication:</strong> GUID or URL hash</li>
          <li><strong>Bot user:</strong> AstroBot (astrobot@astrokomunita.local)</li>
          <li><strong>Posts:</strong> Tagged with source_name = astrobot</li>
        </ul>
      </div>

      <div class="settingsSection">
        <h3>Manual commands</h3>
        <div class="codeBlock">
          <div class="codeLine">
            <span class="codePrompt">$</span> php artisan astrobot:ensure-user
          </div>
          <div class="codeLine">
            <span class="codePrompt">$</span> php artisan astrobot:fetch --source=nasa_news
          </div>
          <div class="codeLine">
            <span class="codePrompt">$</span> php artisan astrobot:publish-scheduled
          </div>
        </div>
      </div>

      <div class="settingsSection">
        <h3>API endpoints</h3>
        <div class="codeBlock">
          <div class="codeLine">GET /api/admin/astrobot/items</div>
          <div class="codeLine">POST /api/admin/astrobot/fetch</div>
          <div class="codeLine">POST /api/admin/astrobot/items/{id}/publish</div>
          <div class="codeLine">POST /api/admin/astrobot/items/{id}/schedule</div>
          <div class="codeLine">POST /api/admin/astrobot/items/{id}/discard</div>
          <div class="codeLine">GET /api/admin/astrobot/posts</div>
          <div class="codeLine">DELETE /api/admin/astrobot/posts/{id}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SettingsTab',
  data() {
    return {
      autoFetchEnabled: true,
      autoPublishEnabled: true,
    }
  },
}
</script>

<style scoped>
.settingsTab {
  display: grid;
  gap: 1.5rem;
}

.settingsCard {
  padding: 2rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.4);
  border-radius: 1rem;
  display: grid;
  gap: 2rem;
}

.settingsTitle {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-surface);
  margin: 0;
}

.settingsSubtitle {
  color: var(--color-text-secondary);
  margin: 0.25rem 0 0 0;
}

.settingsSection h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-surface);
  margin-bottom: 0.75rem;
}

.toggleLabel {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  font-weight: 600;
  color: var(--color-surface);
}

.toggleInput {
  display: none;
}

.toggleSlider {
  position: relative;
  width: 44px;
  height: 24px;
  background: rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 12px;
  transition: background 0.2s ease-out;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: transform 0.2s ease-out;
}

.toggleInput:checked + .toggleSlider {
  background: var(--color-primary);
}

.toggleInput:checked + .toggleSlider::before {
  transform: translateX(20px);
}

.settingsNote {
  font-size: 0.85rem;
  color: var(--color-text-secondary);
  margin-top: 0.5rem;
  margin-bottom: 0;
}

.settingsNote code {
  background: rgb(var(--color-text-secondary-rgb) / 0.15);
  padding: 0.1rem 0.3rem;
  border-radius: 0.25rem;
  font-family: monospace;
}

.infoList {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.5rem;
}

.infoList li {
  color: var(--color-text-secondary);
}

.infoList strong {
  color: var(--color-surface);
}

.codeBlock {
  background: rgb(var(--color-bg-rgb) / 0.8);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.5rem;
  padding: 1rem;
  font-family: monospace;
  font-size: 0.85rem;
  display: grid;
  gap: 0.5rem;
}

.codeLine {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.codePrompt {
  color: var(--color-text-secondary);
  user-select: none;
}
</style>
