# Use Filamentphp with nwiDart/laravel-modules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/savannabits/filament-modules.svg?style=flat-square)](https://packagist.org/packages/savannabits/filament-modules)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/savannabits/filament-modules/run-tests?label=tests)](https://github.com/savannabits/filament-modules/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/savannabits/filament-modules/Check%20&%20fix%20styling?label=code%20style)](https://github.com/savannabits/filament-modules/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/savannabits/filament-modules.svg?style=flat-square)](https://packagist.org/packages/savannabits/filament-modules)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require savannabits/filament-modules
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-modules-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-modules-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-modules-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$filament-modules = new Savannabits\FilamentModules();
echo $filament-modules->echoPhrase('Hello, Savannabits!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sam Maosa](https://github.com/savannabits)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
