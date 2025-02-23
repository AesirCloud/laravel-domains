<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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
        // e.g. parentRaw="Users", subdomainRaw="Profiles"
        $parentRaw     = $this->argument('parent');
        $subdomainRaw  = $this->argument('name');

        // Directories remain plural
        $parentDirName    = Str::studly($parentRaw);    // e.g. "Users"
        $parentClassName  = Str::singular($parentDirName);  // "User"

        $subdomainDirName = Str::studly($subdomainRaw); // "Profiles"
        $subdomainClassName = Str::singular($subdomainDirName); // "Profile"

        // e.g. "app/Domains/Users/Profiles"
        $domainNamespace  = "App\\Domains\\{$parentDirName}\\{$subdomainDirName}";
        $actionsNamespace = "App\\Actions\\{$parentDirName}\\{$subdomainDirName}";

        // DB table => from subdomain singular => "profiles" if subdomainClassName="Profile"
        $tableName = Str::snake(Str::plural($subdomainClassName));

        $softDeletes     = $this->option('soft-deletes');
        $createMigration = $this->option('migration');

        $this->info("Creating subdomain: {$subdomainDirName} under parent domain: {$parentDirName}");

        // 1) Check if parent domain folder exists
        $parentDomainPath = app_path("Domains/{$parentDirName}");
        if (!File::exists($parentDomainPath)) {
            $this->error("Parent domain '{$parentDirName}' does not exist at '{$parentDomainPath}'.");
            return 1;
        }

        // 2) Create subdomain folder
        $baseDir = "{$parentDomainPath}/{$subdomainDirName}";
        if (File::exists($baseDir) && !$this->option('force')) {
            $this->error("Subdomain {$subdomainDirName} already exists. Use --force to overwrite.");
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
                File::makeDirectory($dir, 0755, true, true);
                $this->info("Created directory: {$dir}");
            } else {
                $this->warn("Directory already exists: {$dir}");
            }
        }

        // 3) Generate stubs for Entity, Repo, DomainService => singular className
        $stubPath = __DIR__ . '/../../stubs/domain';

        $domainStubs = [
            'Entity.stub'        => "{$baseDir}/Entities/{$subdomainClassName}.php",
            'Repository.stub'    => "{$baseDir}/Repositories/{$subdomainClassName}RepositoryInterface.php",
            'DomainService.stub' => "{$baseDir}/DomainServices/{$subdomainClassName}Service.php",
        ];
        foreach ($domainStubs as $stub => $dest) {
            $this->generateStubFile("{$stubPath}/{$stub}", $dest, [
                '{{ parentDirName }}'     => $parentDirName,
                '{{ parentClassName }}'   => $parentClassName,
                '{{ subdomainDirName }}'  => $subdomainDirName,
                '{{ subdomainClassName }}'=> $subdomainClassName,
            ]);
        }

        // 4) DTO => e.g. "ProfileData.php"
        $dtoStub = "{$stubPath}/DataTransferObject.stub";
        $dtoDest = "{$baseDir}/DataTransferObjects/{$subdomainClassName}Data.php";
        $this->generateStubFile($dtoStub, $dtoDest, [
            '{{ subdomainClassName }}' => $subdomainClassName,
        ]);

        // 5) Model => e.g. "app/Models/Profile.php"
        $modelStubFile = $softDeletes ? 'Model.soft.stub' : 'Model.stub';
        $modelStubPath = __DIR__ . "/../../stubs/model/{$modelStubFile}";
        $modelDest     = app_path("Models/{$subdomainClassName}.php");
        $this->generateStubFile($modelStubPath, $modelDest, [
            '{{ className }}' => $subdomainClassName, // "Profile"
            '{{ table }}'     => $tableName,          // "profiles"
        ]);

        // 6) Factory => "ProfileFactory.php"
        $factoryStubPath = __DIR__ . "/../../stubs/model/Factory.stub";
        $factoryDest     = database_path("factories/{$subdomainClassName}Factory.php");
        $this->generateStubFile($factoryStubPath, $factoryDest, [
            '{{ className }}' => $subdomainClassName,
        ]);

        // 7) Observer => "ProfileObserver.php" in app/Observers
        $observerDir = app_path('Observers');
        if (!File::exists($observerDir)) {
            File::makeDirectory($observerDir, 0755, true, true);
            $this->info("Created directory: {$observerDir}");
        }
        $observerStubFile = $softDeletes ? 'Observer.soft.stub' : 'Observer.stub';
        $observerDest     = app_path("Observers/{$subdomainClassName}Observer.php");
        $this->generateStubFile("{$stubPath}/{$observerStubFile}", $observerDest, [
            '{{ className }}' => $subdomainClassName,
        ]);

        // 8) Policy => "ProfilePolicy.php" in app/Policies
        $policyDir = app_path('Policies');
        if (!File::exists($policyDir)) {
            File::makeDirectory($policyDir, 0755, true, true);
            $this->info("Created directory: {$policyDir}");
        }
        $policyStubFile = $softDeletes ? 'Policy.soft.stub' : 'Policy.stub';
        $policyDest     = app_path("Policies/{$subdomainClassName}Policy.php");
        $this->generateStubFile("{$stubPath}/{$policyStubFile}", $policyDest, [
            '{{ className }}' => $subdomainClassName,
        ]);

        // 9) Eloquent Repository => "EloquentProfileRepository.php"
        $repoFile = $softDeletes
            ? __DIR__ . "/../../stubs/infrastructure/EloquentRepository.soft.stub"
            : __DIR__ . "/../../stubs/infrastructure/EloquentRepository.stub";
        $infraDir = app_path('Infrastructure/Persistence/Repositories');
        if (!File::exists($infraDir)) {
            File::makeDirectory($infraDir, 0755, true, true);
            $this->info("Created directory: {$infraDir}");
        }
        $repoDest = "{$infraDir}/Eloquent{$subdomainClassName}Repository.php";
        $this->generateStubFile($repoFile, $repoDest, [
            '{{ className }}' => $subdomainClassName,
        ]);

        // 10) Migration => e.g. "create_profiles_table.php"
        if ($this->option('migration')) {
            $migrationStubFile = $softDeletes ? 'Migration.soft.stub' : 'Migration.stub';
            $migrationStubPath = __DIR__ . "/../../stubs/model/{$migrationStubFile}";
            $timestamp         = date('Y_m_d_His');
            $migrationName     = "{$timestamp}_create_{$tableName}_table.php";
            $migrationDest     = database_path("migrations/{$migrationName}");
            $this->generateStubFile($migrationStubPath, $migrationDest, [
                '{{ className }}' => $subdomainClassName,
                '{{ table }}'     => $tableName,
            ]);
        }

        // 11) Update repository provider
        $this->updateProviderBinding($parentDirName, $subdomainDirName, $subdomainClassName);

        // 12) Subdomain actions => e.g. "app/Actions/Users/Profiles"
        $this->createSubdomainActions($parentDirName, $subdomainDirName, $subdomainClassName);

        $this->info("Subdomain {$subdomainDirName} has been created under parent domain {$parentDirName}.");
        return 0;
    }

    protected function createSubdomainActions(string $parentDirName, string $subdomainDirName, string $subdomainClassName): void
    {
        // e.g. "app/Actions/Users/Profiles"
        $actionsDir = app_path("Actions/{$parentDirName}/{$subdomainDirName}");
        if (!File::exists($actionsDir)) {
            File::makeDirectory($actionsDir, 0755, true, true);
            $this->info("Created actions directory: {$actionsDir}");
        }

        // We want "Create.php", "Update.php", etc. with no domain in the filename
        $actionStubs = [
            'Create.stub' => 'Create.php',
            'Update.stub' => 'Update.php',
            'Delete.stub' => 'Delete.php',
            'Index.stub'  => 'Index.php',
            'Show.stub'   => 'Show.php',
        ];
        if ($this->option('soft-deletes')) {
            $actionStubs['Restore.stub']     = 'Restore.php';
            $actionStubs['ForceDelete.stub'] = 'ForceDelete.php';
        }

        $actionStubPath = __DIR__ . '/../../stubs/actions';
        foreach ($actionStubs as $stub => $filename) {
            $this->generateStubFile("{$actionStubPath}/{$stub}", "{$actionsDir}/{$filename}", [
                '{{ className }}' => $subdomainClassName,
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
            $contents = str_replace(
                ['{{ ' . $search . ' }}'],
                [$replace],
                $contents
            );
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

    protected function updateProviderBinding(string $parentDirName, string $subdomainDirName, string $subdomainClassName): void
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

        // e.g. \App\Domains\Users\Profiles\Repositories\ProfileRepositoryInterface::class
        $bindingSignature = "\\App\\Domains\\{$parentDirName}\\{$subdomainDirName}\\Repositories\\{$subdomainClassName}RepositoryInterface::class";

        if (!str_contains($providerContent, $bindingSignature)) {
            $bindingLine = "\n        \$this->app->bind(\n"
                . "            \\App\\Domains\\{$parentDirName}\\{$subdomainDirName}\\Repositories\\{$subdomainClassName}RepositoryInterface::class,\n"
                . "            \\App\\Infrastructure\\Persistence\\Repositories\\Eloquent{$subdomainClassName}Repository::class\n"
                . "        );";

            $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';
            if (preg_match($pattern, $providerContent, $matches)) {
                $newMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];
                $providerContent = preg_replace($pattern, $newMethod, $providerContent, 1);
                File::put($providerPath, $providerContent);
                $this->info("Added subdomain binding to RepositoryServiceProvider.");
            } else {
                $this->warn("Could not locate register() method in RepositoryServiceProvider. Please add manually:");
                $this->line($bindingLine);
            }
        } else {
            $this->info("Repository binding for subdomain {$subdomainDirName} already exists.");
        }
    }
}
