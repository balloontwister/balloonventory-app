<?php

namespace App\Services;

use App\Models\BalloonSize;
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
use enshrined\svgSanitize\Sanitizer as SvgSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Single entry point for image uploads attached to Eloquent models. Handles:
 *  - downscaling source images to MAX_WIDTH (preserves aspect, strips EXIF)
 *  - sanitizing SVGs (strips active content + external refs)
 *  - storing under storage/app/public/<entity-folder>/<hashed-name>.<ext>
 *  - deleting the previously-stored file when replacing
 *  - URL resolution for Inertia responses
 *
 * Used by catalog admin (brands, SKUs, colors, reference tables) as well as
 * user avatars and business logos. To add a new entity, register it in the
 * CONFIG map below — no changes elsewhere in the service.
 *
 * Forward-compat note: this is the single chokepoint a future migration to
 * spatie/laravel-medialibrary would change. Controllers don't know paths or
 * columns, only slot names ("single", "cluster", "logo").
 */
class ImageAttachmentService
{
    public const MAX_WIDTH = 1200;

    public const JPEG_QUALITY = 85;

    /**
     * Hard cap on source-image dimensions. Defends against pixel-bomb uploads
     * (tiny file payload, huge declared width/height) that would OOM the
     * decoder. Legitimate photos rarely exceed 4000–6000px.
     */
    public const MAX_SOURCE_DIMENSION = 8000;

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
        BalloonSize::class => [
            'folder' => 'balloon-size-images',
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
     *
     * Write order is: store new → save DB → delete old. If the DB save fails,
     * the new file is rolled back so the model never points at a missing path.
     */
    public function set(Model $model, string $slot, UploadedFile $file): void
    {
        $column = $this->columnFor($model, $slot);
        $folder = $this->folderFor($model);

        $mime = $file->getMimeType();
        $extension = $this->extensionFor($mime, $file);

        $maxWidth = self::CONFIG[$model::class]['max_width'] ?? self::MAX_WIDTH;

        if ($mime === 'image/svg+xml') {
            $contents = $this->sanitizeSvg((string) file_get_contents($file->getRealPath()), $slot);
        } else {
            $this->guardSourceDimensions($file, $slot);
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

        $previous = $model->getAttribute($column);
        $path = $folder.'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($path, $contents);

        $model->setAttribute($column, $path);
        try {
            $model->save();
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * Delete the stored file for $slot and null the column. Saves the model.
     *
     * Order is: save DB → delete file. A no-op when no file is attached.
     */
    public function clear(Model $model, string $slot): void
    {
        $column = $this->columnFor($model, $slot);
        $previous = $model->getAttribute($column);

        if ($previous === null) {
            return;
        }

        $model->setAttribute($column, null);
        $model->save();

        Storage::disk('public')->delete($previous);
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

    /**
     * Reject raster images whose declared dimensions exceed MAX_SOURCE_DIMENSION
     * before they hit the decoder. Files validated as `image:*` should always
     * have readable dimensions; failure to read also rejects (corrupt header).
     */
    private function guardSourceDimensions(UploadedFile $file, string $slot): void
    {
        $size = @getimagesize($file->getRealPath());

        if ($size === false || $size[0] > self::MAX_SOURCE_DIMENSION || $size[1] > self::MAX_SOURCE_DIMENSION) {
            throw ValidationException::withMessages([
                $slot => __('validation.custom.image.dimensions_too_large', [
                    'max' => self::MAX_SOURCE_DIMENSION,
                ]),
            ]);
        }
    }

    /**
     * Strip scripts, event handlers, foreignObject, and other active content
     * from an SVG payload. Throws a validation error if parsing fails or the
     * payload sanitizes down to nothing usable.
     *
     * Why: SVGs served from the app origin will execute embedded JavaScript
     * (XSS) unless cleaned. The image validation rule alone is not enough.
     * A second pass strips http(s)/protocol-relative refs that the library
     * leaves intact — those don't run JS but can leak viewer IP/UA to the
     * uploader via auto-loaded <image>/<use>/<feImage> elements.
     */
    private function sanitizeSvg(string $raw, string $slot): string
    {
        $sanitizer = new SvgSanitizer;
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($raw);

        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages([
                $slot => __('validation.custom.image.svg_unsafe'),
            ]);
        }

        return $this->stripExternalRefs($clean);
    }

    /**
     * Remove any attribute whose value points off-origin (http://, https://,
     * //example.com, ftp://, file://). Preserves fragment refs (#id), relative
     * paths, and whitelisted data: URIs left intact by the library.
     */
    private function stripExternalRefs(string $svg): string
    {
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($svg);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $svg;
        }

        $xpath = new \DOMXPath($doc);
        $pattern = '~^\s*(?:https?:|ftp:|file:)?//~i';

        foreach ($xpath->query('//*') as $element) {
            foreach (iterator_to_array($element->attributes) as $attr) {
                if (preg_match($pattern, (string) $attr->value)) {
                    $element->removeAttributeNode($attr);
                }
            }
        }

        return (string) $doc->saveXML();
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
