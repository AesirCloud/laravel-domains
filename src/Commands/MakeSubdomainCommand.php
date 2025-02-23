<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeSubdomainCommand extends Command
{
    protected $signature = 'make:subdomain
                            {parent : The existing parent domain (e.g. User)}
                            {name : The subdomain (e.g. AuthenticationLogs)}
                            {--migration : Create a migration file}
                            {--soft-deletes : Include soft deletes functionality}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new subdomain for DDD within a parent domain.';

    public function handle(): int
    {
        $parentRaw    = $this->argument('parent');

        $subdomainRaw = $this->argument('name');

        $parentDomain  = Str::studly(Str::singular($parentRaw));

        $subdomainName = Str::studly(Str::singular($subdomainRaw));

        $subdomainLower = Str::camel($subdomainName);

        $domainNamespace  = "App\\Domains\\{$parentDomain}\\{$subdomainName}";

        $actionsNamespace = "App\\Actions\\{$parentDomain}\\{$subdomainName}";

        $tableName = Str::snake(Str::plural($subdomainRaw));

        $softDeletes     = $this->option('soft-deletes');

        $createMigration = $this->option('migration');

        $this->info("Creating subdomain: {$subdomainName} under parent: {$parentDomain}");

        // Ensure parent domain folder exists
        $parentDomainPath = app_path("Domains/{$parentDomain}");

        if (!File::exists($parentDomainPath)) {
            $this->error("Parent domain '{$parentDomain}' does not exist at '{$parentDomainPath}'.");

            return 1;
        }

        // Create subdomain folder
        $baseDir = "{$parentDomainPath}/{$subdomainName}";

        if (File::exists($baseDir) && !$this->option('force')) {
            $this->error("Subdomain {$subdomainName} already exists. Use --force to overwrite.");

            return 1;
        }

        $directories = [
            $baseDir,
            "{$baseDir}/Entities",
            "{$baseDir}/Repositories",
            "{$baseDir}/DomainServices",
            "{$baseDir}/DataTransferObjects",
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);

                $this->info("Created directory: {$dir}");
            } else {
                $this->warn("Directory already exists: {$dir}");
            }
        }

        // Stubs
        $stubPath = __DIR__ . '/../../stubs/domain';

        // Entity, Repo Interface, Domain Service
        $domainStubs = [
            'Entity.stub'        => "{$baseDir}/Entities/{$subdomainName}.php",
            'Repository.stub'    => "{$baseDir}/Repositories/{$subdomainName}RepositoryInterface.php",
            'DomainService.stub' => "{$baseDir}/DomainServices/{$subdomainName}Service.php",
        ];

        foreach ($domainStubs as $stub => $dest) {
            $this->generateStubFile("{$stubPath}/{$stub}", $dest, [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ actionsNamespace }}' => $actionsNamespace,
                '{{ domain }}'          => $subdomainName,
                '{{ domainLower }}'     => $subdomainLower,
            ]);
        }

        // DTO
        $dtoStub = "{$stubPath}/DataTransferObject.stub";

        $dtoDest = "{$baseDir}/DataTransferObjects/{$subdomainName}Data.php";

        $this->generateStubFile($dtoStub, $dtoDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
        ]);

        // Model => app/Models/<SubdomainName>.php
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';

        $modelStubPath = __DIR__ . "/../../stubs/model/{$modelStubFile}";

        $modelDest     = app_path("Models/{$subdomainName}.php");

        $this->generateStubFile($modelStubPath, $modelDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
            '{{ table }}'           => $tableName,
        ]);

        // Factory => database/factories/<SubdomainName>Factory.php
        $factoryStubPath = __DIR__ . "/../../stubs/model/Factory.stub";

        $factoryDest     = database_path("factories/{$subdomainName}Factory.php");

        $this->generateStubFile($factoryStubPath, $factoryDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
        ]);

        // Observer => app/Observers/<SubdomainName>Observer.php
        $observerStub = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';

        $observerDest = app_path("Observers/{$subdomainName}Observer.php");

        $this->generateStubFile("{$stubPath}/{$observerStub}", $observerDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
        ]);

        // Policy => app/Policies/<SubdomainName>Policy.php
        $policyStub = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';

        $policyDest = app_path("Policies/{$subdomainName}Policy.php");

        $this->generateStubFile("{$stubPath}/{$policyStub}", $policyDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
        ]);

        // Eloquent Repository => Eloquent<SubdomainName>Repository
        $repoFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/EloquentRepository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/EloquentRepository.stub";

        $repoDest = app_path("Infrastructure/Persistence/Repositories/Eloquent{$subdomainName}Repository.php");

        $this->generateStubFile($repoFile, $repoDest, [
            '{{ domainNamespace }}' => $domainNamespace,
            '{{ domain }}'          => $subdomainName,
            '{{ domainLower }}'     => $subdomainLower,
        ]);

        // Optional migration
        if ($this->option('migration')) {
            $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';

            $migrationStubPath = __DIR__ . "/../../stubs/model/{$migrationStubFile}";

            $timestamp = date('Y_m_d_His');

            $migrationName = "{$timestamp}_create_{$tableName}_table.php";

            $migrationDest = database_path("migrations/{$migrationName}");

            $this->generateStubFile($migrationStubPath, $migrationDest, [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ domain }}'          => $subdomainName,
                '{{ domainLower }}'     => $subdomainLower,
                '{{ table }}'           => $tableName,
            ]);
        }

        // Update the binding in the provider
        $this->updateProviderBinding($parentDomain, $subdomainName);

        // Create subdomain actions => app/Actions/<ParentDomain>/<SubdomainName>
        $this->createSubdomainActions($parentDomain, $subdomainName, $domainNamespace, $actionsNamespace, $subdomainLower);

        $this->info("Subdomain {$subdomainName} has been created under parent domain {$parentDomain}.");

        return 0;
    }

    protected function createSubdomainActions(
        string $parentDomain,
        string $subdomainName,
        string $domainNamespace,
        string $actionsNamespace,
        string $subdomainLower
    ): void {
        $actionsDir = app_path("Actions/{$parentDomain}/{$subdomainName}");

        if (!File::exists($actionsDir)) {
            File::makeDirectory($actionsDir, 0755, true);

            $this->info("Created actions directory: {$actionsDir}");
        }

        $actionStubs = [
            'Create.stub' => "Create{$subdomainName}Action.php",
            'Update.stub' => "Update{$subdomainName}Action.php",
            'Delete.stub' => "Delete{$subdomainName}Action.php",
            'Index.stub'  => "Index{$subdomainName}Action.php",
            'Show.stub'   => "Show{$subdomainName}Action.php",
        ];

        if ($this->option('soft-deletes')) {
            $actionStubs['Restore.stub']     = "Restore{$subdomainName}Action.php";

            $actionStubs['ForceDelete.stub'] = "ForceDelete{$subdomainName}Action.php";
        }

        $actionStubPath = __DIR__ . '/../../stubs/actions';

        foreach ($actionStubs as $stub => $filename) {
            $this->generateStubFile("{$actionStubPath}/{$stub}", "{$actionsDir}/{$filename}", [
                '{{ domainNamespace }}' => $domainNamespace,
                '{{ actionsNamespace }}' => $actionsNamespace,
                '{{ domain }}'          => $subdomainName,
                '{{ domainLower }}'     => $subdomainLower,
            ]);
        }
    }

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

    protected function createFile(string $destination, string $contents): void
    {
        $force = $this->option('force');

        if (File::exists($destination) && !$force) {
            if (!$this->confirm("File {$destination} exists. Overwrite?", true)) {
                $this->info("Skipped: {$destination}");

                return;
            }
        }

        File::put($destination, $contents);

        $this->info(File::exists($destination)
            ? "Created/Replaced file: {$destination}"
            : "Created file: {$destination}");
    }

    protected function updateProviderBinding(string $parentDomain, string $subdomainName): void
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

        $bindingSignature = "\\App\\Domains\\{$parentDomain}\\{$subdomainName}\\Repositories\\{$subdomainName}RepositoryInterface::class";

        if (strpos($providerContent, $bindingSignature) === false) {
            $bindingLine = "\n        \$this->app->bind(\n"
                . "            \\App\\Domains\\{$parentDomain}\\{$subdomainName}\\Repositories\\{$subdomainName}RepositoryInterface::class,\n"
                . "            \\App\\Infrastructure\\Persistence\\Repositories\\Eloquent{$subdomainName}Repository::class\n"
                . "        );";

            $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';

            if (preg_match($pattern, $providerContent, $matches)) {
                $newMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];

                $providerContent = preg_replace($pattern, $newMethod, $providerContent, 1);

                File::put($providerPath, $providerContent);

                $this->info("Added subdomain binding to RepositoryServiceProvider.");
            } else {
                $this->warn("register() not found in RepositoryServiceProvider. Add manually:");

                $this->line($bindingLine);
            }
        } else {
            $this->info("Repository binding for subdomain {$subdomainName} already exists.");
        }
    }
}
