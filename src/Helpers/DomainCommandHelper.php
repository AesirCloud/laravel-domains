<?php

namespace AesirCloud\LaravelDomains\Helpers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DomainCommandHelper
{
    /**
     * Create a directory if it does not exist, with logging.
     *
     * @param  string  $dir  The directory path
     * @param  callable  $logger  A logger function
     * @return void
     */
    public static function createDirectoryIfNotExists(string $dir, callable $logger): void
    {
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true, true);
            $logger("Created directory: {$dir}");
        } else {
            // Could also log as a warning
            $logger("Directory already exists: {$dir}");
        }
    }

    /**
     * Load a stub file, replace placeholders, and write the output,
     * respecting --force or user confirmation.
     *
     * @param  string  $stubPath  The path to the stub file
     * @param  string  $destination  The path to write the output file
     * @param  array  $placeholders  An associative array of placeholders and replacements
     * @param  bool  $forceOverwrite  Whether to overwrite the file without confirmation
     * @param  callable  $logger  A logger function
     * @param  callable  $confirmCallback  A callback to confirm overwriting
     * @return void
     */
    public static function generateStubFile(
        string $stubPath,
        string $destination,
        array $placeholders,
        bool $forceOverwrite,
        callable $logger,
        callable $confirmCallback
    ): void {
        if (! File::exists($stubPath)) {
            $logger("Stub file not found: {$stubPath}", true); // as a warning
            return;
        }
        $contents = File::get($stubPath);

        // Perform placeholder replacements
        foreach ($placeholders as $search => $replace) {
            // e.g. $search = '{{ domain }}'
            $contents = str_replace($search, $replace, $contents);
        }

        $existed = File::exists($destination);
        if ($existed && ! $forceOverwrite) {
            if (! $confirmCallback("File {$destination} already exists. Overwrite it?", true)) {
                $logger("Skipped file: {$destination}");
                return;
            }
        }

        $result = File::put($destination, $contents);
        if ($result === false) {
            $logger("Failed to write file: {$destination}", true);
            return;
        }

        $logger(($existed ? 'Replaced' : 'Created') . " file: {$destination}");
    }

    /**
     * Insert a binding line into the RepositoryServiceProvider's register() method,
     * e.g. binding an interface to a concrete repository class.
     *
     * @param  string  $providerPath  The path to the RepositoryServiceProvider file
     * @param  string  $bindingSignature  The signature of the binding line
     * @param  string  $bindingLine  The binding line to insert
     * @param  callable  $logger  A logger function
     * @return void
     */
    public static function updateRepositoryBinding(
        string $providerPath,
        string $bindingSignature,
        string $bindingLine,
        callable $logger
    ): void {
        if (! File::exists($providerPath)) {
            // If not found, attempt to create a new provider
            $logger("RepositoryServiceProvider not found. Creating one...");
            Artisan::call('make:provider RepositoryServiceProvider');
            $logger("RepositoryServiceProvider created.");

            // If still not found, stop
            if (! File::exists($providerPath)) {
                return;
            }
        }

        $providerContent = File::get($providerPath);

        if (! str_contains($providerContent, $bindingSignature)) {
            // Attempt to insert inside: public function register() { ... }
            $pattern = '/(public function register\(\)(?:\s*:\s*\w+)?\s*\{\s*)([^}]*)(\})/s';
            if (preg_match($pattern, $providerContent, $matches)) {
                $newMethod = $matches[1] . $matches[2] . $bindingLine . "\n" . $matches[3];
                $providerContent = preg_replace($pattern, $newMethod, $providerContent, 1);
                File::put($providerPath, $providerContent);

                $logger("Added repository binding to RepositoryServiceProvider.");
            } else {
                $logger("Could not locate register() method in RepositoryServiceProvider. Please add manually:");
                $logger($bindingLine);
            }
        } else {
            $logger("Repository binding already exists.");
        }
    }
}
