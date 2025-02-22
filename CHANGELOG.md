# Change Log
All notable changes to `laravel-domains` will be documented in this file.

## 0.2.1 - 2025-02-22
- **FIXED** Updated the MakeDomainCommand to generate singular class names (e.g., User) while preserving plural table names (e.g., users). This aligns with typical Laravel conventions, ensuring that running php artisan make:domain Users will create a User.php model, but generate a users table in the migration.

## 0.2.1 - 2025-02-17
- **FIXED** Typo in `README.md`.

## 0.2.0 - 2025-02-17
- **ENHANCEMENT:** Replaced `spatie/laravel-sluggable` with our own `aesircloud/sluggable` package.

## 0.1.1 - 2025-02-17
- Add GitHub Actions for Testing

## 0.1.0 - 2025-02-17
- Initial release