<?php

namespace App\Services\EventImport;

use Illuminate\Support\Facades\Http;

class HtmlSourceFetcher
{
    public function fetch(string $url): string
    {
        return Http::accept('text/html')
            ->get($url)
            ->throw()
            ->body();
    }
}
