<?php

declare(strict_types=1);

use Spatie\Health\Enums\Status;
use WizcodePl\OutdatedPackagesHealthCheck\OutdatedPackagesHealthCheck;

/**
 * Test double that bypasses the real `composer outdated` call so the rest
 * of the check can be exercised in isolation. Kept inside the test file
 * (not as a tests/Doubles class) — it's only ever used here.
 */
class FakeOutdatedCheck extends OutdatedPackagesHealthCheck
{
    /** @var array<int, array<string, mixed>> */
    public array $fakeInstalled = [];

    public bool $shouldFail = false;

    protected function fetchOutdatedPackages(): ?array
    {
        if ($this->shouldFail) {
            return null;
        }

        return ['installed' => $this->fakeInstalled];
    }
}

// ---------------------------------------------------------------------------
// detectLevel — pure semver classification
// ---------------------------------------------------------------------------

it('classifies bumps by semver level', function (string $from, string $to, string $expected) {
    expect((new OutdatedPackagesHealthCheck)->detectLevel($from, $to))->toBe($expected);
})->with([
    'patch' => ['1.2.3', '1.2.4', OutdatedPackagesHealthCheck::LEVEL_PATCH],
    'minor' => ['1.2.3', '1.3.0', OutdatedPackagesHealthCheck::LEVEL_MINOR],
    'major' => ['1.2.3', '2.0.0', OutdatedPackagesHealthCheck::LEVEL_MAJOR],
    'v-prefix is stripped' => ['v1.2.3', 'v1.2.4', OutdatedPackagesHealthCheck::LEVEL_PATCH],
    'pre-release-ish core same → unknown' => ['1.2.3-rc1', '1.2.3', OutdatedPackagesHealthCheck::LEVEL_UNKNOWN],
    'dev branch is unknown' => ['dev-main', '1.0.0', OutdatedPackagesHealthCheck::LEVEL_UNKNOWN],
    'major bump from prerelease' => ['2.0.0-beta1', '3.0.0', OutdatedPackagesHealthCheck::LEVEL_MAJOR],
]);

// ---------------------------------------------------------------------------
// alert level filtering
// ---------------------------------------------------------------------------

it('returns ok when no packages outdated', function () {
    $check = new FakeOutdatedCheck;
    $check->fakeInstalled = [];

    $result = $check->run();

    expect($result->status)->toBe(Status::ok());
});

it('returns warning by default when ANY level has updates', function () {
    $check = new FakeOutdatedCheck;
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => '1.0.0', 'latest' => '1.0.1'], // patch
    ];

    $result = $check->run();

    expect($result->status)->toBe(Status::warning());
});

it('ignores patch updates when only major is alerted', function () {
    $check = (new FakeOutdatedCheck)->onlyMajor();
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => '1.0.0', 'latest' => '1.0.5'], // patch
        ['name' => 'vendor/b', 'version' => '1.0.0', 'latest' => '1.4.0'], // minor
    ];

    $result = $check->run();

    expect($result->status)->toBe(Status::ok())
        ->and($result->meta['outdated_total'])->toBe(2)
        ->and($result->meta['outdated_by_level']['patch'])->toBe(1)
        ->and($result->meta['outdated_by_level']['minor'])->toBe(1)
        ->and($result->meta['outdated_by_level']['major'])->toBe(0);
});

it('alerts on major when only major is enabled', function () {
    $check = (new FakeOutdatedCheck)->onlyMajor();
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => '1.0.0', 'latest' => '1.0.5'], // patch (ignored)
        ['name' => 'vendor/b', 'version' => '1.0.0', 'latest' => '2.0.0'], // major
    ];

    $result = $check->run();

    expect($result->status)->toBe(Status::warning())
        ->and($result->meta['alerting_packages'])->toHaveCount(1)
        ->and($result->meta['alerting_packages'][0]['name'])->toBe('vendor/b');
});

it('minorAndAbove ignores patch but alerts on minor', function () {
    $check = (new FakeOutdatedCheck)->minorAndAbove();
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => '1.0.0', 'latest' => '1.0.5'], // patch (ignored)
        ['name' => 'vendor/b', 'version' => '1.0.0', 'latest' => '1.5.0'], // minor (alerts)
    ];

    $result = $check->run();

    expect($result->status)->toBe(Status::warning())
        ->and($result->meta['alerting_packages'])->toHaveCount(1)
        ->and($result->meta['alerting_packages'][0]['name'])->toBe('vendor/b');
});

it('treats unparseable versions as major (alerts under onlyMajor)', function () {
    $check = (new FakeOutdatedCheck)->onlyMajor();
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => 'dev-main', 'latest' => '1.0.0'],
    ];

    $result = $check->run();

    expect($result->status)->toBe(Status::warning())
        ->and($result->meta['alerting_packages'][0]['level'])->toBe(OutdatedPackagesHealthCheck::LEVEL_UNKNOWN);
});

it('returns failed when composer outdated cannot be read', function () {
    $check = new FakeOutdatedCheck;
    $check->shouldFail = true;

    $result = $check->run();

    expect($result->status)->toBe(Status::failed());
});

it('preserves all outdated packages in meta even when not alerting on them', function () {
    $check = (new FakeOutdatedCheck)->onlyMajor();
    $check->fakeInstalled = [
        ['name' => 'vendor/a', 'version' => '1.0.0', 'latest' => '1.0.5'],
        ['name' => 'vendor/b', 'version' => '1.0.0', 'latest' => '1.4.0'],
        ['name' => 'vendor/c', 'version' => '1.0.0', 'latest' => '2.0.0'],
    ];

    $result = $check->run();

    expect($result->meta['outdated_total'])->toBe(3)
        ->and($result->meta['alerting_packages'])->toHaveCount(1);
});
