<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(app_path('ValueObjects'));
    File::deleteDirectory(app_path('Domains/TestUser/ValueObjects'));
});

test('it scaffolds a value object outside of any domain', function () {
    $exitCode = Artisan::call('make:value-object MyAwesomeVo --force');

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path('ValueObjects/MyAwesomeVoValueObject.php')))->toBeTrue();
});

test('it scaffolds a value object in a specific domain', function () {
    $exitCode = Artisan::call('make:value-object Address --domain=TestUser');

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path('Domains/TestUser/ValueObjects/AddressValueObject.php')))->toBeTrue();
});
