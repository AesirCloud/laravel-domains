<?php

namespace AesirCloud\LaravelDomains\Commands;

use AesirCloud\LaravelDomains\Helpers\DomainCommandHelper;
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
        $rawName       = $this->argument('name');    // e.g. "Users"
        $directoryName = Str::studly($rawName);      // "Users"
        $domain        = Str::singular($directoryName); // "User"
        $domainLower   = Str::camel($domain);        // "user"

        $domainNamespace  = "App\\Domains\\{$directoryName}";
        $actionsNamespace = "App\\Actions\\{$directoryName}";

        $tableName   = Str::snake(Str::plural($domain)); // "users"
        $softDeletes = $this->option('soft-deletes');
        $migration   = $this->option('migration');
        $force       = $this->option('force');

        $this->info("Creating domain: {$directoryName}");

        // 1) Create domain directories
        $baseDir = app_path("Domains/{$directoryName}");
        $directories = [
            $baseDir,
            "{$baseDir}/Entities",
            "{$baseDir}/Repositories",
            "{$baseDir}/DomainServices",
            "{$baseDir}/DataTransferObjects",
        ];

        // Use our helper for each directory
        foreach ($directories as $dir) {
            DomainCommandHelper::createDirectoryIfNotExists($dir, function($msg) {
                $this->info($msg);
            });
        }

        // 2) Common placeholders
        $placeholders = [
            '{{ domainNamespace }}'  => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $domain,
            '{{ domainLower }}'     => $domainLower,
            '{{ table }}'           => $tableName,
        ];

        $stubPath = __DIR__ . '/../../stubs/domain';

        // 3) Condition for the interface stub
        $repoInterfaceStub = $softDeletes
            ? 'RepositoryInterface.soft.stub'
            : 'RepositoryInterface.stub';

        // 4) Domain stubs
        $domainStubs = [
            'Entity.stub'         => "{$baseDir}/Entities/{$domain}.php",
            $repoInterfaceStub    => "{$baseDir}/Repositories/{$domain}RepositoryInterface.php",
            'DomainService.stub'  => "{$baseDir}/DomainServices/{$domain}Service.php",
        ];

        foreach ($domainStubs as $stub => $dest) {
            DomainCommandHelper::generateStubFile(
                "{$stubPath}/{$stub}",
                $dest,
                $placeholders,
                $force,
                // logger callback
                function($msg, $warn=false) {
                    $warn ? $this->warn($msg) : $this->info($msg);
                },
                // confirm callback
                function($question, $default) {
                    return $this->confirm($question, $default);
                }
            );
        }

        // 5) DTO
        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$domain}Data.php";
        DomainCommandHelper::generateStubFile(
            $dtoStub, $dtoDest, $placeholders, $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 6) Possibly create BaseModel
        $baseModelPath = app_path('Models/BaseModel.php');
        if (! File::exists($baseModelPath)) {
            $baseModelStub = __DIR__ . '/../../stubs/model/BaseModel.stub';
            DomainCommandHelper::generateStubFile(
                $baseModelStub, $baseModelPath, [],
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }

        // 7) Eloquent model
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelDest     = app_path("Models/{$domain}.php");
        DomainCommandHelper::generateStubFile(
            __DIR__ . "/../../stubs/model/{$modelStubFile}",
            $modelDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 8) Factory
        $factoryStub = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest = database_path("factories/{$domain}Factory.php");
        DomainCommandHelper::generateStubFile(
            $factoryStub, $factoryDest, $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 9) Observer => "app/Observers/UserObserver.php"
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Observers'), fn($msg) => $this->info($msg));

        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerStubPath = "{$stubPath}/{$observerStubFile}";
        $observerDest     = app_path("Observers/{$domain}Observer.php");
        DomainCommandHelper::generateStubFile(
            $observerStubPath,
            $observerDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 10) Policy => "app/Policies/UserPolicy.php"
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Policies'), fn($msg) => $this->info($msg));

        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyStubPath = "{$stubPath}/{$policyStubFile}";
        $policyDest     = app_path("Policies/{$domain}Policy.php");
        DomainCommandHelper::generateStubFile(
            $policyStubPath,
            $policyDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 11) Concrete repository => "UserRepository.php"
        DomainCommandHelper::createDirectoryIfNotExists(
            app_path('Infrastructure/Persistence/Repositories'),
            fn($msg) => $this->info($msg)
        );

        $concreteRepoStub = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";
        $concreteRepoDest = app_path("Infrastructure/Persistence/Repositories/{$domain}Repository.php");
        DomainCommandHelper::generateStubFile(
            $concreteRepoStub,
            $concreteRepoDest,
            $placeholders,
            $force,
            fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
            fn($q, $def) => $this->confirm($q, $def)
        );

        // 12) Migration => "create_users_table"
        if ($migration) {
            $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
            $migrationStub     = __DIR__ . "/../../stubs/model/{$migrationStubFile}";

            $timestamp    = date('Y_m_d_His');
            $migrationName= "{$timestamp}_create_{$tableName}_table.php";
            $migrationDest= database_path("migrations/{$migrationName}");
            DomainCommandHelper::generateStubFile(
                $migrationStub,
                $migrationDest,
                $placeholders,
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }

        // 13) Update the RepositoryServiceProvider
        $providerPath     = app_path('Providers/RepositoryServiceProvider.php');
        $bindingSignature = "\\App\\Domains\\{$directoryName}\\Repositories\\{$domain}RepositoryInterface::class";
        $bindingLine      = "\n        \$this->app->bind(\n"
            . "            \\App\\Domains\\{$directoryName}\\Repositories\\{$domain}RepositoryInterface::class,\n"
            . "            \\App\\Infrastructure\\Persistence\\Repositories\\{$domain}Repository::class\n"
            . "        );";

        DomainCommandHelper::updateRepositoryBinding(
            $providerPath,
            $bindingSignature,
            $bindingLine,
            fn($msg) => $this->info($msg)
        );

        // 14) Create CRUD actions => app/Actions/Users
        $actionsDir = app_path("Actions/{$directoryName}");
        DomainCommandHelper::createDirectoryIfNotExists($actionsDir, fn($m) => $this->info($m));

        $actionStubDir = __DIR__ . '/../../stubs/actions';
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

        foreach ($actionStubs as $stub => $fileName) {
            DomainCommandHelper::generateStubFile(
                "{$actionStubDir}/{$stub}",
                "{$actionsDir}/{$fileName}",
                $placeholders,
                $force,
                fn($msg, $warn=false) => $warn ? $this->warn($msg) : $this->info($msg),
                fn($q, $def) => $this->confirm($q, $def)
            );
        }

        $this->info("Domain {$directoryName} has been successfully created.");
        return 0;
    }
}
