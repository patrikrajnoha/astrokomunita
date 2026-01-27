<?php

namespace App\Services\EventImport;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HtmlSourceFetcher
{
    public function fetch(string $url): string
    {
        // Podpora lokálneho súboru: file://C:/path/to/file.html (Windows)
        // Podpora aj: file:///C:/path/to/file.html
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);     // odstráň "file://"
            $path = ltrim($path, '/');   // ak je file:///C:/..., odstráň úvodné "/"
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_file($path)) {
                throw new RuntimeException("Local file not found: {$path}");
            }

            $content = @file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Cannot read local file: {$path}");
            }

            return $content;
        }

        return Http::withoutVerifying()
            ->accept('text/html')
            ->get($url)
            ->throw()
            ->body();
    }
}
