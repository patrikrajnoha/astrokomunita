<?php

namespace App\Console\Commands;

use App\Models\RssItem;
use App\Services\AstroBotPublisher;
use Illuminate\Console\Command;

class AstroBotPublishPending extends Command
{
    protected $signature = 'astrobot:publish-pending {--count=3}';
    protected $description = 'Publish pending RSS items as posts';

    public function __construct(private AstroBotPublisher $publisher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->option('count');
        
        $this->info("Publishing up to {$count} pending RSS items...");
        
        $items = RssItem::where('status', 'pending')
            ->limit($count)
            ->get();
            
        if ($items->isEmpty()) {
            $this->info('No pending items found.');
            return Command::SUCCESS;
        }

        $published = 0;
        foreach ($items as $item) {
            try {
                $this->publisher->publish($item);
                $this->line("✓ Published: {$item->title}");
                $published++;
            } catch (\Throwable $e) {
                $this->error("✗ Failed to publish {$item->title}: {$e->getMessage()}");
            }
        }

        $this->info("Published {$published} items successfully.");
        return Command::SUCCESS;
    }
}
