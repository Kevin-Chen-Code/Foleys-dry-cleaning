# Foley's Dry Cleaning Portal

A CakePHP application for barristers to register dry-cleaning items for collection.

## Local development

The local configuration uses SQLite at `tmp/foleys.sqlite`. Initialise the schema and start the application with:

```sh
bin/cake migrations migrate
bin/cake server
```

Open `http://localhost:8765` in a browser. The public request form will show a catalogue notice until approved service items and prices are added.

## Foundation included

- Service-item, discount-rule, order, and order-item database tables.
- A public collection request form that supports multiple item quantities.
- Server-side price calculation and percentage or fixed-value discounts.
- A request-received confirmation screen.
- SQLite for local development; production credentials remain external configuration.

## Still to build

- Secure administrator sign-in and screens to manage catalogue items, prices, and discounts.
- Email notification to the collection team.
- Weekly reporting, CSV export, and scheduled report email.
- The confirmed brand design and deployment configuration.

![Build Status](https://github.com/cakephp/app/actions/workflows/ci.yml/badge.svg?branch=5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/app.svg?style=flat-square)](https://packagist.org/packages/cakephp/app)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

A skeleton for creating applications with [CakePHP](https://cakephp.org) 5.x.

The framework source code can be found here: [cakephp/cakephp](https://github.com/cakephp/cakephp).

## Installation

1. Download [Composer](https://getcomposer.org/doc/00-intro.md) or update `composer self-update`.
2. Run `php composer.phar create-project --prefer-dist cakephp/app [app_name]`.

If Composer is installed globally, run

```bash
composer create-project --prefer-dist cakephp/app
```

In case you want to use a custom app dir name (e.g. `/myapp/`):

```bash
composer create-project --prefer-dist cakephp/app myapp
```

You can now either use your machine's webserver to view the default home page, or start
up the built-in webserver with:

```bash
bin/cake server -p 8765
```

Then visit `http://localhost:8765` to see the welcome page.

## Demo app

Check out the [5.x-demo branch](https://github.com/cakephp/app/tree/5.x-demo), which contains demo migrations and a seeder.
See the [README](https://github.com/cakephp/app/blob/5.x-demo/README.md) on how to get it running.

## Update

Since this skeleton is a starting point for your application and various files
would have been modified as per your needs, there isn't a way to provide
automated upgrades, so you have to do any updates manually.

## Configuration

Read and edit the environment specific `config/app_local.php` and set up the
`'Datasources'` and any other configuration relevant for your application.
Other environment agnostic settings can be changed in `config/app.php`.

## Layout

The app skeleton uses [Milligram](https://milligram.io/) (v1.3) minimalist CSS
framework by default. You can, however, replace it with any other library or
custom styles.

## Contributors

Andrew Turner (CEO):
Strategic vision and quality control

Amanda Tougher (Administration manager):
Strategic vision and quality control

Kevin Chen (IT business analyst)
Project manager and developer
