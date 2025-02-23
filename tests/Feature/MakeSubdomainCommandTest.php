<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // e.g. "TestUserAb12" for parent, "AuthenticationLogsQr34" for subdomain
    $this->parentDomain = 'TestUser' . Str::studly(Str::random(4));
    $this->subdomain    = 'AuthenticationLogs' . Str::studly(Str::random(4));

    // Clear leftover migrations from the subdomain table name
    $subdomainTable = Str::snake(Str::plural($this->subdomain));
    collect(File::files(database_path('migrations')))->each(function ($file) use ($subdomainTable) {
        if (str_contains($file->getFilename(), "create_{$subdomainTable}_table")) {
            File::delete($file->getPathname());
        }
    });

    // Create the parent domain so the subdomain can nest
    Artisan::call('make:domain ' . $this->parentDomain . ' --force');
});

afterEach(function () {
    // Clean up the parent domain and subdomain
    File::deleteDirectory(app_path("Domains/{$this->parentDomain}"));
    File::deleteDirectory(app_path("Actions/{$this->parentDomain}"));

    // Remove model if created
    $modelPath = app_path("Models/{$this->subdomain}.php");
    if (File::exists($modelPath)) {
        File::delete($modelPath);
    }
});

test('it scaffolds a subdomain without migration or soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:subdomain ' . $this->parentDomain . ' ' . $this->subdomain
    );

    expect($exitCode)->toBe(0);

    $basePath = app_path("Domains/{$this->parentDomain}/{$this->subdomain}");
    expect(File::exists($basePath))->toBeTrue();

    // Check subdomain entity
    expect(File::exists("{$basePath}/Entities/{$this->subdomain}.php"))->toBeTrue();
    // Check model
    expect(File::exists(app_path("Models/{$this->subdomain}.php")))->toBeTrue();

    // Confirm no migration
    $subdomainTable = Str::snake(Str::plural($this->subdomain));
    $migrationExists = collect(File::files(database_path('migrations')))
        ->contains(fn($file) => str_contains($file->getFilename(), "create_{$subdomainTable}_table"));
    expect($migrationExists)->toBeFalse();
});

test('it scaffolds a subdomain with migration and soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:subdomain ' . $this->parentDomain . ' ' . $this->subdomain . ' --migration --soft-deletes --force'
    );

    expect($exitCode)->toBe(0);

    $basePath = app_path("Domains/{$this->parentDomain}/{$this->subdomain}");
    expect(File::exists($basePath))->toBeTrue();

    $modelContents = File::get(app_path("Models/{$this->subdomain}.php"));
    expect($modelContents)->toContain('use SoftDeletes;');

    // Confirm migration
    $subdomainTable = Str::snake(Str::plural($this->subdomain));
    $migrationFile = collect(File::files(database_path('migrations')))
        ->first(fn($file) => str_contains($file->getFilename(), "create_{$subdomainTable}_table"));

    expect($migrationFile)->not->toBeNull();

    $contents = File::get($migrationFile->getPathname());
    expect($contents)->toContain('$table->softDeletes();');
});
