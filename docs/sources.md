# Event Source Analysis

## Astropixels (Sky Event Almanac)
- Type: HTML almanac pages by year.
- URL pattern: `https://astropixels.com/almanac/almanac21/almanac%dcet.html`
- Parsing mode: yearly crawl (`events:crawl-astropixels`), normalized to event candidates.
- Suggested periodicity: daily incremental crawl + weekly consistency re-crawl.
- Licensing note: verify publisher terms for redistribution of event text; store source URL and original text for attribution.

## NASA RSS (News Feed)
- Type: RSS/Atom feed.
- URL: configured by `ASTROBOT_NASA_RSS_URL`.
- In-project flow: AstroBot RSS sync job (`AstroBotNasaSyncJob`) is the authoritative scheduler path.
- Suggested periodicity: hourly sync.
- Licensing note: NASA material is generally public-use in the U.S., but trademarks/branding/media exceptions still apply; keep source links and attribution.
