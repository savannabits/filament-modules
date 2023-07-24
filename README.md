<p align="center">
    <a href="https://github.com/savannabits/filament-modules/actions?query=workflow%3Arun-tests+branch%3A3.x"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/savannabits/filament-modules/run-tests.yml?branch=3.x&label=tests&style=for-the-badge&logo=github"></a>
    <a href="https://github.com/savannabits/filament-modules/actions?query=workflow%fix-php-code-style-issues+branch%3A3.x"><img alt="Styling" src="https://img.shields.io/github/actions/workflow/status/savannabits/filament-modules/fix-php-code-style-issues.yml?branch=3.x&label=code%20style&style=for-the-badge&logo=github"></a>
    <a href="https://laravel.com"><img alt="Laravel v9.x" src="https://img.shields.io/badge/Laravel-v9.x-FF2D20?style=for-the-badge&logo=laravel"></a>
    <a href="https://beta.filamentphp.com"><img alt="Filament v3.x" src="https://img.shields.io/badge/FilamentPHP-v3.x-FB70A9?style=for-the-badge&logo=filament"></a>
    <a href="https://php.net"><img alt="PHP 8.1" src="https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php"></a>
    <a href="https://packagist.org/packages/coolsam/modules"><img alt="Packagist" src="https://img.shields.io/packagist/dt/coolsam/modules.svg?style=for-the-badge&logo=home"></a>
</p>

Modules is a FilamentPHP Plugin to enable easy integration with `nwidart/laravel-modules`

**NB: These docs are for v3, which only supports Filament 3. If you are using Filament
v2, [see the documentation here](https://github.com/savannabits/filament-modules/tree/main#readme) to get started.**

## Installation

Requirements:

1. Filament >= 3
2. PHP >= 8.1
3. Laravel >= 9.0
4. Livewire >= 3.0
5. lwidart/laravel-modules >=10.0

## Installation

- Ensure you have insalled and configured [Laravel Modules (follow these instructions)]()
- Ensure you have installed and configured Filamentphp (follow these instructions)
- You can now install the package via composer:

```bash
composer require coolsam/modules
```

## Usage

In this guide we are going to use the `Blog module` as an example

### Create your laravel module:
If the module that you want to work on does not exist, create it using nwidart/laravel-modules

```bash
php artisan module:make Blog # Create the blog module
```

### Generate a new Panel inside your module

```bash
php artisan module:make-filament-panel admin Blog # php artisan module:make-filament-panel [id] [module]
```
If none of the two arguments are passed, the command will ask for each of them interactively.
In this example, if the Panel id passed is `admin` and the module is blog, the command will generate a panel with
id `blog::admin`. This ID should be used in the next step when generating resources, pages and widgets.

### Generate your resources, pages and widgets as usual, selecting the panel you just created above.
From here on, use filament as you would normally to generate `resources`, `Pages` and `Widgets`. Be sure to specify the `--panel` option as the ID generated earlier.
If the `--panel` option is not passed, the command will ask for it interactively.
```bash
# For each of these commands, the package will ask for the Model and Panel.
php artisan make:filament-resource
php artisan make:filament-page
php artisan make:filament-widget
```

```bash
# The Model and Panel arguments are passed inline
php artisan make:filament-resource Author blog::admin
php artisan make:filament-page Library blog::admin
php artisan make:filament-widget BookStats blog::admin
```

**All Done!** For each of the panels generated, you can navigate to your `module-path/panel-path` e.g `blog/admin` to acess your panel and links to resources and pages.
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
