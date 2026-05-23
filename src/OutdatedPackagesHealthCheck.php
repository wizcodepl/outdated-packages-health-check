<?php

declare(strict_types=1);

namespace WizcodePl\OutdatedPackagesHealthCheck;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Symfony\Component\Process\Process;

class OutdatedPackagesHealthCheck extends Check
{
    public const LEVEL_MAJOR = 'major';

    public const LEVEL_MINOR = 'minor';

    public const LEVEL_PATCH = 'patch';

    /**
     * Bumps we cannot classify (dev branches, mismatched prerelease tags, etc.)
     * are bucketed here. Treated as MAJOR by default — better to alarm wrongly
     * than to silently miss a real upgrade.
     */
    public const LEVEL_UNKNOWN = 'unknown';

    protected string $composerPath = 'composer';

    protected bool $direct = false;

    protected bool $includeDev = false;

    /** @var list<string> */
    protected array $alertLevels = [
        self::LEVEL_MAJOR,
        self::LEVEL_MINOR,
        self::LEVEL_PATCH,
    ];

    public function direct(bool $direct = true): self
    {
        $this->direct = $direct;

        return $this;
    }

    public function includeDev(bool $includeDev = true): self
    {
        $this->includeDev = $includeDev;

        return $this;
    }

    public function composerPath(string $path): self
    {
        $this->composerPath = $path;

        return $this;
    }

    /**
     * Restrict the check to alert only on the listed bump levels.
     *
     * Levels not in the list are still surfaced in `meta` (so dashboards can
     * show full picture), but they don't drive the check status. Useful when
     * you only care about majors but want to see patch updates anyway.
     *
     * @param  list<string>  $levels  any of LEVEL_MAJOR / LEVEL_MINOR / LEVEL_PATCH / LEVEL_UNKNOWN
     */
    public function alertOnLevels(array $levels): self
    {
        $this->alertLevels = array_values(array_unique($levels));

        return $this;
    }

    public function onlyMajor(): self
    {
        return $this->alertOnLevels([self::LEVEL_MAJOR, self::LEVEL_UNKNOWN]);
    }

    public function minorAndAbove(): self
    {
        return $this->alertOnLevels([self::LEVEL_MAJOR, self::LEVEL_MINOR, self::LEVEL_UNKNOWN]);
    }

    public function run(): Result
    {
        $result = Result::make();

        $output = $this->fetchOutdatedPackages();

        if ($output === null) {
            return $result->failed('Failed to read composer outdated output.');
        }

        $classified = $this->classify($output['installed']);

        $alerting = array_values(array_filter(
            $classified,
            fn (array $pkg): bool => in_array($pkg['level'], $this->alertLevels, true),
        ));

        $alertCount = count($alerting);
        $totalOutdated = count($classified);

        $byLevel = [
            self::LEVEL_MAJOR => 0,
            self::LEVEL_MINOR => 0,
            self::LEVEL_PATCH => 0,
            self::LEVEL_UNKNOWN => 0,
        ];
        foreach ($classified as $pkg) {
            $byLevel[$pkg['level']]++;
        }

        $result = $result->meta([
            'outdated_total' => $totalOutdated,
            'outdated_by_level' => $byLevel,
            'alert_levels' => $this->alertLevels,
            'alerting_packages' => $alerting,
        ]);

        if ($alertCount === 0) {
            $totalOutdated > 0
                ? $message = sprintf('%d outdated, none in alert levels (%s).', $totalOutdated, implode(',', $this->alertLevels))
                : $message = 'All packages are up to date.';

            return $result->ok($message)->shortSummary($message);
        }

        $summary = sprintf('%d outdated (%s)', $alertCount, $this->summarizeLevels($byLevel));

        return $result->warning($summary)->shortSummary($summary);
    }

    /**
     * @return array{installed: array<int, array<string, mixed>>}|null
     */
    protected function fetchOutdatedPackages(): ?array
    {
        $command = [$this->composerPath, 'outdated', '--format=json'];

        if ($this->direct) {
            $command[] = '--direct';
        }

        if (! $this->includeDev) {
            $command[] = '--no-dev';
        }

        $process = new Process($command, base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        /** @var array{installed?: array<int, array<string, mixed>>}|null $decoded */
        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            return null;
        }

        return ['installed' => $decoded['installed'] ?? []];
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @return list<array{name: string, version: string, latest: string, level: string}>
     */
    protected function classify(array $packages): array
    {
        $out = [];
        foreach ($packages as $pkg) {
            $name = (string) ($pkg['name'] ?? '');
            $version = (string) ($pkg['version'] ?? '');
            $latest = (string) ($pkg['latest'] ?? '');

            if ($name === '' || $version === '' || $latest === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'version' => $version,
                'latest' => $latest,
                'level' => $this->detectLevel($version, $latest),
            ];
        }

        return $out;
    }

    public function detectLevel(string $from, string $to): string
    {
        $f = $this->parseSemver($from);
        $t = $this->parseSemver($to);

        if ($f === null || $t === null) {
            return self::LEVEL_UNKNOWN;
        }

        if ($f[0] !== $t[0]) {
            return self::LEVEL_MAJOR;
        }

        if ($f[1] !== $t[1]) {
            return self::LEVEL_MINOR;
        }

        if ($f[2] !== $t[2]) {
            return self::LEVEL_PATCH;
        }

        // Same numeric core but different suffix (e.g. 1.2.3-rc1 → 1.2.3) —
        // can't be sure what the change means. Bucket as unknown.
        return self::LEVEL_UNKNOWN;
    }

    /**
     * Strip a leading `v`, drop everything after the first `-` / `+` (prerelease
     * & build metadata) and require the remainder to be `MAJOR.MINOR.PATCH`
     * with all-numeric segments.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseSemver(string $version): ?array
    {
        $core = ltrim($version, 'vV');
        $core = preg_replace('/[-+].*$/', '', $core) ?? '';

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $core, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    /**
     * @param  array<string, int>  $byLevel
     */
    private function summarizeLevels(array $byLevel): string
    {
        $parts = [];
        foreach ($byLevel as $level => $count) {
            if ($count > 0 && in_array($level, $this->alertLevels, true)) {
                $parts[] = "{$count} {$level}";
            }
        }

        return $parts === [] ? '0' : implode(', ', $parts);
    }
}
