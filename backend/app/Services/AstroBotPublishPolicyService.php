<?php

namespace App\Services;

use App\Models\RssItem;

class AstroBotPublishPolicyService
{
    public const DECISION_PUBLISH = 'publish';
    public const DECISION_NEEDS_REVIEW = 'needs_review';

    public function evaluate(RssItem $item): string
    {
        $link = trim((string) $item->url);
        $title = strtolower(trim((string) $item->title));
        $summary = strtolower(trim((string) ($item->summary ?? '')));

        if ($link === '' || $item->published_at === null) {
            return self::DECISION_NEEDS_REVIEW;
        }

        if ($this->violatesDomainWhitelist($link)) {
            return self::DECISION_NEEDS_REVIEW;
        }

        $riskKeywords = (array) config('astrobot.risk_keywords', []);
        foreach ($riskKeywords as $keyword) {
            $needle = strtolower(trim((string) $keyword));
            if ($needle === '') {
                continue;
            }

            if (str_contains($title, $needle) || str_contains($summary, $needle)) {
                return self::DECISION_NEEDS_REVIEW;
            }
        }

        $maxAgeDays = max(1, (int) config('astrobot.max_age_days', 30));
        if ($item->published_at->lt(now()->subDays($maxAgeDays))) {
            return self::DECISION_NEEDS_REVIEW;
        }

        return self::DECISION_PUBLISH;
    }

    private function violatesDomainWhitelist(string $link): bool
    {
        $whitelist = array_values(array_filter(array_map(
            static fn ($host): string => strtolower(trim((string) $host)),
            (array) config('astrobot.domain_whitelist', [])
        )));

        if ($whitelist === []) {
            return false;
        }

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        if ($host === '') {
            return true;
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return ! in_array($host, $whitelist, true);
    }
}

