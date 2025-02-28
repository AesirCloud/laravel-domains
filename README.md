# aesircloud/laravel-domains

---

A Laravel package to scaffold Domain-Driven Design (DDD) structures in your Laravel projects. This package creates a complete suite of files—domain entities, value objects, repositories, domain services, models (with optional soft deletes), factories, observers, policies, and even migrations—so you can quickly get started with a DDD approach.

---

<p align="center">
<a href="https://github.com/aesircloud/laravel-domains/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/aesircloud/laravel-domains/test.yml?branch=main&style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-domains" target="_blank"><img src="https://img.shields.io/packagist/v/aesircloud/laravel-domains.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-domains" target="_blank"><img src="https://img.shields.io/packagist/dt/aesircloud/laravel-domains.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-domains" target="_blank"><img src="https://img.shields.io/packagist/l/aesircloud/laravel-domains.svg?style=flat-square"/></a>
</p>

## FEATURES

- **Domain Scaffolding**
  Automatically creates directories and stub files for a new domain.

- **Model Generation**
  Generates a domain model extending your BaseModel with optional soft delete support.

- **Factory, Observer, & Policy**
  Generates a factory, observer, and policy for your domain.

- **Optional Migrations**
  Create migrations (with or without soft deletes) for your domain’s table.

- **Repository Binding**
  Automatically updates your RepositoryServiceProvider with the new domain’s repository binding.

- **CRUD Actions**
  Generates a full set of CRUD actions (Create, Update, Delete, Index, Show) using `aesircloud/laravel-actions`.

- **Optional Soft Delete Actions**
  When the domain uses soft deletes, additional Restore and ForceDelete actions are generated.

- **Value Object Generation**
  Use the make:value-object command to scaffold a new value object. Optionally specify a domain to place the value object within that domain’s folder.

- **Interactive Prompts**
  If a file already exists, you'll be prompted (defaulting to replace) so you can control file overwrites.

- **--force Option**
  Overwrite existing files without being prompted.

- **Customizable Stubs**
  Publish the package stubs for customization.

## INSTALLATION

### Install the package via Composer:

```bash
  composer require aesircloud/laravel-domains
```

Laravel’s package auto-discovery will register the service provider automatically. If you need to manually register it, add the following to your `config/app.php` providers array:

```php 
AesirCloud\LaravelDomains\Providers\DomainServiceProvider::class,
```

## PUBLISHING STUBS

To customize the stub files used for scaffolding, publish the package stubs:

```php
php artisan vendor:publish --tag=ddd-stubs
```

This will copy all stub files into `stubs/laravel-domains` in your project, where you can modify them as you wish.

## USAGE

To scaffold a new domain, run the following command:

```php
php artisan make:domain {DomainName} [--migration] [--soft-deletes] [--force]
```

### HOW DOMAIN NAMING WORKS

- Class Names: DomainName is automatically converted to StudlyCase.
- Table Names: The table name is derived from your raw domain input, converted to snake_case and pluralized.
  Example: php artisan make:domain user_profile
  - Domain class name: UserProfile
  - Table name: user_profiles

### BASIC EXAMPLES

1) Basic Domain Creation
   ```php
   php artisan make:domain User
   ```
   Creates domain files under `app/Domains/User/`
   Generates a User model, factory, observer, policy, DTO, repository interface, and domain service.

2) Domain with Migration
   ```php
   php artisan make:domain User --migration
   ```
   Also creates a migration in the `database/migrations` folder.

3) Domain with Soft Deletes
   ```php
   php artisan make:domain User --soft-deletes
   ```
   Model, observer, policy, repository, and actions will include soft-delete logic.

4) Domain with Migration and Soft Deletes
   ```php
   php artisan make:domain User --migration --soft-deletes
   ```

5) Force Overwrite of Existing Files
   ```php
   php artisan make:domain User --force
   ```
   Automatically overwrites any existing files without prompting.

### The command will:
- Create domain directories (e.g., `app/Domains/User/Entities`, `Repositories`, `DomainServices`).
- Generate stub files for Entity, Repository Interface, and Domain Service.
- Create a BaseModel (if not already present) and a domain-specific model in `app/Models` (using soft delete logic if selected).
- Create a DTO file using spatie/laravel-data in `app/Domains/User/DataTransferObjects`.
- Create a factory in `database/factories`.
- Create an observer in `app/Observers` and a policy in `app/Policies`.
- Optionally generate a migration file (using stubs from `stubs/model`).
- Update the RepositoryServiceProvider with the binding for the new domain’s repository interface and its concrete implementation.
- Generate CRUD actions (Create, Update, Delete, Index, Show), with optional Restore and ForceDelete actions if soft deletes are enabled.

### MAKING A SUBDOMAIN

To create a subdomain within an existing domain, use the `make:subdomain` command:

```php
php artisan make:subdomain {ParentDomain} {SubdomainName} [--migration] [--soft-deletes] [--force]
```

#### EXAMPLES

Example: Under the 'User' domain, create a 'AuthenticationLogs' subdomain
```php
php artisan make:subdomain User AuthenticationLogs --migration --soft-deletes
```

- Also creates actions in app/Actions/{ParentDomain}/{SubdomainName}, e.g. app/Actions/User/AuthenticationLogs/DeleteAuthenticationLogAction.php.
- Binds the repository to RepositoryServiceProvider.

***NOTE:*** The parent domain (e.g. app/Domains/User) must already exist before you can add a subdomain within it.
- You can make a folder in the `app/Domains/<domain>` directory to represent a parent domain that you do not need to scaffold. E.g., `app/Domains/HumanResources`. Then you can create subdomains within that folder.
- **Example:** `php artisan make:subdomain HumanResources Payroll --migration --soft-deletes`

### MAKING A VALUE OBJECT

You can also generate a value object, optionally scoping it to a domain:

```php
php artisan make:value-object Address
```

Creates a file named AddressValueObject.php in `app/ValueObjects`.

Or specify a domain:

```php
php artisan make:value-object Address --domain=User
```

Or specify a domain and subdomain:

```php
php and artisan make:value-object Check --domain=HumanResources --sub-domain=Payroll 
```

You can also use the `--force` option to overwrite existing files:

```php
php artisan make:value-object Address --force
```

## REQUIREMENTS

- PHP: 8.3 or higher
- Laravel: 11.42 or higher
- illuminate/console: 11.42 or higher
- illuminate/support: 11.42.1 or higher
- spatie/laravel-data: 4.13 or higher
- aesircloud/sluggable: 1.1.0 or higher
- aesircloud/laravel-actions: 1.0.0 or higher

## DESIGN AND DEVELOPMENT STANDARDS

Please see the [Standards & Pattern Philosophy](STANDARDS.md) file for the design and development standards used in this package.

## Security

If you've found a bug regarding security please mail [security@aesircloud.com](mailto:security@aesircloud.com) instead of using the issue tracker.

## LICENSE

The MIT License (MIT). Please see [License](LICENSE.md) file for more information.
