<?php

namespace AesirCloud\LaravelDomains\Commands;

use AesirCloud\LaravelDomains\Commands\Concerns\HandlesStubCallbacks;
use AesirCloud\LaravelDomains\Helpers\DomainCommandHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    use HandlesStubCallbacks;

    protected $signature = 'make:domain
                            {name : The (plural) name of the domain, e.g. "Users"}
                            {--migration : Create a migration file}
                            {--soft-deletes : Include soft deletes functionality}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new domain (DTO, model, observer, policy, factory, repository, migration, actions, etc.)';

    public function handle(): int
    {
        $rawName       = $this->argument('name');
        $directoryName = Str::studly($rawName);
        $domain        = Str::singular($directoryName);
        $domainLower   = Str::camel($domain);

        $domainNamespace  = "App\\Domains\\{$directoryName}";
        $actionsNamespace = "App\\Actions\\{$directoryName}";

        $tableName   = Str::snake(Str::plural($domain));
        $softDeletes = $this->option('soft-deletes');
        $migration   = $this->option('migration');
        $force       = $this->option('force');

        $logger  = $this->logger();
        $confirm = $this->confirmOverwrite();

        $this->info("Creating domain: {$directoryName}");

        $baseDir = $this->createDomainDirectories($directoryName, $logger);

        $placeholders = [
            '{{ domainNamespace }}'  => $domainNamespace,
            '{{ actionsNamespace }}' => $actionsNamespace,
            '{{ domain }}'          => $domain,
            '{{ domainLower }}'     => $domainLower,
            '{{ table }}'           => $tableName,
        ];

        $stubPath = __DIR__ . '/../../stubs/domain';

        $this->generateDomainCore($stubPath, $baseDir, $domain, $softDeletes, $placeholders, $force, $logger, $confirm);

        $this->ensureBaseModel($force, $logger, $confirm);
        $this->generateModelArtifacts($domain, $softDeletes, $placeholders, $force, $logger, $confirm);
        $this->generateObserver($stubPath, $domain, $softDeletes, $placeholders, $force, $logger, $confirm);
        $this->generatePolicy($stubPath, $domain, $softDeletes, $placeholders, $force, $logger, $confirm);
        $this->generateConcreteRepository($domain, $softDeletes, $placeholders, $force, $logger, $confirm);

        if ($migration) {
            $this->generateMigration($domain, $tableName, $softDeletes, $placeholders, $force, $logger, $confirm);
        }

        $this->updateDomainBinding($directoryName, $domain);
        $this->createDomainActions($directoryName, $softDeletes, $placeholders, $force, $logger, $confirm);

        $this->info("Domain {$directoryName} has been successfully created.");
        return 0;
    }

    protected function createDomainDirectories(string $directoryName, callable $logger): string
    {
        $baseDir = app_path("Domains/{$directoryName}");
        $directories = [
            $baseDir,
            "{$baseDir}/Entities",
            "{$baseDir}/Repositories",
            "{$baseDir}/DomainServices",
            "{$baseDir}/DataTransferObjects",
        ];

        foreach ($directories as $dir) {
            DomainCommandHelper::createDirectoryIfNotExists($dir, fn($msg) => $logger($msg));
        }

        return $baseDir;
    }

    protected function generateDomainCore(string $stubPath, string $baseDir, string $domain, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        $repoInterfaceStub = $softDeletes ? 'RepositoryInterface.soft.stub' : 'RepositoryInterface.stub';
        $domainServiceStub = $softDeletes ? 'DomainService.soft.stub' : 'DomainService.stub';

        $domainStubs = [
            'Entity.stub'      => "{$baseDir}/Entities/{$domain}.php",
            $repoInterfaceStub => "{$baseDir}/Repositories/{$domain}RepositoryInterface.php",
            $domainServiceStub => "{$baseDir}/DomainServices/{$domain}Service.php",
        ];

        foreach ($domainStubs as $stub => $dest) {
            DomainCommandHelper::generateStubFile(
                "{$stubPath}/{$stub}",
                $dest,
                $placeholders,
                $force,
                $logger,
                $confirm
            );
        }

        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$domain}Data.php";
        DomainCommandHelper::generateStubFile($dtoStub, $dtoDest, $placeholders, $force, $logger, $confirm);
    }

    protected function ensureBaseModel(bool $force, callable $logger, callable $confirm): void
    {
        $baseModelPath = app_path('Models/BaseModel.php');
        if (! File::exists($baseModelPath)) {
            $baseModelStub = __DIR__ . '/../../stubs/model/BaseModel.stub';
            DomainCommandHelper::generateStubFile($baseModelStub, $baseModelPath, [], $force, $logger, $confirm);
        }
    }

    protected function generateModelArtifacts(string $domain, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelDest     = app_path("Models/{$domain}.php");
        DomainCommandHelper::generateStubFile(__DIR__ . "/../../stubs/model/{$modelStubFile}", $modelDest, $placeholders, $force, $logger, $confirm);

        $factoryStub = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest = database_path("factories/{$domain}Factory.php");
        DomainCommandHelper::generateStubFile($factoryStub, $factoryDest, $placeholders, $force, $logger, $confirm);
    }

    protected function generateObserver(string $stubPath, string $domain, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Observers'), fn($m) => $logger($m));
        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerDest     = app_path("Observers/{$domain}Observer.php");
        DomainCommandHelper::generateStubFile("{$stubPath}/{$observerStubFile}", $observerDest, $placeholders, $force, $logger, $confirm);
    }

    protected function generatePolicy(string $stubPath, string $domain, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Policies'), fn($m) => $logger($m));
        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyDest     = app_path("Policies/{$domain}Policy.php");
        DomainCommandHelper::generateStubFile("{$stubPath}/{$policyStubFile}", $policyDest, $placeholders, $force, $logger, $confirm);
    }

    protected function generateConcreteRepository(string $domain, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        DomainCommandHelper::createDirectoryIfNotExists(app_path('Infrastructure/Persistence/Repositories'), fn($m) => $logger($m));
        $concreteRepoStub = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/Repository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/Repository.stub";
        $concreteRepoDest = app_path("Infrastructure/Persistence/Repositories/{$domain}Repository.php");
        DomainCommandHelper::generateStubFile($concreteRepoStub, $concreteRepoDest, $placeholders, $force, $logger, $confirm);
    }

    protected function generateMigration(string $domain, string $tableName, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
        $migrationStub     = __DIR__ . "/../../stubs/model/{$migrationStubFile}";
        $timestamp     = date('Y_m_d_His');
        $migrationName = "{$timestamp}_create_{$tableName}_table.php";
        $migrationDest = database_path("migrations/{$migrationName}");
        DomainCommandHelper::generateStubFile($migrationStub, $migrationDest, $placeholders, $force, $logger, $confirm);
    }

    protected function updateDomainBinding(string $directoryName, string $domain): void
    {
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
    }

    protected function createDomainActions(string $directoryName, bool $softDeletes, array $placeholders, bool $force, callable $logger, callable $confirm): void
    {
        $actionsDir = app_path("Actions/{$directoryName}");
        DomainCommandHelper::createDirectoryIfNotExists($actionsDir, fn($m) => $logger($m));

        $actionStubDir = __DIR__ . '/../../stubs/actions';
        $actionStubs = [
            'Create.stub' => 'Create.php',
            'Update.stub' => 'Update.php',
            'Delete.stub' => 'Delete.php',
            'Index.stub'  => 'Index.php',
            'Show.stub'   => 'Show.php',
        ];
        if ($softDeletes) {
            $actionStubs['Restore.stub']     = 'Restore.php';
            $actionStubs['ForceDelete.stub'] = 'ForceDelete.php';
        }

        foreach ($actionStubs as $stub => $fileName) {
            DomainCommandHelper::generateStubFile(
                "{$actionStubDir}/{$stub}",
                "{$actionsDir}/{$fileName}",
                $placeholders,
                $force,
                $logger,
                $confirm
            );
        }
    }
}
