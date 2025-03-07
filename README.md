# This package contains a Laravel Health check that can report any outdated PHP packages installed in your application using Composer.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wizcodepl/outdated-packages-health-check.svg?style=flat-square)](https://packagist.org/packages/wizcodepl/outdated-packages-health-check)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/wizcodepl/outdated-packages-health-check/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/wizcodepl/outdated-packages-health-check/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/wizcodepl/outdated-packages-health-check/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/wizcodepl/outdated-packages-health-check/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wizcodepl/outdated-packages-health-check.svg?style=flat-square)](https://packagist.org/packages/wizcodepl/outdated-packages-health-check)


This package contains a Laravel Health check that can report any outdated PHP packages installed in your application using Composer.

## About us
Wizcode builds expandable MVPs with lightning-speed development solutions. We create scalable web platforms, mobile apps, and IoT solutions. Check for more: https://wizcode.pl

## Installation

You can install the package via composer:

```bash
composer require wizcodepl/outdated-packages-health-check
```

## Usage

```bash
use Spatie\Health\Facades\Health;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;

Health::checks([
    OutdatedPackagesCheck::new()
        ->direct()
        ->includeDev()
        ->composerPath('/path/to/composer.json'),
]);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jakub Szcześniak](https://github.com/:jakub-wizcode)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
