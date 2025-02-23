<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // Domain + subdomain in PascalCase
    $this->domainName = 'TestUser' . Str::studly(Str::random(4));
    $this->subdomain  = 'AuthenticationLogs' . Str::studly(Str::random(4));

    // Cleanup domain folder if leftover from a previous test
    File::deleteDirectory(app_path("Domains/{$this->domainName}"));
    File::deleteDirectory(app_path('ValueObjects'));
});

afterEach(function () {
    File::deleteDirectory(app_path("Domains/{$this->domainName}"));
    File::deleteDirectory(app_path('ValueObjects'));
});

test('it scaffolds a value object outside of any domain', function () {
    $voName = 'MyAwesomeVo' . Str::studly(Str::random(3)); // e.g. "MyAwesomeVoXyZ"
    $exitCode = Artisan::call('make:value-object ' . $voName . ' --force');

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("ValueObjects/{$voName}ValueObject.php")))->toBeTrue();
});

test('it scaffolds a value object in a specific domain', function () {
    // Create domain first
    Artisan::call('make:domain ' . $this->domainName . ' --force');

    // Then create the value object
    $voName = 'Address' . Str::studly(Str::random(3));
    $exitCode = Artisan::call(
        'make:value-object ' . $voName . ' --domain=' . $this->domainName
    );

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("Domains/{$this->domainName}/ValueObjects/{$voName}ValueObject.php")))->toBeTrue();
});

test('it scaffolds a value object in a subdomain', function () {
    // Ensure domain + subdomain
    Artisan::call('make:domain ' . $this->domainName . ' --force');
    Artisan::call('make:subdomain ' . $this->domainName . ' ' . $this->subdomain . ' --force');

    $voName = 'IpAddress' . Str::studly(Str::random(3));
    $exitCode = Artisan::call(
        'make:value-object ' . $voName . ' --domain=' . $this->domainName . ' --subdomain=' . $this->subdomain . ' --force'
    );

    expect($exitCode)->toBe(0)
        ->and(File::exists(app_path("Domains/{$this->domainName}/{$this->subdomain}/ValueObjects/{$voName}ValueObject.php")))->toBeTrue();
});
