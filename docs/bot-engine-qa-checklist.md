# Bot Engine Admin QA Checklist

## Safe Mode Flow (3 Click)
1. Open `Admin -> Bot Engine`.
2. In Sources, run `Dry run` for `nasa_rss_breaking`.
3. Open latest run `Detail`, click `Preview` on one item, then `Publish` on that item.

## Publish All Limit
1. In the same run detail, set `Limit` to `3`.
2. Click `Publish all`.
3. Confirm toast summary and refreshed item statuses.

## Source Runs
1. Run `nasa_apod_daily` with `Run now`.
2. Run `wiki_onthisday_astronomy` with `Run now` or `Dry run` and publish manually from run detail.

## AstroFeed Verification
1. Open `AstroFeed`.
2. Verify bot badge, source label, source attribution, and source link.
3. Verify `Originál/Preklad` toggle on translated bot posts.

## Admin Audit Verification
1. Open run detail items (`/api/admin/bots/items?run_id=...` path through UI).
2. Confirm `used_translation`, `skip_reason`, `post_id`, `published_manually`, and `manual_published_at`.
