<?php

namespace App\Services\Crawlers\Astropixels;

use DomainException;
use Throwable;

class AstropixelsYearUnavailableException extends DomainException
{
    public function __construct(
        public readonly int $year,
        public readonly string $url,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'ASTROPIXELS_YEAR_UNAVAILABLE: Almanac pre rok %d zrejme este nie je publikovany (%s).',
                $year,
                $url
            ),
            previous: $previous
        );
    }
}
