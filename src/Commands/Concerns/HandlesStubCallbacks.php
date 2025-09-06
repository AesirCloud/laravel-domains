<?php

namespace AesirCloud\LaravelDomains\Commands\Concerns;

trait HandlesStubCallbacks
{
    protected function logger(): callable
    {
        return function (string $message, bool $warn = false): void {
            $warn ? $this->warn($message) : $this->info($message);
        };
    }

    protected function confirmOverwrite(): callable
    {
        return function (string $question, bool $default = true): bool {
            return $this->confirm($question, $default);
        };
    }
}
