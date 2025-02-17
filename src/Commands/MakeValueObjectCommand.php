<?php

namespace AesirCloud\LaravelDomains\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeValueObjectCommand extends Command
{
    protected $signature = 'make:value-object
                            {name : The name of the value object}
                            {--domain= : The domain this value object belongs to (optional)}
                            {--force : Overwrite any existing files without prompting}';

    protected $description = 'Scaffold a new value object. If a domain is provided, the value object will be placed in that domain’s folder.';

    public function handle()
    {
        // Get the provided name and optional domain
        $rawName      = $this->argument('name');
        $domainOption = $this->option('domain');

        // Append "ValueObject" if not already present
        if (!Str::endsWith($rawName, 'ValueObject')) {
            $rawName .= 'ValueObject'; // e.g. "OrderIdValueObject"
        }

        // If a domain is specified, studly-case it.
        if ($domainOption) {
            $domain = Str::studly($domainOption);
            $directory = app_path("Domains/{$domain}/ValueObjects");
        } else {
            $directory = app_path("ValueObjects");
        }

        // Ensure the directory exists
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true, true);
            $this->info("Created directory: {$directory}");
        }

        // Destination file path
        $destination = $directory . "/{$rawName}.php";

        // Locate the stub file
        $stubPath = __DIR__ . '/../../stubs/domain/ValueObject.stub';
        if (!File::exists($stubPath)) {
            $this->error("ValueObject stub not found at {$stubPath}");
            return 1;
        }

        // Read the stub and replace placeholder
        $contents = File::get($stubPath);
        $contents = str_replace('{{ name }}', $rawName, $contents);

        $this->createFile($destination, $contents);

        $this->info("Value Object {$rawName} has been successfully created.");
        return 0;
    }

    /**
     * Create or replace a file with given contents, respecting --force.
     *
     * @param string $destination
     * @param string $contents
     * @return void
     */
    protected function createFile($destination, $contents)
    {
        $force = $this->option('force');

        if (File::exists($destination) && !$force) {
            if (!$this->confirm("File {$destination} already exists. Overwrite it?", true)) {
                $this->info("Skipped file: {$destination}");
                return;
            }
        }

        File::put($destination, $contents);

        if (File::exists($destination)) {
            $this->info("Created/Replaced file: {$destination}");
        } else {
            $this->info("Created file: {$destination}");
        }
    }
}
