<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeValueObjectCommand extends Command
{
    protected $signature = 'make:value-object
                            {name : The name of the value object}
                            {--domain= : The parent domain (e.g. "User")}
                            {--subdomain= : The subdomain (e.g. "AuthenticationLogs")}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new value object. If a domain/subdomain is provided, places the VO in that folder & namespace. Otherwise, uses app/ValueObjects.';

    public function handle(): int
    {
        $rawName = $this->argument('name');

        if (!Str::endsWith($rawName, 'ValueObject')) {
            $rawName .= 'ValueObject';
        }

        $domainOption    = $this->option('domain');

        $subdomainOption = $this->option('subdomain');

        $directory = null;

        $valueObjectNamespace = null;

        if ($domainOption) {
            $domain = Str::studly($domainOption);

            if ($subdomainOption) {
                $subdomain = Str::studly($subdomainOption);

                $valueObjectNamespace = "App\\Domains\\{$domain}\\{$subdomain}\\ValueObjects";

                $directory = app_path("Domains/{$domain}/{$subdomain}/ValueObjects");
            } else {
                $valueObjectNamespace = "App\\Domains\\{$domain}\\ValueObjects";

                $directory = app_path("Domains/{$domain}/ValueObjects");
            }
        } else {
            $valueObjectNamespace = "App\\ValueObjects";

            $directory = app_path("ValueObjects");
        }

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true, true);

            $this->info("Created directory: {$directory}");
        }

        $destination = "{$directory}/{$rawName}.php";

        $stubPath = __DIR__ . '/../../stubs/domain/ValueObject.stub';

        if (!File::exists($stubPath)) {
            $this->error("ValueObject stub not found: {$stubPath}");

            return 1;
        }

        // Load + replace placeholders
        $contents = File::get($stubPath);

        $contents = str_replace([
            '{{ valueObjectNamespace }}',
            '{{ name }}'
        ], [
            $valueObjectNamespace,
            $rawName
        ], $contents);

        $this->createFile($destination, $contents);

        $this->info("Value Object {$rawName} created at {$destination}.");

        return 0;
    }

    protected function createFile(string $destination, string $contents): void
    {
        $force = $this->option('force');

        if (File::exists($destination) && !$force) {
            if (!$this->confirm("File {$destination} already exists. Overwrite?", true)) {
                $this->info("Skipped file: {$destination}");

                return;
            }
        }

        File::put($destination, $contents);

        $this->info(File::exists($destination)
            ? "Created/Replaced file: {$destination}"
            : "Created file: {$destination}");
    }
}
