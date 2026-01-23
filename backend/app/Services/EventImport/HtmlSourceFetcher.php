<?php

namespace App\Services\EventImport;

use Illuminate\Support\Facades\Http;

class HtmlSourceFetcher
{
    public function fetch(string $url): string
    {
        // Podpora lokálneho súboru: file://C:/path/to/file.html
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);
            return file_get_contents($path) ?: '';
        }

        return Http::withoutVerifying()
            ->accept('text/html')
            ->get($url)
            ->throw()
            ->body();
    }
}
