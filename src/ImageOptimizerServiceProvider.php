<?php

namespace DaniHidayatX\ImageOptimizer;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ImageOptimizerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'image-optimizer';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        $this->registerMacros();
        $this->publishStubs();
    }

    protected function registerMacros(): void
    {
        // Optimization Settings Macros
        FileUpload::macro('optimize', function (string | Closure | null $format = 'webp', ?int $quality = null) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['format'] = $format;
            $this->imageOptimization['quality'] = $quality;
            $this->ensureOptimizerHook();

            return $this;
        });

        FileUpload::macro('resize', function (int | Closure | null $percent = 50) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['resize'] = $percent;
            $this->ensureOptimizerHook();

            return $this;
        });

        FileUpload::macro('maxImageWidth', function (int | Closure | null $width) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['max_width'] = $width;
            $this->ensureOptimizerHook();

            return $this;
        });

        FileUpload::macro('maxImageHeight', function (int | Closure | null $height) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['max_height'] = $height;
            $this->ensureOptimizerHook();

            return $this;
        });

        // Spatie Specific Macros (for compatibility)
        FileUpload::macro('mediaName', function (string | Closure | null $name) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['media_name'] = $name;
            return $this;
        });

        FileUpload::macro('customHeaders', function (array | Closure | null $headers) {
            $this->imageOptimization = $this->imageOptimization ?? [];
            $this->imageOptimization['custom_headers'] = $headers;
            return $this;
        });

        // Hook Registration
        FileUpload::macro('ensureOptimizerHook', function () {
            if ($this->hasOptimizerHook ?? false) {
                return;
            }
            $this->hasOptimizerHook = true;

            $this->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file, ?Model $record = null) {
                if ($component->isSpatieComponent()) {
                    return $component->processAndStoreSpatie($file, $record);
                }

                return $component->processAndStoreImage($file);
            });
        });

        // Helper to check for Spatie component
        FileUpload::macro('isSpatieComponent', function () {
            return class_exists('\Filament\Forms\Components\SpatieMediaLibraryFileUpload') &&
                   $this instanceof \Filament\Forms\Components\SpatieMediaLibraryFileUpload;
        });

        // Standard FileUpload Logic
        FileUpload::macro('processAndStoreImage', function (TemporaryUploadedFile $file) {
            /** @var FileUpload $this */
            $settings = $this->imageOptimization ?? [];
            $format = $this->evaluate($settings['format'] ?? null);
            $resize = $this->evaluate($settings['resize'] ?? null);
            $maxWidth = $this->evaluate($settings['max_width'] ?? null);
            $maxHeight = $this->evaluate($settings['max_height'] ?? null);
            $quality = $settings['quality'] ?? null;

            $filename = $this->getUploadedFileNameForStorage($file);
            
            $mime = $file->getMimeType();
            $isImage = str_contains((string) $mime, 'image');
            if (! $isImage) {
                $ext = strtolower($file->getClientOriginalExtension());
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
            }

            if (
                $isImage &&
                ($format || $resize || $maxWidth || $maxHeight)
            ) {
                $image = Image::make($file);

                if ($format) {
                    $quality = $quality ?? ($format === 'jpeg' || $format === 'jpg' ? 70 : null);
                }

                $shouldResize = false;
                $imageWidth = null;
                $imageHeight = null;

                if ($maxWidth && $image->width() > $maxWidth) {
                    $shouldResize = true;
                    $imageWidth = $maxWidth;
                }

                if ($maxHeight && $image->height() > $maxHeight) {
                    $shouldResize = true;
                    $imageHeight = $maxHeight;
                }

                if ($resize) {
                    $shouldResize = true;
                    if ($image->height() > $image->width()) {
                        $imageHeight = $image->height() - ($image->height() * ($resize / 100));
                    } else {
                        $imageWidth = $image->width() - ($image->width() * ($resize / 100));
                    }
                }

                if ($shouldResize) {
                    $image->resize($imageWidth, $imageHeight, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                if ($format) {
                    $compressedImage = $image->encode($format, $quality);
                    
                    // Update filename extension
                    $extension = strrpos($filename, '.');
                    if ($extension !== false) {
                        $filename = substr($filename, 0, $extension + 1) . $format;
                    } else {
                        $filename .= '.' . $format;
                    }
                } else {
                    $compressedImage = $image->encode();
                }

                Storage::disk($this->getDiskName())->put(
                    $this->getDirectory() . '/' . $filename,
                    (string) $compressedImage
                );

                return $this->getDirectory() . '/' . $filename;
            }

            return $this->storeUploadedFileToDisk($file);
        });

        // Spatie FileUpload Logic
        FileUpload::macro('processAndStoreSpatie', function (TemporaryUploadedFile $file, ?Model $record) {
            /** @var \Filament\Forms\Components\SpatieMediaLibraryFileUpload $this */
            
            if (! $record || ! method_exists($record, 'addMediaFromString')) {
                return null;
            }

            $settings = $this->imageOptimization ?? [];
            $format = $this->evaluate($settings['format'] ?? null);
            $resize = $this->evaluate($settings['resize'] ?? null);
            $maxWidth = $this->evaluate($settings['max_width'] ?? null);
            $maxHeight = $this->evaluate($settings['max_height'] ?? null);
            $quality = $settings['quality'] ?? null;
            
            $filename = $this->getUploadedFileNameForStorage($file);
            $content = $file->get();

            $mime = $file->getMimeType();
            $isImage = str_contains((string) $mime, 'image');
            if (! $isImage) {
                $ext = strtolower($file->getClientOriginalExtension());
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
            }

            if (
                $isImage &&
                ($format || $resize || $maxWidth || $maxHeight)
            ) {
                $image = Image::make($content);

                if ($format) {
                    $quality = $quality ?? ($format === 'jpeg' || $format === 'jpg' ? 70 : null);
                }

                $shouldResize = false;
                $imageWidth = null;
                $imageHeight = null;

                if ($maxWidth && $image->width() > $maxWidth) {
                    $shouldResize = true;
                    $imageWidth = $maxWidth;
                }

                if ($maxHeight && $image->height() > $maxHeight) {
                    $shouldResize = true;
                    $imageHeight = $maxHeight;
                }

                if ($resize) {
                    $shouldResize = true;
                    if ($image->height() > $image->width()) {
                        $imageHeight = $image->height() - ($image->height() * ($resize / 100));
                    } else {
                        $imageWidth = $image->width() - ($image->width() * ($resize / 100));
                    }
                }

                if ($shouldResize) {
                    $image->resize($imageWidth, $imageHeight, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                if ($format) {
                    $content = (string) $image->encode($format, $quality);
                    $filename = self::formatFilename($filename, $format); // Use helper if available, or logic below
                    
                    // Update filename extension locally if helper not available on component
                    // But component has formatFilename static method in original code? 
                    // No, it was on the subclass. We should replicate logic here.
                    $extension = strrpos($filename, '.');
                    if ($extension !== false) {
                        $filename = substr($filename, 0, $extension + 1) . $format;
                    } else {
                        $filename .= '.' . $format;
                    }
                } else {
                    $content = (string) $image->encode();
                }
            }

            $mediaAdder = $record->addMediaFromString($content);
            
            // Apply Spatie Options
            if ($name = $this->evaluate($settings['media_name'] ?? null)) {
                 $mediaAdder->usingName($name);
            } else {
                 // Fallback to client original name if not set
                 $mediaAdder->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            }

            if ($headers = $this->evaluate($settings['custom_headers'] ?? null)) {
                $mediaAdder->addCustomHeaders($headers);
            }
            
            // Standard Spatie component methods
            $mediaAdder
                ->usingFileName($filename)
                ->toMediaCollection($this->getCollection(), $this->getDiskName());
                
            // Note: Other Spatie options (properties, manipulations, etc.) 
            // are usually handled by the standard component's logic or would require 
            // more macros if we wanted full 1:1 on options that standard component supports.
            // The standard component applies them in `saveUploadedFiles` loop usually?
            // Wait, we are BYPASSING standard saveUploadedFileUsing.
            // So we MUST apply everything the standard component applies.
            
            if (method_exists($this, 'getCustomProperties')) {
                $mediaAdder->withCustomProperties($this->getCustomProperties());
            }
            if (method_exists($this, 'getManipulations')) {
                $mediaAdder->withManipulations($this->getManipulations());
            }
            if (method_exists($this, 'getConversionsDisk') && $disk = $this->getConversionsDisk()) {
                $mediaAdder->storingConversionsOnDisk($disk);
            }
            if (method_exists($this, 'hasResponsiveImages') && $this->hasResponsiveImages()) {
                $mediaAdder->withResponsiveImages();
            }

            return $mediaAdder->toMediaCollection($this->getCollection(), $this->getDiskName())->getAttributeValue('uuid');
        });
        
        // Standard FileUpload storeFile logic (fallback)
        FileUpload::macro('storeUploadedFileToDisk', function (TemporaryUploadedFile $file) {
            /** @var FileUpload $this */
            $storeMethod = $this->getVisibility() === 'public' ? 'storePubliclyAs' : 'storeAs';

            if (
                $this->shouldMoveFiles() &&
                method_exists($file, 'getDisk') &&
                $this->getDiskName() === $file->getDisk()
            ) {
                $newPath = trim($this->getDirectory() . '/' . $this->getUploadedFileNameForStorage($file), '/');
                $this->getDisk()->move($file->path(), $newPath);

                return $newPath;
            }

            return $file->{$storeMethod}(
                $this->getDirectory(),
                $this->getUploadedFileNameForStorage($file),
                $this->getDiskName()
            );
        });
    }

    protected function publishStubs(): void
    {
        if (app()->runningInConsole()) {
            $filesystem = app(Filesystem::class);
            $stubsPath = __DIR__ . '/../stubs/';
            if ($filesystem->exists($stubsPath)) {
                foreach ($filesystem->files($stubsPath) as $file) {
                    $this->publishes([
                        $file->getRealPath() => base_path("stubs/image-optimizer/{$file->getFilename()}"),
                    ], 'image-optimizer-stubs');
                }
            }
        }
    }
}
