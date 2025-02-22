<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain
                            {name : The name of the domain}
                            {--migration : Create a migration file}
                            {--soft-deletes : Include soft deletes functionality}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new domain (with DTO, model, observer, policy, factory, concrete repository, migration, repository binding, and actions) for DDD';

    public function handle(): int
    {
        // Original raw name from the command argument
        $rawName = $this->argument('name');

        // For class names, use singular (e.g., "Users" -> "User")
        $domainName = Str::studly(Str::singular($rawName));

        // For table names, keep it plural and snake (e.g., "Users" -> "users")
        $tableName = Str::snake(Str::plural($rawName));

        // For variable names (e.g. $user, $customer)
        $domainLower = Str::camel($domainName);

        $softDeletes     = $this->option('soft-deletes');

        $createMigration = $this->option('migration');

        $this->info("Creating domain: {$domainName}");

        // 1. Create Domain Directories in app/Domains/<DomainName>
        $baseDir = app_path("Domains/{$domainName}");

        // [OPTION 1] Remove the immediate "domain folder exists" check, so we don't abort early.
        $directories = [
            $baseDir,
            "{$baseDir}/Entities",
            "{$baseDir}/Repositories",
            "{$baseDir}/DomainServices",
            "{$baseDir}/DataTransferObjects",
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true, true);

                $this->info("Created directory: {$dir}");
            } else {
                $this->warn("Directory already exists: {$dir}");
            }
        }

        // 2. Create Domain Stub Files (Entity, Repository Interface, DomainService)
        $stubPath = __DIR__ . '/../../stubs/domain';

        $domainStubs = [
            'Entity.stub'        => "{$baseDir}/Entities/{$domainName}.php",
            'Repository.stub'    => "{$baseDir}/Repositories/{$domainName}RepositoryInterface.php",
            'DomainService.stub' => "{$baseDir}/DomainServices/{$domainName}Service.php",
        ];

        foreach ($domainStubs as $stub => $destination) {
            $stubFilePath = $stubPath . '/' . $stub;

            if (File::exists($stubFilePath)) {
                $contents = File::get($stubFilePath);

                $contents = str_replace(
                    ['{{ domain }}', '{{ domainLower }}'],
                    [$domainName, $domainLower],
                    $contents
                );

                $this->createFile($destination, $contents);
            } else {
                $this->warn("Stub file not found: {$stubFilePath}");
            }
        }

        // 3. Create DTO Stub File
        $dtoStubPath = $stubPath . '/DataTransferObject.stub';

        $dtoDestination = "{$baseDir}/DataTransferObjects/{$domainName}Data.php";

        if (File::exists($dtoStubPath)) {
            $contents = File::get($dtoStubPath);

            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}'],
                [$domainName, $domainLower],
                $contents
            );

            $this->createFile($dtoDestination, $contents);
        } else {
            $this->warn("DTO stub not found: {$dtoStubPath}");
        }

        // 4. Create Base Model (if missing) and Domain Model in app/Models
        $baseModelPath = app_path('Models/BaseModel.php');

        if (!File::exists($baseModelPath)) {
            $modelStubPath = __DIR__ . '/../../stubs/model/BaseModel.stub';

            if (File::exists($modelStubPath)) {
                $contents = File::get($modelStubPath);

                $this->createFile($baseModelPath, $contents);
            } else {
                $this->warn("BaseModel stub not found: {$modelStubPath}");
            }
        }

        $modelStubFile   = $softDeletes ? 'Model.soft.stub' : 'Model.stub';

        $modelStubPath   = __DIR__ . "/../../stubs/model/{$modelStubFile}";

        $modelDestination = app_path("Models/{$domainName}.php");

        if (File::exists($modelStubPath)) {
            $contents = File::get($modelStubPath);

            // Replace placeholders with the singular class name, variable name, and plural table name
            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}', '{{ table }}'],
                [$domainName, $domainLower, $tableName],
                $contents
            );

            $this->createFile($modelDestination, $contents);
        } else {
            $this->warn("Model stub not found: {$modelStubPath}");
        }

        // 5. Create Factory in database/factories
        $factoryStubFile = 'Factory.stub';

        $factoryStubPath = __DIR__ . "/../../stubs/model/{$factoryStubFile}";

        $factoryDestination = database_path("factories/{$domainName}Factory.php");

        if (File::exists($factoryStubPath)) {
            $contents = File::get($factoryStubPath);

            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}'],
                [$domainName, $domainLower],
                $contents
            );

            $this->createFile($factoryDestination, $contents);
        } else {
            $this->warn("Factory stub not found: {$factoryStubPath}");
        }

        // 6. Create Observer in app/Observers
        $observerDir = app_path("Observers");
        if (!File::exists($observerDir)) {
            File::makeDirectory($observerDir, 0755, true);

            $this->info("Created directory: {$observerDir}");
        }

        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';

        $observerStubPath = __DIR__ . "/../../stubs/domain/{$observerStubFile}";

        $observerDestination = app_path("Observers/{$domainName}Observer.php");

        if (File::exists($observerStubPath)) {
            $contents = File::get($observerStubPath);

            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}'],
                [$domainName, $domainLower],
                $contents
            );

            $this->createFile($observerDestination, $contents);
        } else {
            $this->warn("Observer stub not found: {$observerStubPath}");
        }

        // 7. Create Policy in app/Policies
        $policyDir = app_path("Policies");
        if (!File::exists($policyDir)) {
            File::makeDirectory($policyDir, 0755, true);

            $this->info("Created directory: {$policyDir}");
        }

        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';

        $policyStubPath = __DIR__ . "/../../stubs/domain/{$policyStubFile}";

        $policyDestination = app_path("Policies/{$domainName}Policy.php");

        if (File::exists($policyStubPath)) {
            $contents = File::get($policyStubPath);

            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}'],
                [$domainName, $domainLower],
                $contents
            );

            $this->createFile($policyDestination, $contents);
        } else {
            $this->warn("Policy stub not found: {$policyStubPath}");
        }

        // 8. Create Concrete Repository Implementation
        $concreteRepoDir = app_path('Infrastructure/Persistence/Repositories');
        if (!File::exists($concreteRepoDir)) {
            File::makeDirectory($concreteRepoDir, 0755, true);

            $this->info("Created directory: {$concreteRepoDir}");
        }

        $concreteRepoStubFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";

        $concreteRepoDestination = $concreteRepoDir . "/{$domainName}Repository.php";

        if (File::exists($concreteRepoStubFile)) {
            $contents = File::get($concreteRepoStubFile);

            $contents = str_replace(
                ['{{ domain }}', '{{ domainLower }}'],
                [$domainName, $domainLower],
                $contents
            );

            $this->createFile($concreteRepoDestination, $contents);
        } else {
            $this->warn("Concrete repository stub not found: {$concreteRepoStubFile}");
        }

        // 9. Optionally, Create a Migration
        if ($createMigration) {
            $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';

            $migrationStubPath = __DIR__ . "/../../stubs/model/{$migrationStubFile}";

            $timestamp = date('Y_m_d_His');

            $migrationFileName = "{$timestamp}_create_{$tableName}_table.php";

            $migrationDestination = database_path("migrations/{$migrationFileName}");

            if (File::exists($migrationStubPath)) {
                $contents = File::get($migrationStubPath);

                $contents = str_replace(
                    ['{{ domain }}', '{{ domainLower }}', '{{ table }}'],
                    [$domainName, $domainLower, $tableName],
                    $contents
                );

                $this->createFile($migrationDestination, $contents);
            } else {
                $this->warn("Migration stub not found: {$migrationStubPath}");
            }
        }

        // 10. Update RepositoryServiceProvider
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        if (!File::exists($providerPath)) {
            $this->info("RepositoryServiceProvider not found. Creating one...");

            Artisan::call('make:provider RepositoryServiceProvider');

            $this->info("RepositoryServiceProvider created.");
        }

        if (File::exists($providerPath)) {
            $providerContent = File::get($providerPath);

            $bindingSignature = "\\App\\Domains\\{$domainName}\\Repositories\\{$domainName}RepositoryInterface::class";

            if (strpos($providerContent, $bindingSignature) === false) {
                $bindingLine = "\n        \$this->app->bind(\n"
                    . "            \\App\\Domains\\{$domainName}\\Repositories\\{$domainName}RepositoryInterface::class,\n"
                    . "            \\App\\Infrastructure\\Persistence\\Repositories\\{$domainName}Repository::class\n"
                    . "        );";

                // Regex to capture an optional return type (e.g. : void)
                $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';

                if (preg_match($pattern, $providerContent, $matches)) {
                    $newRegisterMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];

                    $providerContent = preg_replace($pattern, $newRegisterMethod, $providerContent, 1);

                    File::put($providerPath, $providerContent);

                    $this->info("Added repository binding to RepositoryServiceProvider.");
                } else {
                    $this->warn("Could not locate the register() method in RepositoryServiceProvider. Please add the following binding manually:");

                    $this->line($bindingLine);
                }
            } else {
                $this->info("Repository binding for {$domainName} already exists in RepositoryServiceProvider.");
            }
        }

        // 11. Create CRUD Actions in app/Actions/{{ domain }}
        $actionsDir = app_path("Actions/{$domainName}");
        if (!File::exists($actionsDir)) {
            File::makeDirectory($actionsDir, 0755, true);

            $this->info("Created directory: {$actionsDir}");
        }

        $actionStubs = [
            'Create.stub'  => "Create{$domainName}Action.php",
            'Update.stub'  => "Update{$domainName}Action.php",
            'Delete.stub'  => "Delete{$domainName}Action.php",
            'Index.stub'   => "Index{$domainName}Action.php",
            'Show.stub'    => "Show{$domainName}Action.php",
        ];

        // If soft deletes are enabled, add Restore and ForceDelete actions.
        if ($softDeletes) {
            $actionStubs['Restore.stub']     = "Restore{$domainName}Action.php";

            $actionStubs['ForceDelete.stub'] = "ForceDelete{$domainName}Action.php";
        }

        $actionStubPath = __DIR__ . '/../../stubs/actions';
        foreach ($actionStubs as $stub => $destFile) {
            $stubFilePath = $actionStubPath . '/' . $stub;

            $destPath = $actionsDir . '/' . $destFile;

            if (File::exists($stubFilePath)) {
                $contents = File::get($stubFilePath);

                $contents = str_replace(
                    ['{{ domain }}', '{{ domainLower }}'],
                    [$domainName, $domainLower],
                    $contents
                );
                $this->createFile($destPath, $contents);
            } else {
                $this->warn("Action stub not found: {$stubFilePath}");
            }
        }

        $this->info("Domain {$domainName} has been successfully created.");

        return 0;
    }

    /**
     * Create or replace a file with given contents, respecting --force.
     *
     * @param  string  $destination
     * @param  string  $contents
     * @return void
     */
    protected function createFile($destination, $contents): void
    {
        $force = $this->option('force');

        if (File::exists($destination) && !$force) {
            if (!$this->confirm("File {$destination} already exists. Overwrite it?", true)) {
                $this->info("Skipped file: {$destination}");

                return;
            }
        }

        File::put($destination, $contents);

        if (File::exists($destination)) {
            $this->info("Created/Replaced file: {$destination}");
        } else {
            $this->info("Created file: {$destination}");
        }
    }
}
