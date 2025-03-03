# Change Log
All notable changes to `aesircloud/laravel-domains` will be documented in this file.

---
## 2.0.4 - 2025-03-03
- **FIXED:** The `DomainService.stub` is missing the findBy method. This has been added.
- **REFACTORED:** The `DomainService.stub` has been split into two separate files. `DomainService.stub` & `DomainService.soft.stub`. The `MakeDomainCommand` and `MakeSubdomainCommand` files have been updated to reflect this change.

## 2.0.2-2.0.3 - 2025-03-02
- **FIXED:** The observer stubs were incorrectly using the `entity` class rather than the `model` class. This has been fixed.
- **FIXED:** The DataTransferObject stub was missing the constructor method. This has been added.
- **FIXED:** The UpdateOrCreate method was missing from the DomainService.stub. This has been added.

## 2.0.0-2.0.1 - 2025-02-28
- **ENHANCEMENT:** Removed dependency on `lorisleiva/laravel-actions` and replaced with our own `aesircloud/laravel-actions` package.
- **REFACTORED:** Refactored the `Create` and `Update` actions to properly convert the validated data to the correct DTO type before passing it to the domain.

## 1.0.1-1.0.3 - 2025-02-22 - 2025-02-23
- **FIXED** Somewhere we messed up the grammatical number of the classes and directories, e.g. singular vs. plural. We've fixed this now.
- **FIXED** Stubs not having the correct placeholders.
- **ENHANCEMENT:** Added support for laravel 12.x.

## 1.0.0 - 2025-02-23
- **ENHANCEMENT:** Added `make:subdomain` command to add subdomains to a domain.

## 0.2.2-0.2.5 - 2025-02-22
- **FIXED** Incorrect placeholders in stubs.
- **FIXED** Aligned the `make:domain` command to create infrastructure repository classes with the correct name.
- **FIXED** Missing `domainLower` placeholder in make:domain command.
- **FIXED** Updated the MakeDomainCommand to generate singular class names (e.g., User) while preserving plural table names (e.g., users). This aligns with typical Laravel conventions, ensuring that running php artisan make:domain Users will create a User.php model, but generate a users table in the migration.

## 0.2.1 - 2025-02-17
- **FIXED** Typo in `README.md`.

## 0.2.0 - 2025-02-17
- **ENHANCEMENT:** Replaced `spatie/laravel-sluggable` with our own `aesircloud/sluggable` package.

## 0.1.1 - 2025-02-17
- Add GitHub Actions for Testing

## 0.1.0 - 2025-02-17
- Initial release