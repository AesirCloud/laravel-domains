# AesirCloud Domain Driven Design Standards & Philosophy

---

This document outlines the standards and guidelines for using the AesirCloud/laravel-domains package in your Laravel projects. The goal is to enforce a consistent Domain-Driven Design (DDD) approach across projects, ensuring separation of concerns, modularity, and reusability.

---

## 1. PHILOSOPHY & OVERVIEW

### Our pattern philosophy is centered on the following principles:

- Separation of Concerns:
  Different layers handle distinct responsibilities. The Domain layer encapsulates business logic; the Infrastructure layer handles persistence and external integrations; the Application layer (or actions) orchestrates operations.

- Domain-Centric Design:
  Organize your code by business domain. Each domain encapsulates related entities, (optionally) value objects, repositories, domain services, data transfer objects, and actions, making it easier to manage and evolve as business requirements change.

- Consistency & Reusability:
  All projects follow the same directory structure, naming conventions, and coding practices. This consistency simplifies onboarding, maintenance, and code reuse.

- Framework-Agnostic Domain Logic:
  Domain entities, value objects, and domain services remain independent of Laravel-specific code. This isolation enhances testability and portability.

- Explicit Dependencies:
  Use dependency injection and repository interfaces to clearly define contracts. Repository bindings are managed via a dedicated service provider to facilitate easy swapping of implementations.

## 2. DIRECTORY STRUCTURE & NAMING CONVENTIONS

### DOMAIN STRUCTURE

Each domain is created under `app/Domains/{DomainName}` with the following subdirectories:

- Entities: `app/Domains/{DomainName}/Entities/{DomainName}.php`
  - Guidelines:
    - Represent core domain models with a unique identity and business rules.
    - Naming: Singular, PascalCase (e.g., User.php).

- Value Objects: (Value objects are NOT auto-generated during domain scaffolding. They are created separately via the make:value-object command when a domain concept requires specialized behavior and validation.)
  - Example Naming: `app/Domains/{DomainName}/ValueObjects/EmailValueObject.php`

- Repositories: `app/Domains/{DomainName}/Repositories/{DomainName}RepositoryInterface.php`
  - Guidelines:
   - Define contracts for data access and persistence.
   - Naming example: UserRepositoryInterface.php.

- Domain Services: `app/Domains/{DomainName}/DomainServices/{DomainName}Service.php`
  - Guidelines:
    - Encapsulate business operations that do not fit naturally within an entity or value object.
    -  Naming example: UserService.php.

- Data Transfer Objects (DTOs): `app/Domains/{DomainName}/DataTransferObjects/{DomainName}Data.php`
  - Guidelines:
    - Handle data transfer and validation using spatie/laravel-data.
    - Naming example: UserData.php.
    - Note: DTOs should define their validation rules via a rules() method and map data cleanly into domain objects.

### INFRASTRUCTURE & APPLICATION LAYERS

- Models: Eloquent models reside in `app/Models` and extend a shared BaseModel.
  - Guidelines:
    - Naming: Singular, matching the domain (e.g., User.php).
    - Conventions: Models use soft delete logic if enabled and define `$fillable`, `$guarded`, and `$casts` as needed.
    - Note on Naming: The package automatically converts the raw domain name to StudlyCase for model classes and snake-pluralizes the raw domain name for database tables.

- Concrete Repository Implementations: Concrete implementations of repository interfaces are generated in the Infrastructure layer under `app/Infrastructure/Persistence/Repositories`.
  - Guidelines:
    - Naming: Eloquent{DomainName}Repository.php (e.g., EloquentUserRepository.php).
    - These classes use the Eloquent ORM to implement the repository interface, mapping domain entities to Eloquent models.
    - When soft deletes are enabled, a dedicated soft-delete–aware repository stub is used.

- Observers & Policies:
  Observers are located in `app/Observers` and policies in `app/Policies`.
  They help manage model events and authorization logic, respectively.

- Factories:
  Factories are stored in `database/factories` with the naming convention `{DomainName}Factory.php`.

- Migrations:
  Migrations follow Laravel’s naming conventions and are timestamped. Table names are derived by pluralizing and snake-casing the domain name (e.g., users for User).

