# Changelog

All notable changes to `outdated-packages-health-check` will be documented in this file.

## 1.0.4 - 2026-05-06

### Added
- `alertOnLevels(array $levels)` to restrict the check to specific semver bump levels — only alert when a package has a major / minor / patch update available, not on every release.
- Shortcut helpers: `onlyMajor()` (major + unknown) and `minorAndAbove()` (major + minor + unknown).
- Public `LEVEL_MAJOR` / `LEVEL_MINOR` / `LEVEL_PATCH` / `LEVEL_UNKNOWN` constants on `OutdatedPackagesCheck`.
- `meta` payload now contains `outdated_total`, `outdated_by_level`, `alert_levels` and a structured `alerting_packages` list.

### Fixed
- `tests/Pest.php` referenced a non-existent `Wizcode\…\TestCase`. Added `tests/TestCase.php` (Orchestra Testbench-based) and corrected the namespace.
- `phpunit.xml.dist` test suite renamed from "Wizcode Test Suite" to "WizcodePl Test Suite".

### Backwards compatibility
- Default behaviour unchanged — without configuring `alertOnLevels()`, the check still warns on any outdated package.
