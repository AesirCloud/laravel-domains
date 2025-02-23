<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // e.g. "UsersAb" => domain, "ProfilesCd" => subdomain
    $this->pluralDomain = 'Users' . Str::studly(Str::random(2));
    $this->pluralSubdomain = 'Profiles' . Str::studly(Str::random(2));

    // Clean up
    File::deleteDirectory(app_path("Domains/{$this->pluralDomain}"));
    File::deleteDirectory(app_path("Actions/{$this->pluralDomain}"));
    File::deleteDirectory(app_path('Models'));

    File::makeDirectory(app_path('Models'), 0755, true, true);

    // Create the parent domain
    Artisan::call('make:domain ' . $this->pluralDomain . ' --force');
});

test('it scaffolds a subdomain without migration or soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:subdomain ' . $this->pluralDomain . ' ' . $this->pluralSubdomain
    );
    expect($exitCode)->toBe(0);

    // e.g. app/Domains/UsersAb/ProfilesCd
    $basePath = app_path("Domains/{$this->pluralDomain}/{$this->pluralSubdomain}");
    expect(File::exists($basePath))->toBeTrue();

    // The subdomain class name is singular, e.g. "ProfileCd"
    $subdomainClass = Str::singular($this->pluralSubdomain);

    // Check entity
    expect(File::exists("{$basePath}/Entities/{$subdomainClass}.php"))->toBeTrue();
    // Check model
    expect(File::exists(app_path("Models/{$subdomainClass}.php")))->toBeTrue();

    // No migration
    $tableName = Str::snake(Str::plural($subdomainClass));
    $migrationExists = collect(File::files(database_path('migrations')))
        ->contains(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));
    expect($migrationExists)->toBeFalse();
});

test('it scaffolds a subdomain with migration and soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:subdomain ' . $this->pluralDomain . ' ' . $this->pluralSubdomain . ' --migration --soft-deletes --force'
    );
    expect($exitCode)->toBe(0);

    $subdomainClass = Str::singular($this->pluralSubdomain);
    $modelContents  = File::get(app_path("Models/{$subdomainClass}.php"));
    expect($modelContents)->toContain('use SoftDeletes;');

    // Confirm migration
    $tableName = Str::snake(Str::plural($subdomainClass));
    $migrationFile = collect(File::files(database_path('migrations')))
        ->first(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));
    expect($migrationFile)->not->toBeNull();

    $contents = File::get($migrationFile->getPathname());
    expect($contents)->toContain('$table->softDeletes();');
});
