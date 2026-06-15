<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageUploadService
{
    protected string $disk = 'private';

    public function saveOptimized(
        UploadedFile|TemporaryUploadedFile $file,
        string $folder = 'uploads',
        int $maxWidth = 1600,
        int $maxHeight = 1600,
        int $quality = 72,
        ?string $referenceFolder = null
    ): array {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return $this->saveOriginalPdf(
                file: $file,
                folder: $folder,
                referenceFolder: $referenceFolder
            );
        }

        return $this->saveImage(
            file: $file,
            folder: $folder,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            quality: $quality,
            referenceFolder: $referenceFolder
        );
    }

    protected function saveImage(
        UploadedFile|TemporaryUploadedFile $file,
        string $folder = 'uploads',
        int $maxWidth = 1600,
        int $maxHeight = 1600,
        int $quality = 72,
        ?string $referenceFolder = null
    ): array {
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file->getRealPath());
        $image = $image->scaleDown(width: $maxWidth, height: $maxHeight);
        $encoded = $image->toWebp($quality);

        [$path, $fileName] = $this->buildPath($folder, 'webp', $referenceFolder);

        Storage::disk($this->disk)->put($path, (string) $encoded);

        return [
            'path' => $path,
            'disk' => $this->disk,
            'mime' => 'image/webp',
            'size' => Storage::disk($this->disk)->size($path),
            'original_name' => $file->getClientOriginalName(),
            'source_type' => 'image',
            'file_name' => $fileName,
        ];
    }

    protected function saveOriginalPdf(
        UploadedFile|TemporaryUploadedFile $file,
        string $folder = 'uploads',
        ?string $referenceFolder = null
    ): array {
        [$path, $fileName] = $this->buildPath($folder, 'pdf', $referenceFolder);

        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath())
        );

        return [
            'path' => $path,
            'disk' => $this->disk,
            'mime' => 'application/pdf',
            'size' => Storage::disk($this->disk)->size($path),
            'original_name' => $file->getClientOriginalName(),
            'source_type' => 'pdf',
            'file_name' => $fileName,
        ];
    }

    protected function buildPath(
        string $folder,
        string $extension,
        ?string $referenceFolder = null
    ): array {
        $folder = trim($folder, '/');

        $datePath = now()->format('Y/m/d');

        $referenceFolder = $referenceFolder
            ? $this->sanitizeFolderName($referenceFolder)
            : null;

        $basePath = $folder . '/' . $datePath;

        if ($referenceFolder) {
            $basePath .= '/' . $referenceFolder;
        }

        $fileName = $referenceFolder
            ? $referenceFolder . '-' . Str::uuid() . '.' . $extension
            : Str::uuid() . '.' . $extension;

        $path = $basePath . '/' . $fileName;

        return [$path, $fileName];
    }

    public function deleteIfExists(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
    }

    protected function sanitizeFolderName(string $value): string
    {
        $value = trim($value);

        $value = preg_replace('/[\\\\\\/:\*\?"<>\|]+/', '-', $value);
        $value = preg_replace('/\s+/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);

        return trim($value, '-');
    }
}