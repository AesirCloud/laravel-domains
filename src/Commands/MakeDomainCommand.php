<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain
                            {name : The (plural) name of the domain, e.g. "Users"}
                            {--migration : Create a migration file}
                            {--soft-deletes : Include soft deletes functionality}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new domain (DTO, model, observer, policy, factory, repository, migration, actions, etc.)';

    public function handle(): int
    {
        // The user types "Users" (plural).
        // We'll store the directory name as is (plural),
        // but produce singular class names.
        $rawName        = $this->argument('name'); // e.g. "Users"
        $directoryName  = Str::studly($rawName);   // e.g. "Users"
        $className      = Str::singular($directoryName); // e.g. "User"

        // So the final domain folder is app/Domains/Users
        // but classes are "User.php", "UserService.php", etc.

        // For DB table, we want "users" from "User" => plural => "users"
        $tableName = Str::snake(Str::plural($className)); // "users"

        $softDeletes     = $this->option('soft-deletes');
        $createMigration = $this->option('migration');

        $this->info("Creating domain: {$directoryName}");

        // 1) Create domain directories: app/Domains/Users
        $baseDir = app_path("Domains/{$directoryName}");
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

        // 2) Stubs directory
        $stubPath = __DIR__ . '/../../stubs/domain';

        // 3) Entities, Repo Interface, Domain Service => singular class
        $domainStubs = [
            'Entity.stub'        => "{$baseDir}/Entities/{$className}.php",
            'Repository.stub'    => "{$baseDir}/Repositories/{$className}RepositoryInterface.php",
            'DomainService.stub' => "{$baseDir}/DomainServices/{$className}Service.php",
        ];
        foreach ($domainStubs as $stub => $dest) {
            $this->generateStubFile(
                "{$stubPath}/{$stub}",
                $dest,
                [
                    '{{ directoryName }}' => $directoryName, // e.g. "Users"
                    '{{ className }}'     => $className,     // e.g. "User"
                ]
            );
        }

        // 4) DTO => singular class
        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$className}Data.php";
        $this->generateStubFile($dtoStub, $dtoDest, [
            '{{ directoryName }}' => $directoryName,
            '{{ className }}'     => $className,
        ]);

        // 5) Possibly create BaseModel if missing
        $baseModelPath = app_path('Models/BaseModel.php');
        if (!File::exists($baseModelPath)) {
            $baseModelStub = __DIR__ . '/../../stubs/model/BaseModel.stub';
            $this->generateStubFile($baseModelStub, $baseModelPath, []);
        }

        // 6) Model => singular class name in app/Models
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelStubPath = __DIR__ . "/../../stubs/model/{$modelStubFile}";
        $modelDest     = app_path("Models/{$className}.php");
        $this->generateStubFile($modelStubPath, $modelDest, [
            '{{ className }}' => $className,     // "User"
            '{{ table }}'     => $tableName,     // "users"
        ]);

        // 7) Factory => singular class name => "UserFactory.php"
        $factoryStub = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest = database_path("factories/{$className}Factory.php");
        $this->generateStubFile($factoryStub, $factoryDest, [
            '{{ className }}' => $className,
        ]);

        // 8) Observer => "UserObserver.php" in app/Observers
        $observerDir = app_path('Observers');
        if (!File::exists($observerDir)) {
            File::makeDirectory($observerDir, 0755, true, true);
            $this->info("Created directory: {$observerDir}");
        }
        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerStubPath = "{$stubPath}/{$observerStubFile}";
        $observerDest     = app_path("Observers/{$className}Observer.php");
        $this->generateStubFile($observerStubPath, $observerDest, [
            '{{ className }}' => $className,
        ]);

        // 9) Policy => "UserPolicy.php" in app/Policies
        $policyDir = app_path('Policies');
        if (!File::exists($policyDir)) {
            File::makeDirectory($policyDir, 0755, true, true);
            $this->info("Created directory: {$policyDir}");
        }
        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyStubPath = "{$stubPath}/{$policyStubFile}";
        $policyDest     = app_path("Policies/{$className}Policy.php");
        $this->generateStubFile($policyStubPath, $policyDest, [
            '{{ className }}' => $className,
        ]);

        // 10) Concrete Repository => "UserRepository.php" in app/Infrastructure/Persistence/Repositories
        $infraDir = app_path('Infrastructure/Persistence/Repositories');
        if (!File::exists($infraDir)) {
            File::makeDirectory($infraDir, 0755, true, true);
            $this->info("Created directory: {$infraDir}");
        }
        $repoStubFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";
        $repoDest = "{$infraDir}/{$className}Repository.php";
        $this->generateStubFile($repoStubFile, $repoDest, [
            '{{ className }}' => $className,
        ]);

        // 11) Migration => "create_users_table" if requested
        if ($createMigration) {
            $migrationFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
            $migrationStub = __DIR__ . "/../../stubs/model/{$migrationFile}";
            $timestamp     = date('Y_m_d_His');
            $migrationName = "{$timestamp}_create_{$tableName}_table.php";
            $migrationDest = database_path("migrations/{$migrationName}");
            $this->generateStubFile($migrationStub, $migrationDest, [
                '{{ className }}' => $className,
                '{{ table }}'     => $tableName,
            ]);
        }

        // 12) Update the RepositoryServiceProvider with a binding
        $this->updateRepositoryBinding($directoryName, $className);

        // 13) Create CRUD actions => in app/Actions/Users
        $actionsDir = app_path("Actions/{$directoryName}");
        if (!File::exists($actionsDir)) {
            File::makeDirectory($actionsDir, 0755, true, true);
            $this->info("Created directory: {$actionsDir}");
        }

        // Action stubs: "Create.stub" => "Create.php", etc.
        $actionStubs = [
            'Create.stub' => "Create.php",
            'Update.stub' => "Update.php",
            'Delete.stub' => "Delete.php",
            'Index.stub'  => "Index.php",
            'Show.stub'   => "Show.php",
        ];
        if ($softDeletes) {
            $actionStubs['Restore.stub']     = "Restore.php";
            $actionStubs['ForceDelete.stub'] = "ForceDelete.php";
        }

        $actionStubDir = __DIR__ . '/../../stubs/actions';
        foreach ($actionStubs as $stub => $fileName) {
            $this->generateStubFile(
                "{$actionStubDir}/{$stub}",
                "{$actionsDir}/{$fileName}",
                [
                    '{{ className }}'     => $className,
                    '{{ directoryName }}' => $directoryName,
                ]
            );
        }

        $this->info("Domain {$directoryName} has been successfully created.");
        return 0;
    }

    /**
     * Helper: read stub, replace placeholders, create file.
     */
    protected function generateStubFile(string $stubPath, string $destination, array $placeholders): void
    {
        if (!File::exists($stubPath)) {
            $this->warn("Stub file not found: {$stubPath}");
            return;
        }
        $contents = File::get($stubPath);
        foreach ($placeholders as $search => $replace) {
            $contents = str_replace(
                ['{{ ' . $search . ' }}'],
                [$replace],
                $contents
            );
        }
        $this->createFile($destination, $contents);
    }

    /**
     * Create or overwrite a file, respecting --force.
     */
    protected function createFile(string $destination, string $contents): void
    {
        $force = $this->option('force');
        if (File::exists($destination) && !$force) {
            if (!$this->confirm("File {$destination} already exists. Overwrite?", true)) {
                $this->info("Skipped file: {$destination}");
                return;
            }
        }
        File::put($destination, $contents);
        $this->info(File::exists($destination)
            ? "Created/Replaced file: {$destination}"
            : "Created file: {$destination}");
    }

    /**
     * Update the repository binding in RepositoryServiceProvider.
     *
     * @param string $directoryName The domain's plural name, e.g. "Users"
     * @param string $className The singular class name, e.g. "User"
     */
    protected function updateRepositoryBinding(string $directoryName, string $className): void
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        if (!File::exists($providerPath)) {
            $this->info("RepositoryServiceProvider not found. Creating one...");
            Artisan::call('make:provider RepositoryServiceProvider');
            $this->info("RepositoryServiceProvider created.");
        }
        if (!File::exists($providerPath)) {
            return;
        }

        $providerContent = File::get($providerPath);

        // We'll reference \App\Domains\Users\Repositories\UserRepositoryInterface::class
        $bindingSignature = "\\App\\Domains\\{$directoryName}\\Repositories\\{$className}RepositoryInterface::class";

        if (!str_contains($providerContent, $bindingSignature)) {
            $bindingLine = "\n        \$this->app->bind(\n"
                . "            \\App\\Domains\\{$directoryName}\\Repositories\\{$className}RepositoryInterface::class,\n"
                . "            \\App\\Infrastructure\\Persistence\\Repositories\\{$className}Repository::class\n"
                . "        );";

            $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';
            if (preg_match($pattern, $providerContent, $matches)) {
                $newMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];
                $providerContent = preg_replace($pattern, $newMethod, $providerContent, 1);
                File::put($providerPath, $providerContent);
                $this->info("Added repository binding to RepositoryServiceProvider.");
            } else {
                $this->warn("Could not locate register() method in RepositoryServiceProvider. Please add manually:");
                $this->line($bindingLine);
            }
        } else {
            $this->info("Repository binding for domain {$directoryName} already exists.");
        }
    }
}