- Actions:
  Actions are generated using `lorisleiva/laravel-actions` and are stored in `app/Actions/{DomainName}`.
  - Standard CRUD Actions: Include Create, Update, Delete, Index, and Show.
  - Additional Soft Delete Actions: If the domain uses soft deletes, Restore and ForceDelete actions are also generated.
  - Naming Example: `CreateUserAction.php`, `UpdateUserAction.php`, etc.

## 3. CODING & INTEGRATION STANDARDS

### DEPENDENCY INJECTION & REPOSITORY BINDING

- Repository Interfaces: Always type-hint dependencies using repository interfaces.

- Service Provider: The RepositoryServiceProvider automatically binds interfaces to their concrete implementations. Do not hard-code concrete classes outside of this provider.

### DOMAIN LOGIC ISOLATION

- Keep Domain Logic Pure: Domain entities and value objects should not rely on Laravel helpers or facade calls. Use DTOs and actions to mediate between the domain and external layers.

- Validation: Validate data at the application level (via DTOs or Laravel validation) before constructing domain objects.

### DTO USAGE

- Centralized Data Transfer: DTOs are used to transfer and validate data entering the domain layer.

- spatie/laravel-data: All DTOs should extend `Spatie\LaravelData\Data` and define their validation rules in a static rules() method. This ensures consistency and leverages Laravel’s validation when mapping external data into domain objects.

CUSTOMIZATION & EXTENSIBILITY

- Publish stubs with:
    ```php
    php artisan vendor:publish --tag=ddd-stubs
    ```

- File Replacement: When regenerating files, the generator prompts to replace existing files (defaulting to "yes") unless you pass the --force option to overwrite without confirmation. Adjust your stubs and files as needed without breaking the overall structure.

## 4. WORKFLOW GUIDELINES
1. Scaffold a Domain - Run the command:
   php artisan make:domain {DomainName} {--migration} {--soft-deletes} {--force}
   Review and adjust the generated files as necessary.

2. Create Subdomains - Use the make:subdomain command to add subdomains to an existing domain.
   Run the command:
   ```php
    php artisan make:subdomain {SubdomainName} {--domain={DomainName}} {--force}
    ```
   
    This will place the subdomain in app/Domains/{DomainName}/{SubdomainName}.

3. Create Value Objects - Value objects are generated separately to represent specific domain concepts.
   Run the command:
   ```php
    php artisan make:value-object {ValueObjectName} {--domain={DomainName}} {--force}
    ```
   
    This will place the value object in app/Domains/{DomainName}/ValueObjects if the domain is specified, or in app/ValueObjects otherwise.

4. Extending an Existing Domain:
   When adding new models or related components (e.g., UserProfile), consider:
   • Same Domain: If tightly coupled to the existing domain (e.g., User), add it as a new entity or value object within the same domain.
   • New Domain: If it represents a distinct bounded context with independent behavior, scaffold it as a separate domain.

5. Repository & Service Updates:
   Update repository interfaces and implementations in tandem.
   Use explicit binding in RepositoryServiceProvider for clarity and ease of testing.

6. Testing & Validation:
   Ensure each domain component is well-tested.
   Use factories for generating test data, and validate domain behavior independently from infrastructure concerns.

7. Actions for CRUD Operations:
   Generate CRUD actions using the package’s scaffolding command. The following actions are generated by default:
   • Create Action: Handles creation of new domain entities.
   • Update Action: Handles updating existing domain entities.
   • Delete Action: Handles deletion.
   • Index Action: Retrieves a collection of domain entities.
   • Show Action: Retrieves a single domain entity.

   Additional Actions for Soft Deletes:
   If soft deletes are enabled, additional actions (Restore and ForceDelete) are generated to allow for record restoration and permanent deletion.

## 5. CONCLUSION

Following these standards will ensure that your DDD-based Laravel projects are consistent, maintainable, and scalable. Adhering to this pattern philosophy makes it easier to onboard new team members, maintain code quality, and extend functionality as business requirements evolve.

For questions, improvements, or contributions to these guidelines, please open an issue or submit a pull request on the project’s repository.

--- 

By maintaining these standards, we create a robust and flexible architecture that stands the test of time and scales with our applications.
