# Events Realtime Feed

- Broadcast channel: `events.feed` (public channel)
- Broadcast event name: `event.published`
- Payload:
  - `event_id` (int)
  - `scope` (`normal` or future scopes like `featured`)
  - `published_at` (ISO datetime)

Frontend subscription is in `frontend/src/views/EventsView.vue` using existing Echo/Reverb setup from `frontend/src/realtime/echo.js`.

When a new event is published, the view queues event IDs and either:
- loads and prepends them immediately when the user is near top of page, or
- shows a banner `Nove udalosti (N) - klikni pre nacitanie` and loads them on click.
