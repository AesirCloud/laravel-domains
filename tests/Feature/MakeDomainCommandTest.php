<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| MakeDomainCommandTest
|--------------------------------------------------------------------------
|
| We'll create "Users" -> expect "User.php" entity, "User.php" model,
| "app/Actions/Users/Create.php" etc.
|
*/
beforeEach(function () {
    $this->pluralDomain = 'Users' . Str::studly(Str::random(3)); // e.g. "UsersXyz"
    // Clean up
    File::deleteDirectory(app_path("Domains/{$this->pluralDomain}"));
    File::deleteDirectory(app_path("Actions/{$this->pluralDomain}"));

    // Also remove migrations referencing "usersxyz" or similar
    $tableName = Str::snake(Str::plural(Str::singular($this->pluralDomain)));
    collect(File::files(database_path('migrations')))->each(function ($file) use ($tableName) {
        if (str_contains($file->getFilename(), "create_{$tableName}_table")) {
            File::delete($file->getPathname());
        }
    });
});

test('it scaffolds a domain without migration or soft-deletes', function () {
    // e.g. "make:domain UsersXyz"
    $exitCode = Artisan::call('make:domain ' . $this->pluralDomain);

    expect($exitCode)->toBe(0);

    // Check folder: app/Domains/UsersXyz
    expect(File::exists(app_path("Domains/{$this->pluralDomain}")))->toBeTrue();

    // We expect singular class => "UserXyz" => actually we do Str::singular( e.g. "UsersXyz" -> "UsersXy" ?
    // Actually if "UsersXyz" => singular => "UsersXy"? This might be odd. So let's just see if the command's logic is correct.
    // We'll do a safer approach: the code is Str::singular. If you typed "UsersXyz", it tries to remove the last 's' => "UserXyz".
    $singularName = Str::singular($this->pluralDomain);
    // e.g. "UserXyz"

    // Check entity
    expect(File::exists(app_path("Domains/{$this->pluralDomain}/Entities/{$singularName}.php")))->toBeTrue();
    // Check model
    expect(File::exists(app_path("Models/{$singularName}.php")))->toBeTrue();
    // No migration
    $tableName = Str::snake(Str::plural($singularName));
    $migrationExists = collect(File::files(database_path('migrations')))
        ->contains(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));
    expect($migrationExists)->toBeFalse();
});

test('it scaffolds a domain with migration and soft-deletes', function () {
    $exitCode = Artisan::call(
        'make:domain ' . $this->pluralDomain . ' --migration --soft-deletes --force'
    );

    expect($exitCode)->toBe(0);

    $singularName = Str::singular($this->pluralDomain);

    // Check DataTransferObjects
    expect(File::exists(app_path("Domains/{$this->pluralDomain}/DataTransferObjects/{$singularName}Data.php")))->toBeTrue();
    // Check model
    expect(File::exists(app_path("Models/{$singularName}.php")))->toBeTrue();

    // Migration => create_{tableName}_table
    $tableName = Str::snake(Str::plural($singularName));
    $migrationFile = collect(File::files(database_path('migrations')))
        ->first(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));
    expect($migrationFile)->not->toBeNull();

    // Confirm soft deletes
    $contents = File::get($migrationFile->getPathname());
    expect($contents)->toContain('$table->softDeletes();');
});
