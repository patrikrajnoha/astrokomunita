<?php

namespace App\Services\Observing;

use Carbon\CarbonImmutable;

class MeteorShowerService
{
    private const DATA_FILE = 'resources/data/meteor_showers.json';

    /**
     * @return array<int,array{id:string,name:string,active_today:bool,peak_date:string,peak_in_days:int,zhr:int|null}>
     */
    public function activeForDate(string $date): array
    {
        $targetDate = CarbonImmutable::createFromFormat('Y-m-d', $date, 'UTC');

        $items = [];

        foreach ($this->allShowers() as $shower) {
            $start = (string) ($shower['active_start'] ?? '');
            $end = (string) ($shower['active_end'] ?? '');
            $peak = (string) ($shower['peak_date'] ?? '');

            if (!$this->isValidMmDd($start) || !$this->isValidMmDd($end) || !$this->isValidMmDd($peak)) {
                continue;
            }

            $isActive = $this->isActiveOnDate($targetDate, $start, $end);
            if (!$isActive) {
                continue;
            }

            $items[] = [
                'id' => (string) ($shower['id'] ?? ''),
                'name' => (string) ($shower['name'] ?? ''),
                'active_today' => true,
                'peak_date' => $peak,
                'peak_in_days' => $this->peakDiffDays($targetDate, $peak),
                'zhr' => isset($shower['zhr']) && is_numeric($shower['zhr']) ? (int) $shower['zhr'] : null,
            ];
        }

        return array_values(array_filter($items, static fn (array $item) => $item['id'] !== '' && $item['name'] !== ''));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function allShowers(): array
    {
        $path = base_path(self::DATA_FILE);

        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isActiveOnDate(CarbonImmutable $date, string $startMmDd, string $endMmDd): bool
    {
        $year = $date->year;

        $startCurrentYear = $this->mmDdToDate($year, $startMmDd);
        $endCurrentYear = $this->mmDdToDate($year, $endMmDd);
        if (!$startCurrentYear || !$endCurrentYear) {
            return false;
        }

        if ($startCurrentYear->lessThanOrEqualTo($endCurrentYear)) {
            return $date->between($startCurrentYear, $endCurrentYear, true);
        }

        $endNextYear = $this->mmDdToDate($year + 1, $endMmDd);
        $startPreviousYear = $this->mmDdToDate($year - 1, $startMmDd);
        if (!$endNextYear || !$startPreviousYear) {
            return false;
        }

        return $date->between($startCurrentYear, $endNextYear, true)
            || $date->between($startPreviousYear, $endCurrentYear, true);
    }

    private function peakDiffDays(CarbonImmutable $date, string $peakMmDd): int
    {
        $candidateDates = [
            $this->mmDdToDate($date->year - 1, $peakMmDd),
            $this->mmDdToDate($date->year, $peakMmDd),
            $this->mmDdToDate($date->year + 1, $peakMmDd),
        ];

        $best = null;
        foreach ($candidateDates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $diff = $date->diffInDays($candidate, false);
            if ($best === null || abs($diff) < abs($best)) {
                $best = $diff;
            }
        }

        return (int) ($best ?? 0);
    }

    private function isValidMmDd(string $value): bool
    {
        if (!preg_match('/^\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$month, $day] = explode('-', $value);

        return checkdate((int) $month, (int) $day, 2024);
    }

    private function mmDdToDate(int $year, string $mmDd): ?CarbonImmutable
    {
        [$month, $day] = explode('-', $mmDd);

        if (!checkdate((int) $month, (int) $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, (int) $month, (int) $day, 0, 0, 0, 'UTC');
    }
}
