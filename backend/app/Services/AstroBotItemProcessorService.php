<?php

namespace App\Services;

use App\Models\RssItem;

class AstroBotItemProcessorService
{
    public function __construct(
        private readonly AstroBotPublishPolicyService $policyService,
        private readonly AstroBotPublisher $publisher,
    ) {
    }

    /**
     * @return 'published'|'needs_review'|'rejected'|'skipped'
     */
    public function process(RssItem $item): string
    {
        if ($item->status === RssItem::STATUS_REJECTED) {
            return 'rejected';
        }

        if ($item->status === RssItem::STATUS_PUBLISHED && $item->post_id) {
            return 'skipped';
        }

        if (! (bool) config('astrobot.auto_publish_enabled', true)) {
            if ($item->status !== RssItem::STATUS_NEEDS_REVIEW) {
                $item->update(['status' => RssItem::STATUS_NEEDS_REVIEW]);
            }
            return 'needs_review';
        }

        $decision = $this->policyService->evaluate($item);
        if ($decision === AstroBotPublishPolicyService::DECISION_PUBLISH) {
            $this->publisher->publish($item);
            return 'published';
        }

        if ($item->status !== RssItem::STATUS_NEEDS_REVIEW) {
            $item->update(['status' => RssItem::STATUS_NEEDS_REVIEW]);
        }

        return 'needs_review';
    }
}

