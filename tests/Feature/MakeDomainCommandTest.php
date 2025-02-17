<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Cleanup: remove any leftover test directories.
    File::deleteDirectory(app_path('Domains/TestUser'));
});

test('it scaffolds a domain without migration or soft-deletes', function () {
    // Run the command
    $exitCode = Artisan::call('make:domain TestUser');

    // Check exit code
    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path('Domains/TestUser')))->toBeTrue()
        ->and(File::exists(app_path('Domains/TestUser/Entities/TestUser.php')))->toBeTrue()
        ->and(File::exists(app_path('Models/TestUser.php')))->toBeTrue();

    // Check that a migration was NOT created
    $files = File::files(database_path('migrations'));
    $migrationCreated = collect($files)
        ->contains(fn ($file) => str_contains($file->getFilename(), 'create_test_users_table'));

    expect($migrationCreated)->toBeFalse();
});

test('it scaffolds a domain with migration and soft-deletes', function () {
    // Run the command with additional options
    $exitCode = Artisan::call('make:domain TestUser --migration --soft-deletes --force');

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path('Domains/TestUser/DataTransferObjects/TestUserData.php')))->toBeTrue()
        ->and(File::exists(app_path('Models/TestUser.php')))->toBeTrue();

    // Check that a migration was created
    $files = File::files(database_path('migrations'));
    $migrationFile = collect($files)
        ->first(fn ($file) => str_contains($file->getFilename(), 'create_test_users_table'));

    expect($migrationFile)->not->toBeNull();

    // Optionally, check contents of the migration
    $migrationContents = File::get($migrationFile->getPathname());
    expect($migrationContents)->toContain('$table->softDeletes();');

    // Because we used --force, no prompt was asked to overwrite anything.
});
