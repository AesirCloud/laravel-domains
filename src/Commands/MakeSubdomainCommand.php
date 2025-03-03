<?php

namespace AesirCloud\LaravelDomains\Commands;

use AesirCloud\LaravelDomains\Helpers\DomainCommandHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSubdomainCommand extends Command
{
    protected $signature = 'make:subdomain
                            {parent : The existing (plural) parent domain, e.g. "Users"}
                            {name : The subdomain (plural) e.g. "Profiles"}
                            {--migration : Create a migration file}
                            {--soft-deletes : Include soft deletes functionality}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new subdomain for DDD within a parent domain.';

    public function handle(): int
    {
        $parentRaw        = $this->argument('parent');   // e.g. "Users"
        $subdomainRaw     = $this->argument('name');     // e.g. "Profiles"

        // For the parent domain
        $parentDirName    = Str::studly($parentRaw);     // "Users"
        $parentDomain     = Str::singular($parentDirName);// "User"

        // For the subdomain
        $subdomainDirName = Str::studly($subdomainRaw);  // "Profiles"
        $subdomainDomain  = Str::singular($subdomainDirName); // "Profile"
        $subdomainLower   = Str::camel($subdomainDomain);     // "profile"

        $domainNamespace  = "App\\Domains\\{$parentDirName}\\{$subdomainDirName}";
        $actionsNamespace = "App\\Actions\\{$parentDirName}\\{$subdomainDirName}";

        $tableName   = Str::snake(Str::plural($subdomainDomain)); // "profiles"
        $softDeletes = $this->option('soft-deletes');
        $migration   = $this->option('migration');
        $force       = $this->option('force');

        $this->info("Creating subdomain: {$subdomainDirName} under parent domain: {$parentDirName}");

        // 1) Check if parent domain folder exists
        $parentDomainPath = app_path("Domains/{$parentDirName}");
        if (! is_dir($parentDomainPath)) {
            $this->error("Parent domain '{$parentDirName}' does not exist at '{$parentDomainPath}'.");
            return 1;
        }

        // 2) Create the subdomain folder
        $baseDir = "{$parentDomainPath}/{$subdomainDirName}";
        if (is_dir($baseDir) && ! $force) {
            $this->error("Subdomain {$subdomainDirName} already exists. Use --force to overwrite.");
            return 1;
        }

        // 3) Directories
        $directories = [
            $baseDir,
            "{$baseDir}/Entities",
            "{$baseDir}/Repositories",
            "{$baseDir}/DomainServices",
            "{$baseDir}/DataTransferObjects",
        ];
        foreach ($directories as $dir) {
            DomainCommandHelper::createDirectoryIfNotExists($dir, fn($msg) => $this->info($msg));
        }

        // 4) Common placeholders
        $placeholders = [
            '{{ domainNamespace }}'  => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ parentDirName }}'    => $parentDirName,
            '{{ parentDomain }}'     => $parentDomain,
            '{{ subdomainDirName }}' => $subdomainDirName,
            '{{ domain }}'           => $subdomainDomain,
            '{{ domainLower }}'      => $subdomainLower,
            '{{ table }}'            => $tableName,
        ];

        $stubPath = __DIR__ . '/../../stubs/domain';

        // 5) Pick the correct repository interface stub
        $repoInterfaceStubFile = $softDeletes
            ? 'RepositoryInterface.soft.stub'
            : 'RepositoryInterface.stub';

        // 6) Pick the correct DomainService stub
        $domainServiceStub = $softDeletes
            ? 'DomainService.soft.stub'
            : 'DomainService.stub';

        // 7) Domain stubs
        $domainStubs = [
            'Entity.stub'                 => "{$baseDir}/Entities/{$subdomainDomain}.php",
            $repoInterfaceStubFile        => "{$baseDir}/Repositories/{$subdomainDomain}RepositoryInterface.php",
            $domainServiceStub            => "{$baseDir}/DomainServices/{$subdomainDomain}Service.php",
        ];
        foreach ($domainStubs as $stub => $dest) {
            DomainCommandHelper::generateStubFile(
                "{$stubPath}/{$stub}",
                $dest,
                $placeholders,
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }

        // 8) DTO
        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$subdomainDomain}Data.php";
        DomainCommandHelper::generateStubFile(
            $dtoStub,
            $dtoDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 9) Model
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelStubPath = __DIR__ . "/../../stubs/model/{$modelStubFile}";
        $modelDest     = app_path("Models/{$subdomainDomain}.php");
        DomainCommandHelper::generateStubFile(
            $modelStubPath,
            $modelDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 10) Factory
        $factoryStubPath = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest     = database_path("factories/{$subdomainDomain}Factory.php");
        DomainCommandHelper::generateStubFile(
            $factoryStubPath,
            $factoryDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 11) Observer
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Observers'), fn($m) => $this->info($m));
        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerStubPath = "{$stubPath}/{$observerStubFile}";
        $observerDest     = app_path("Observers/{$subdomainDomain}Observer.php");
        DomainCommandHelper::generateStubFile(
            $observerStubPath,
            $observerDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 12) Policy
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Policies'), fn($m) => $this->info($m));
        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyStubPath = "{$stubPath}/{$policyStubFile}";
        $policyDest     = app_path("Policies/{$subdomainDomain}Policy.php");
        DomainCommandHelper::generateStubFile(
            $policyStubPath,
            $policyDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 13) Concrete repo
        DomainCommandHelper::createDirectoryIfNotExists(
            app_path('Infrastructure/Persistence/Repositories'),
            fn($m) => $this->info($m)
        );
        $repoFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";
        $repoDest = app_path("Infrastructure/Persistence/Repositories/{$subdomainDomain}Repository.php");
        DomainCommandHelper::generateStubFile(
            $repoFile,
            $repoDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 14) Migration
        if ($migration) {
            $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
            $migrationStubPath = __DIR__ . "/../../stubs/model/{$migrationStubFile}";
            $timestamp         = date('Y_m_d_His');
            $migrationName     = "{$timestamp}_create_{$tableName}_table.php";
            $migrationDest     = database_path("migrations/{$migrationName}");

            DomainCommandHelper::generateStubFile(
                $migrationStubPath,
                $migrationDest,
                $placeholders,
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }

        // 15) Update subdomain binding in the provider
        $this->updateSubdomainBinding($parentDirName, $subdomainDirName, $subdomainDomain);

        // 16) Create subdomain actions
        $this->createSubdomainActions(
            $parentDirName,
            $subdomainDirName,
            $subdomainDomain,
            $domainNamespace,
            $actionsNamespace
        );

        $this->info("Subdomain {$subdomainDirName} has been successfully created under parent domain {$parentDirName}.");
        return 0;
    }

    protected function updateSubdomainBinding(string $parentDirName, string $subdomainDirName, string $subdomainDomain): void
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        $bindingSignature = "\\App\\Domains\\{$parentDirName}\\{$subdomainDirName}\\Repositories\\{$subdomainDomain}RepositoryInterface::class";
        $bindingLine = "\n        \$this->app->bind(\n"
            . "            \\App\\Domains\\{$parentDirName}\\{$subdomainDirName}\\Repositories\\{$subdomainDomain}RepositoryInterface::class,\n"
            . "            \\App\\Infrastructure\\Persistence\\Repositories\\{$subdomainDomain}Repository::class\n"
            . "        );";

        DomainCommandHelper::updateRepositoryBinding(
            $providerPath,
            $bindingSignature,
            $bindingLine,
            fn($msg) => $this->info($msg)
        );
    }

    protected function createSubdomainActions(
        string $parentDirName,
        string $subdomainDirName,
        string $domain,
        string $domainNamespace,
        string $actionsNamespace
    ): void
    {
        $actionsDir = app_path("Actions/{$parentDirName}/{$subdomainDirName}");
        DomainCommandHelper::createDirectoryIfNotExists($actionsDir, fn($m) => $this->info($m));

        $softDeletes = $this->option('soft-deletes');
        $force       = $this->option('force');

        $actionStubPath = __DIR__ . '/../../stubs/actions';
        $actionStubs = [
            'Create.stub' => 'Create.php',
            'Update.stub' => 'Update.php',
            'Delete.stub' => 'Delete.php',
            'Index.stub'  => 'Index.php',
            'Show.stub'   => 'Show.php',
        ];
        if ($softDeletes) {
            $actionStubs['Restore.stub']     = "Restore.php";
            $actionStubs['ForceDelete.stub'] = "ForceDelete.php";
        }

        // Action placeholders
        $placeholders = [
            '{{ domainNamespace }}'  => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'           => $domain,
        ];

        foreach ($actionStubs as $stub => $fileName) {
            DomainCommandHelper::generateStubFile(
                "{$actionStubPath}/{$stub}",
                "{$actionsDir}/{$fileName}",
                $placeholders,
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }
    }
}
