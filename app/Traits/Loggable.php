<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Loggable Trait
 * 
 * Provides logging helper methods for classes.
 * Uses Laravel's Log facade with consistent formatting.
 */
trait Loggable
{
    /**
     * Log debug message
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug($this->formatLogMessage($message), $context);
    }

    /**
     * Log info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($this->formatLogMessage($message), $context);
    }

    /**
     * Log warning message
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($this->formatLogMessage($message), $context);
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($this->formatLogMessage($message), $context);
    }

    /**
     * Static log debug
     */
    protected static function staticLogDebug(string $message, array $context = []): void
    {
        Log::debug(static::formatStaticLogMessage($message), $context);
    }

    /**
     * Static log info
     */
    protected static function staticLogInfo(string $message, array $context = []): void
    {
        Log::info(static::formatStaticLogMessage($message), $context);
    }

    /**
     * Static log error
     */
    protected static function staticLogError(string $message, array $context = []): void
    {
        Log::error(static::formatStaticLogMessage($message), $context);
    }

    /**
     * Format log message with class name
     */
    private function formatLogMessage(string $message): string
    {
        return '[' . class_basename($this) . '] ' . $message;
    }

    /**
     * Format static log message with class name
     */
    private static function formatStaticLogMessage(string $message): string
    {
        return '[' . class_basename(static::class) . '] ' . $message;
    }
}
