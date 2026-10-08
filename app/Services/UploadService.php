<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Secure image uploads.
 *
 * Defence in depth:
 *  - size limit from configuration;
 *  - extension AND detected MIME must both be on the allowlist;
 *  - the file must parse as an image of an accepted type;
 *  - randomised filename inside a generated storage path;
 *  - the web server is configured to never execute anything in uploads/.
 */
final class UploadService
{
    /** @var array<string,string> extension => expected mime */
    private const ALLOWED = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private Logger $logger, private string $publicPath)
    {
    }

    /**
     * @param array<string,mixed> $file  $_FILES entry
     * @param string              $folder logical subfolder, e.g. "fundraisers"
     * @return string relative path stored in the database, e.g. uploads/fundraisers/ab12.jpg
     */
    public function storeImage(array $file, string $folder): string
    {
        $this->assertUploadOk($file);

        $maxBytes = (int) Config::get('security.uploads.max_bytes', 4194304);
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('Images must be smaller than ' . $this->humanBytes($maxBytes) . '.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('That file could not be read as an upload.');
        }

        // Real content type, not the client-supplied one.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED[$extension]) || self::ALLOWED[$extension] !== $mime) {
            $this->logger->security('Rejected upload with unexpected type', [
                'extension' => $extension,
                'mime'      => $mime,
            ]);
            throw new InvalidArgumentException('Only JPG, PNG or WebP images are allowed.');
        }

        // Confirm it really is an image and get its dimensions.
        $info = @getimagesize($tmpPath);
        if ($info === false || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
            throw new InvalidArgumentException('That file is not a valid image.');
        }

        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?: 'misc';

        $relativeDir = trim((string) Config::get('security.uploads.directory', 'uploads'), '/') . '/' . $folder;
        $absoluteDir = rtrim($this->publicPath, '/') . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Unable to create the upload directory.');
        }

        $filename = gmdate('Ymd') . '-' . Str::randomHex(10) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        @chmod($absolutePath, 0644);

        // Downscale very large images in place to keep pages fast.
        $this->downscale($absolutePath, $mime, (int) Config::get('security.uploads.image_width', 1600));

        $this->logger->info('Upload stored', ['path' => $relativeDir . '/' . $filename]);

        return $relativeDir . '/' . $filename;
    }

    /** Open a remote/uploaded image and resize it if it exceeds the max width. */
    private function downscale(string $path, string $mime, int $maxWidth): void
    {
        if ($maxWidth <= 0 || !function_exists('imagecreatetruecolor')) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        [$width, $height] = $info;
        if ($width <= $maxWidth) {
            return;
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => null,
        };

        if ($source === false || $source === null) {
            return;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($maxWidth / $width));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($target, $path, 85),
            'image/png'  => imagepng($target, $path, 8),
            'image/webp' => imagewebp($target, $path, 85),
            default      => null,
        };

        imagedestroy($source);
        imagedestroy($target);
    }

    /** @param array<string,mixed> $file */
    private function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('No file was uploaded.');
        }

        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the form allows.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder available.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'The upload was blocked by a server extension.',
        ];

        if (isset($messages[$error])) {
            throw new InvalidArgumentException($messages[$error]);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The upload failed. Please try again.');
        }
    }

    /** Delete a previously stored upload, refusing to leave the uploads directory. */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $relativePath = ltrim($relativePath, '/');

        if (!str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return;
        }

        $absolute = rtrim($this->publicPath, '/') . '/' . $relativePath;

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function humanBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /** @return array<int,string> */
    public function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED);
    }
}
