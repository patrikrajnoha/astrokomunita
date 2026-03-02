<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_START = 'start';
    private const TYPE_PEAK = 'peak';
    private const TYPE_UNKNOWN = 'unknown';

    private const PRECISION_EXACT = 'exact';
    private const PRECISION_UNKNOWN = 'unknown';

    public function up(): void
    {
        $this->setUtcSessionTimezone();

        Schema::table('events', function (Blueprint $table): void {
            $table->dateTime('start_at')->nullable()->change();
            $table->dateTime('end_at')->nullable()->change();
        });

        Schema::table('manual_events', function (Blueprint $table): void {
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->enum('time_type', ['start', 'peak', 'window', 'unknown'])->default('start');
            $table->enum('time_precision', ['exact', 'approximate', 'unknown'])->default('exact');
        });

        Schema::table('event_candidates', function (Blueprint $table): void {
            $table->enum('time_type', ['start', 'peak', 'window', 'unknown'])->default('start');
            $table->enum('time_precision', ['exact', 'approximate', 'unknown'])->default('exact');
        });

        Schema::table('manual_events', function (Blueprint $table): void {
            $table->enum('time_type', ['start', 'peak', 'window', 'unknown'])->default('start');
            $table->enum('time_precision', ['exact', 'approximate', 'unknown'])->default('exact');
        });

        $this->backfillEvents();
        $this->backfillEventCandidates();
        $this->backfillManualEvents();
    }

    public function down(): void
    {
        $this->setUtcSessionTimezone();

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['time_type', 'time_precision']);
        });

        Schema::table('event_candidates', function (Blueprint $table): void {
            $table->dropColumn(['time_type', 'time_precision']);
        });

        Schema::table('manual_events', function (Blueprint $table): void {
            $table->dropColumn(['time_type', 'time_precision']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('start_at')->nullable()->change();
            $table->timestamp('end_at')->nullable()->change();
        });

        Schema::table('manual_events', function (Blueprint $table): void {
            $table->timestamp('starts_at')->change();
            $table->timestamp('ends_at')->nullable()->change();
        });
    }

    private function backfillEvents(): void
    {
        DB::table('events')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    [$timeType, $timePrecision] = $this->resolvePublishedRow($row);

                    DB::table('events')
                        ->where('id', $row->id)
                        ->update([
                            'time_type' => $timeType,
                            'time_precision' => $timePrecision,
                        ]);
                }
            });
    }

    private function backfillEventCandidates(): void
    {
        DB::table('event_candidates')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    [$timeType, $timePrecision] = $this->resolvePublishedRow($row);

                    DB::table('event_candidates')
                        ->where('id', $row->id)
                        ->update([
                            'time_type' => $timeType,
                            'time_precision' => $timePrecision,
                        ]);
                }
            });
    }

    private function backfillManualEvents(): void
    {
        DB::table('manual_events')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('manual_events')
                        ->where('id', $row->id)
                        ->update([
                            'time_type' => self::TYPE_START,
                            'time_precision' => self::PRECISION_EXACT,
                        ]);
                }
            });
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolvePublishedRow(object $row): array
    {
        $sourceName = strtolower(trim((string) ($row->source_name ?? '')));
        $startAt = $this->parseDate($row->start_at ?? null);
        $maxAt = $this->parseDate($row->max_at ?? null);

        if ($sourceName === 'manual') {
            return [self::TYPE_START, self::PRECISION_EXACT];
        }

        if (! $startAt && ! $maxAt) {
            return [self::TYPE_UNKNOWN, self::PRECISION_UNKNOWN];
        }

        if ($sourceName === 'imo') {
            return $this->isMidnightFallback($startAt, $maxAt)
                ? [self::TYPE_PEAK, self::PRECISION_UNKNOWN]
                : [self::TYPE_PEAK, self::PRECISION_EXACT];
        }

        if ($sourceName === 'astropixels') {
            return [self::TYPE_PEAK, self::PRECISION_EXACT];
        }

        if ($maxAt && (! $startAt || ! $this->sameMoment($startAt, $maxAt))) {
            return [self::TYPE_PEAK, self::PRECISION_EXACT];
        }

        return [self::TYPE_START, self::PRECISION_EXACT];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isMidnightFallback(?CarbonImmutable $startAt, ?CarbonImmutable $maxAt): bool
    {
        $primary = $maxAt ?? $startAt;
        if (! $primary) {
            return true;
        }

        if ($primary->format('H:i:s') !== '00:00:00') {
            return false;
        }

        if ($startAt && $startAt->format('H:i:s') !== '00:00:00') {
            return false;
        }

        return true;
    }

    private function sameMoment(CarbonImmutable $left, CarbonImmutable $right): bool
    {
        return $left->format('Y-m-d H:i:s.u') === $right->format('Y-m-d H:i:s.u');
    }

    private function setUtcSessionTimezone(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("SET time_zone = '+00:00'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("SET TIME ZONE 'UTC'");
        }
    }
};
