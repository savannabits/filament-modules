<p align="center">
    <a href="https://github.com/filamentphp/filament/actions"><img alt="Tests passing" src="https://img.shields.io/badge/Tests-passing-green?style=for-the-badge&logo=github"></a>
    <a href="https://laravel.com"><img alt="Laravel v9.x" src="https://img.shields.io/badge/Laravel-v9.x-FF2D20?style=for-the-badge&logo=laravel"></a>
    <a href="https://laravel-livewire.com"><img alt="Livewire v3.x" src="https://img.shields.io/badge/Livewire-v3.x-FB70A9?style=for-the-badge"></a>
    <a href="https://php.net"><img alt="PHP 8.1" src="https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php"></a>
</p>

Modules is a FilamentPHP Plugin to enable easy integration with `nwidart/laravel-modules`

**NB: These docs are for v3, which only supports Filament 3. If you are using Filament v2, [see the documentation here](https://github.com/savannabits/filament-modules/tree/main) to get started.**
## Usage

[![Latest Version on Packagist](https://img.shields.io/packagist/v/coolsam/modules.svg?style=flat-square)](https://packagist.org/packages/coolsam/modules)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/coolsam/modules/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/coolsam/modules/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/coolsam/modules/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/coolsam/modules/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/coolsam/modules.svg?style=flat-square)](https://packagist.org/packages/coolsam/modules)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/modules.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/modules)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require coolsam/modules
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="modules-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="modules-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="modules-views"
```

## Usage

```php
$modules = new Coolsam\Modules();
echo $modules->echoPhrase('Hello, Coolsam!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sam Maosa](https://github.com/coolsam726)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
