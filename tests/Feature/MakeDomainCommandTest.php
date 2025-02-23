<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // We'll store a random domain name in PascalCase, e.g. "TestUserAb12"
    $this->domainName = 'TestUser' . Str::studly(Str::random(4));

    // Remove leftover migrations referencing this domain's table name
    $tableName = Str::snake(Str::plural($this->domainName));
    collect(File::files(database_path('migrations')))->each(function ($file) use ($tableName) {
        if (str_contains($file->getFilename(), "create_{$tableName}_table")) {
            File::delete($file->getPathname());
        }
    });
});

afterEach(function () {
    // Remove the domain folder and actions
    File::deleteDirectory(app_path("Domains/{$this->domainName}"));
    File::deleteDirectory(app_path("Actions/{$this->domainName}"));

    // Remove the model
    $modelPath = app_path("Models/{$this->domainName}.php");
    if (File::exists($modelPath)) {
        File::delete($modelPath);
    }
});

test('it scaffolds a domain without migration or soft-deletes', function () {
    // e.g. "make:domain TestUserAb12"
    $exitCode = Artisan::call('make:domain ' . $this->domainName);

    expect($exitCode)->toBe(0)
        // Domain folder
        ->and(File::exists(app_path("Domains/{$this->domainName}")))->toBeTrue()
        // Entity
        ->and(File::exists(app_path("Domains/{$this->domainName}/Entities/{$this->domainName}.php")))->toBeTrue()
        // Model
        ->and(File::exists(app_path("Models/{$this->domainName}.php")))->toBeTrue();

    // No migration should be present for this domain’s table
    $domainTable = Str::snake(Str::plural($this->domainName));
    $migrationCreated = collect(File::files(database_path('migrations')))
        ->contains(fn($file) => str_contains($file->getFilename(), "create_{$domainTable}_table"));
    expect($migrationCreated)->toBeFalse();
});

test('it scaffolds a domain with migration and soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:domain ' . $this->domainName . ' --migration --soft-deletes --force'
    );

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("Domains/{$this->domainName}/DataTransferObjects/{$this->domainName}Data.php")))->toBeTrue()
        ->and(File::exists(app_path("Models/{$this->domainName}.php")))->toBeTrue();

    // Confirm the migration was created
    $domainTable = Str::snake(Str::plural($this->domainName));
    $migrationFile = collect(File::files(database_path('migrations')))
        ->first(fn($file) => str_contains($file->getFilename(), "create_{$domainTable}_table"));

    expect($migrationFile)->not->toBeNull();

    // Confirm soft deletes
    $contents = File::get($migrationFile->getPathname());
    expect($contents)->toContain('$table->softDeletes();');
});
