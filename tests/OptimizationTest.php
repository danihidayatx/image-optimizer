<?php

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TestLivewireComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function getFormSchema(): array
    {
        return [
            // Schema defined in tests
        ];
    }
}

// Helper Functions for V2/V3 Compatibility
function createTestImage($width, $height, $color, $path)
{
    if (class_exists('Intervention\Image\ImageManagerStatic')) {
        $img = \Intervention\Image\ImageManagerStatic::canvas($width, $height, $color);
        $img->save($path);
    } else {
        $driver = class_exists('Intervention\Image\Drivers\Imagick\Driver')
            ? new \Intervention\Image\Drivers\Imagick\Driver
            : new \Intervention\Image\Drivers\Gd\Driver;
        $manager = new \Intervention\Image\ImageManager($driver);
        $img = $manager->create($width, $height)->fill($color);
        $img->save($path);
    }
}

function readTestImage($source)
{
    if (class_exists('Intervention\Image\ImageManagerStatic')) {
        return \Intervention\Image\ImageManagerStatic::make($source);
    } else {
        $driver = class_exists('Intervention\Image\Drivers\Imagick\Driver')
            ? new \Intervention\Image\Drivers\Imagick\Driver
            : new \Intervention\Image\Drivers\Gd\Driver;
        $manager = new \Intervention\Image\ImageManager($driver);

        return $manager->read($source);
    }
}

function getTestImageMime($image)
{
    if (method_exists($image, 'mime')) {
        return $image->mime();
    }

    return $image->origin()->mediaType();
}

beforeEach(function () {
    $fs = new \Illuminate\Filesystem\Filesystem;
    $fs->cleanDirectory(__DIR__ . '/temp');
    if (! file_exists(__DIR__ . '/temp/livewire-tmp')) {
        mkdir(__DIR__ . '/temp/livewire-tmp', 0777, true);
    }
});

function getConfiguredComponent(Closure $configure)
{
    $livewire = new TestLivewireComponent;

    // Adapt to breaking change in Filament v4/v5
    $componentContainerClass = class_exists(\Filament\Forms\ComponentContainer::class)
        ? \Filament\Forms\ComponentContainer::class
        : \Filament\Schemas\Schema::class;

    $container = $componentContainerClass::make($livewire)
        ->statePath('data')
        ->components([
            $component = FileUpload::make('attachment')
                ->disk('public')
                ->directory('uploads'),
        ]);

    $configure($component);

    $component->container($container);

    return $component;
}

it('optimizes image to webp format', function () {
    $filename = 'test.jpg';
    $imagePath = __DIR__ . '/temp/livewire-tmp/' . $filename;

    createTestImage(100, 100, '#ff0000', $imagePath);

    $file = new TemporaryUploadedFile($filename, 'public');

    $component = getConfiguredComponent(function ($c) {
        $c->optimize('webp');
    });

    // 3. Get Callback
    $reflection = new ReflectionClass($component);
    $property = $reflection->getProperty('saveUploadedFileUsing');
    $property->setAccessible(true);
    $callback = $property->getValue($component);

    expect($callback)->not->toBeNull();

    // 4. Execute the callback
    $storedPath = $callback($component, $file, null);

    // 5. Verify result
    expect($storedPath)->toContain('.webp');
    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();

    $content = Storage::disk('public')->get($storedPath);
    $savedImage = readTestImage($content);
    expect(getTestImageMime($savedImage))->toBe('image/webp');
});

it('resizes image by percentage', function () {
    $filename = 'resize_test.jpg';
    $imagePath = __DIR__ . '/temp/livewire-tmp/' . $filename;

    createTestImage(200, 200, '#00ff00', $imagePath);

    $file = new TemporaryUploadedFile($filename, 'public');

    $component = getConfiguredComponent(function ($c) {
        $c->resize(50);
    });

    $reflection = new ReflectionClass($component);
    $property = $reflection->getProperty('saveUploadedFileUsing');
    $property->setAccessible(true);
    $callback = $property->getValue($component);

    $storedPath = $callback($component, $file, null);

    $content = Storage::disk('public')->get($storedPath);
    $savedImage = readTestImage($content);

    expect($savedImage->width())->toBe(100);
    expect($savedImage->height())->toBe(100);
});

it('respects max width', function () {
    $filename = 'max_width_test.jpg';
    $imagePath = __DIR__ . '/temp/livewire-tmp/' . $filename;

    createTestImage(500, 200, '#0000ff', $imagePath);

    $file = new TemporaryUploadedFile($filename, 'public');

    $component = getConfiguredComponent(function ($c) {
        $c->maxImageWidth(250);
    });

    $reflection = new ReflectionClass($component);
    $property = $reflection->getProperty('saveUploadedFileUsing');
    $property->setAccessible(true);
    $callback = $property->getValue($component);

    $storedPath = $callback($component, $file, null);

    $content = Storage::disk('public')->get($storedPath);
    $savedImage = readTestImage($content);

    expect($savedImage->width())->toBe(250);
    expect($savedImage->height())->toBe(100);
});
