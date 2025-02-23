<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->pluralDomain    = 'Users' . Str::studly(Str::random(2));
    $this->pluralSubdomain = 'Profiles' . Str::studly(Str::random(2));

    File::deleteDirectory(app_path("Domains/{$this->pluralDomain}"));
    File::deleteDirectory(app_path('ValueObjects'));
});

test('it scaffolds a value object outside of any domain', function () {
    $voName = 'Address' . Str::studly(Str::random(2)); // e.g. "AddressAb"
    $exitCode = Artisan::call('make:value-object ' . $voName . ' --force');

    // Should create app/ValueObjects/AddressAbValueObject.php
    $fileName = $voName . 'ValueObject.php';
    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("ValueObjects/{$fileName}")))->toBeTrue();
});

test('it scaffolds a value object in a specific domain', function () {
    // Create the domain "UsersAb"
    Artisan::call('make:domain ' . $this->pluralDomain . ' --force');

    // Then create the value object
    $voName = 'Email' . Str::studly(Str::random(2)); // e.g. "EmailCd"
    $exitCode = Artisan::call(
        'make:value-object ' . $voName . ' --domain=' . $this->pluralDomain
    );

    $fileName = $voName . 'ValueObject.php';
    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("Domains/{$this->pluralDomain}/ValueObjects/{$fileName}")))->toBeTrue();
});

test('it scaffolds a value object in a subdomain', function () {
    // Create domain + subdomain
    Artisan::call('make:domain ' . $this->pluralDomain . ' --force');
    Artisan::call('make:subdomain ' . $this->pluralDomain . ' ' . $this->pluralSubdomain . ' --force');

    $voName = 'IpAddress' . Str::studly(Str::random(2));
    $exitCode = Artisan::call(
        'make:value-object ' . $voName
        . ' --domain=' . $this->pluralDomain
        . ' --subdomain=' . $this->pluralSubdomain
        . ' --force'
    );

    $fileName = $voName . 'ValueObject.php';
    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("Domains/{$this->pluralDomain}/{$this->pluralSubdomain}/ValueObjects/{$fileName}")))->toBeTrue();
});
