<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Single entry point for catalog image uploads. Handles:
 *  - downscaling source images to MAX_WIDTH (preserves aspect, strips EXIF)
 *  - storing under storage/app/public/<entity-folder>/<hashed-name>.<ext>
 *  - deleting the previously-stored file when replacing
 *  - URL resolution for Inertia responses
 *
 * To add a new entity (e.g. user avatars, business logos): register it in the
 * CONFIG map below — no changes elsewhere in the service.
 *
 * Forward-compat note: this is the single chokepoint a future migration to
 * spatie/laravel-medialibrary would change. Controllers don't know paths or
 * columns, only slot names ("single", "cluster", "logo").
 */
class CatalogImageService
{
    public const MAX_WIDTH = 1200;

    public const JPEG_QUALITY = 85;

    private const CONFIG = [
        Business::class => [
            'folder' => 'business-logos',
            'slots' => ['logo' => 'logo_path'],
            'max_width' => 400,
        ],
        User::class => [
            'folder' => 'user-avatars',
            'slots' => ['avatar' => 'avatar_path'],
            'max_width' => 400,
        ],
        Brand::class => [
            'folder' => 'brand-logos',
            'slots' => ['logo' => 'logo_path'],
        ],
        Material::class => [
            'folder' => 'material-images',
            'slots' => ['image' => 'image_path'],
        ],
        Texture::class => [
            'folder' => 'texture-images',
            'slots' => ['image' => 'image_path'],
        ],
        Shape::class => [
            'folder' => 'shape-images',
            'slots' => ['image' => 'image_path'],
        ],
        Size::class => [
            'folder' => 'size-images',
            'slots' => [
                'single' => 'single_image_file_path',
                'cluster' => 'cluster_image_file_path',
            ],
        ],
        ColorFamily::class => [
            'folder' => 'color-family-images',
            'slots' => [
                'single' => 'single_image_file_path',
                'cluster' => 'cluster_image_file_path',
            ],
        ],
        Color::class => [
            'folder' => 'color-images',
            'slots' => [
                'single' => 'single_image_file_path',
                'cluster' => 'cluster_image_file_path',
            ],
        ],
        Sku::class => [
            'folder' => 'sku-images',
            'slots' => [
                'single' => 'single_image_file_path',
                'cluster' => 'cluster_image_file_path',
            ],
        ],
    ];

    private readonly ImageManager $images;

    public function __construct(?ImageManager $images = null)
    {
        // Imagick gives better resize quality (Lanczos); fall back to GD when
        // the extension isn't available (e.g. local dev / CI without Imagick).
        $this->images = $images ?? new ImageManager(
            extension_loaded('imagick') ? new ImagickDriver : new GdDriver
        );
    }

    /**
     * Resize, store, and attach a new file to $slot. Deletes the previously
     * stored file (if any) and saves the model.
     */
    public function set(Model $model, string $slot, UploadedFile $file): void
    {
        $column = $this->columnFor($model, $slot);
        $folder = $this->folderFor($model);

        $mime = $file->getMimeType();
        $extension = $this->extensionFor($mime, $file);

        $maxWidth = self::CONFIG[$model::class]['max_width'] ?? self::MAX_WIDTH;

        if ($mime === 'image/svg+xml') {
            // SVGs pass through unchanged — they're already vector.
            $contents = (string) file_get_contents($file->getRealPath());
        } else {
            $image = $this->images->decode($file->getRealPath());
            if ($image->width() > $maxWidth) {
                $image->scale(width: $maxWidth);
            }
            $encoder = match ($mime) {
                'image/png' => new PngEncoder,
                'image/webp' => new WebpEncoder(quality: self::JPEG_QUALITY),
                'image/gif' => new GifEncoder,
                default => new JpegEncoder(quality: self::JPEG_QUALITY),
            };
            $contents = (string) $image->encode($encoder);
        }

        $path = $folder.'/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $contents);

        $this->deleteCurrent($model, $column);

        $model->setAttribute($column, $path);
        $model->save();
    }

    /**
     * Delete the stored file for $slot and null the column. Saves the model.
     */
    public function clear(Model $model, string $slot): void
    {
        $column = $this->columnFor($model, $slot);
        $this->deleteCurrent($model, $column);
        $model->setAttribute($column, null);
        $model->save();
    }

    /**
     * Public URL for the file in $slot, or null when unset.
     */
    public function url(Model $model, string $slot): ?string
    {
        $column = $this->columnFor($model, $slot);
        $path = $model->getAttribute($column);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Returns a [slot => url] map for every slot configured on $model.
     * Useful for stuffing into Inertia responses.
     *
     * @return array<string, ?string>
     */
    public function urls(Model $model): array
    {
        $slots = self::CONFIG[$model::class]['slots'] ?? throw new InvalidArgumentException('No image config for '.$model::class);

        return collect($slots)
            ->map(fn (string $column) => $model->getAttribute($column)
                ? Storage::disk('public')->url($model->getAttribute($column))
                : null
            )
            ->all();
    }

    private function deleteCurrent(Model $model, string $column): void
    {
        $previous = $model->getAttribute($column);
        if ($previous && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }
    }

    private function columnFor(Model $model, string $slot): string
    {
        $slots = self::CONFIG[$model::class]['slots']
            ?? throw new InvalidArgumentException('No image config for '.$model::class);

        return $slots[$slot]
            ?? throw new InvalidArgumentException("Unknown slot '$slot' for ".$model::class);
    }

    private function folderFor(Model $model): string
    {
        return self::CONFIG[$model::class]['folder']
            ?? throw new InvalidArgumentException('No image config for '.$model::class);
    }

    private function extensionFor(?string $mime, UploadedFile $file): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => $file->guessExtension() ?: 'jpg',
        };
    }
}
