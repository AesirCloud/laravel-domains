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

    protected $description = 'Scaffold a new domain (DTO, model, observer, policy, factory, repository, migration, actions, etc.)';

    public function handle(): int
    {
        // 1) Preserve the rawName exactly as typed
        $rawName    = $this->argument('name');
        $domainName = $rawName; // No more Str::studly/Str::singular
        $domainLower = Str::camel($domainName);

        // 2) For namespacing, we still do "App\\Domains\\<String>"
        $domainNamespace  = "App\\Domains\\{$domainName}";
        $actionsNamespace = "App\\Actions\\{$domainName}";

        // 3) Table name
        $tableName = Str::snake(Str::plural($rawName)); // e.g. if rawName="TestUser_AbC", => "test_user__ab_cs"

        $softDeletes     = $this->option('soft-deletes');
        $createMigration = $this->option('migration');

        $this->info("Creating domain: {$domainName}");

        // 4) Create domain directories
        $baseDir = app_path("Domains/{$domainName}");
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

        $stubPath = __DIR__ . '/../../stubs/domain';

        // (A) Entity, Repo Interface, Domain Service
        $domainStubs = [
            'Entity.stub'        => "{$baseDir}/Entities/{$domainName}.php",
            'Repository.stub'    => "{$baseDir}/Repositories/{$domainName}RepositoryInterface.php",
            'DomainService.stub' => "{$baseDir}/DomainServices/{$domainName}Service.php",
        ];
        foreach ($domainStubs as $stub => $dest) {
            $this->generateStubFile("{$stubPath}/{$stub}", $dest, [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ actionsNamespace }}' => $actionsNamespace,
                '{{ domain }}'          => $domainName,
                '{{ domainLower }}'     => $domainLower,
            ]);
        }

        // (B) DTO
        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$domainName}Data.php";
        $this->generateStubFile($dtoStub, $dtoDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
        ]);

        // 6) BaseModel
        $baseModelPath = app_path('Models/BaseModel.php');
        if (!File::exists($baseModelPath)) {
            $baseModelStub = __DIR__ . '/../../stubs/model/BaseModel.stub';
            $this->generateStubFile($baseModelStub, $baseModelPath, []);
        }

        // 7) Domain Model
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelStubPath = __DIR__ . "/../../stubs/model/{$modelStubFile}";
        $modelDest     = app_path("Models/{$domainName}.php");
        $this->generateStubFile($modelStubPath, $modelDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
            '{{ table }}'           => $tableName,
        ]);

        // 8) Factory
        $factoryStub = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest = database_path("factories/{$domainName}Factory.php");
        $this->generateStubFile($factoryStub, $factoryDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
        ]);

        // 9) Observer => ensure app/Observers
        $observerDir = app_path('Observers');
        if (!File::exists($observerDir)) {
            File::makeDirectory($observerDir, 0755, true, true);
            $this->info("Created directory: {$observerDir}");
        }
        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerStubPath = "{$stubPath}/{$observerStubFile}";
        $observerDest     = app_path("Observers/{$domainName}Observer.php");
        $this->generateStubFile($observerStubPath, $observerDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
        ]);

        // 10) Policy => ensure app/Policies
        $policyDir = app_path('Policies');
        if (!File::exists($policyDir)) {
            File::makeDirectory($policyDir, 0755, true, true);
            $this->info("Created directory: {$policyDir}");
        }
        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyDest     = app_path("Policies/{$domainName}Policy.php");
        $this->generateStubFile("{$stubPath}/{$policyStubFile}", $policyDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
        ]);

        // 11) Concrete Repository => ensure app/Infrastructure/Persistence/Repositories
        $infraDir = app_path('Infrastructure/Persistence/Repositories');
        if (!File::exists($infraDir)) {
            File::makeDirectory($infraDir, 0755, true, true);
            $this->info("Created directory: {$infraDir}");
        }
        $repoStubFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";
        $repoDest = $infraDir . "/{$domainName}Repository.php";
        $this->generateStubFile($repoStubFile, $repoDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $domainName,
            '{{ domainLower }}'     => $domainLower,
        ]);

        // 12) Optional migration
        if ($createMigration) {
            $migrationFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
            $migrationStub = __DIR__ . "/../../stubs/model/{$migrationFile}";
            $timestamp     = date('Y_m_d_His');
            $migrationName = "{$timestamp}_create_{$tableName}_table.php";
            $migrationDest = database_path("migrations/{$migrationName}");

            $this->generateStubFile($migrationStub, $migrationDest, [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ domain }}'          => $domainName,
                '{{ domainLower }}'     => $domainLower,
                '{{ table }}'           => $tableName,
            ]);
        }

        // 13) Update RepositoryServiceProvider
        $this->updateRepositoryBinding($domainName);

        // 14) CRUD Actions => app/Actions/<domainName>
        $actionsDir = app_path("Actions/{$domainName}");
        if (!File::exists($actionsDir)) {
            File::makeDirectory($actionsDir, 0755, true, true);
            $this->info("Created directory: {$actionsDir}");
        }
        $actionStubs = [
            'Create.stub' => "Create{$domainName}Action.php",
            'Update.stub' => "Update{$domainName}Action.php",
            'Delete.stub' => "Delete{$domainName}Action.php",
            'Index.stub'  => "Index{$domainName}Action.php",
            'Show.stub'   => "Show{$domainName}Action.php",
        ];
        if ($softDeletes) {
            $actionStubs['Restore.stub']     = "Restore{$domainName}Action.php";
            $actionStubs['ForceDelete.stub'] = "ForceDelete{$domainName}Action.php";
        }
        $actionStubDir = __DIR__ . '/../../stubs/actions';
        foreach ($actionStubs as $stub => $fileName) {
            $this->generateStubFile("{$actionStubDir}/{$stub}", "{$actionsDir}/{$fileName}", [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ actionsNamespace }}' => $actionsNamespace,
                '{{ domain }}'          => $domainName,
                '{{ domainLower }}'     => $domainLower,
            ]);
        }

        $this->info("Domain {$domainName} has been successfully created.");
        return 0;
    }

    /**
     * Replace placeholders in a stub file & create final file.
     */
    protected function generateStubFile(string $stubPath, string $destination, array $placeholders): void
    {
        if (!File::exists($stubPath)) {
            $this->warn("Stub file not found: {$stubPath}");
            return;
        }
        $contents = File::get($stubPath);
        foreach ($placeholders as $search => $replace) {
            $contents = str_replace($search, $replace, $contents);
        }
        $this->createFile($destination, $contents);
    }

    /**
     * Create or overwrite file, respecting --force.
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
            : "Created file: {$destination}"
        );
    }

    /**
     * Update RepositoryServiceProvider with domain binding.
     */
    protected function updateRepositoryBinding(string $domainName): void
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
        $providerContent  = File::get($providerPath);
        $bindingSignature = "\\App\\Domains\\{$domainName}\\Repositories\\{$domainName}RepositoryInterface::class";

        if (!str_contains($providerContent, $bindingSignature)) {
            $bindingLine = "\n        \$this->app->bind(\n"
                . "            \\App\\Domains\\{$domainName}\\Repositories\\{$domainName}RepositoryInterface::class,\n"
                . "            \\App\\Infrastructure\\Persistence\\Repositories\\{$domainName}Repository::class\n"
                . "        );";

            $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';
            if (preg_match($pattern, $providerContent, $matches)) {
                $newMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];
                $providerContent = preg_replace($pattern, $newMethod, $providerContent, 1);
                File::put($providerPath, $providerContent);
                $this->info("Added repository binding to RepositoryServiceProvider.");
            } else {
                $this->warn("Could not locate register() method. Please add manually:");
                $this->line($bindingLine);
            }
        } else {
            $this->info("Repository binding for {$domainName} already exists in RepositoryServiceProvider.");
        }
    }
}
