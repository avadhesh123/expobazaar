<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileStorage
{
    /**
     * Get the active storage disk based on environment
     * Production (scm.expobazaar.com) → s3
     * Development/staging → public
     */
    public static function disk(): string
    {
        return config('app.storage_disk', 'public');
    }

    /**
     * Get the Storage disk instance
     */
    public static function storage()
    {
        return Storage::disk(static::disk());
    }

    /**
     * Upload a file
     */
    public static function upload(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, static::disk());
    }

    /**
     * Store raw content (e.g., image data from base64)
     */
    public static function put(string $path, $contents): bool
    {
        return static::storage()->put($path, $contents);
    }

    /**
     * Append content to a file
     */
    public static function append(string $path, $contents): bool
    {
        return static::storage()->append($path, $contents);
    }

    /**
     * Get public URL for a file
     */
    public static function urlBAK(?string $path): string
    {
        if (!$path) {
            return '';
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return static::storage()->url($path);
    }
    public static function url(?string $path): string
    {
        if (!$path) {
            return '';
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $path = str_replace('storage/app/public/', '', $path);
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = ltrim($path, '/');

        $disk = static::disk();

        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60));
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Check if file exists
     */
    public static function exists(?string $path): bool
    {
        if (!$path) {
            return false;
        }
        return static::storage()->exists($path);
    }

    /**
     * Delete a file
     */
    public static function delete(?string $path): bool
    {
        if (!$path) {
            return false;
        }
        if (static::exists($path)) {
            return static::storage()->delete($path);
        }
        return false;
    }

    /**
     * Delete old file and upload new one
     */
    public static function replace(?string $oldPath, UploadedFile $file, string $folder): string
    {
        static::delete($oldPath);
        return static::upload($file, $folder);
    }

    /**
     * Get file contents
     */
    public static function get(?string $path): ?string
    {
        if (!$path || !static::exists($path)) {
            return null;
        }
        return static::storage()->get($path);
    }

    /**
     * Download response
     */
    public static function download(string $path, ?string $name = null)
    {
        return static::storage()->download($path, $name);
    }
}
