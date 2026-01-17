<?php

namespace DaniHidayatX\ImageOptimizer\Tests;

use DaniHidayatX\ImageOptimizer\ImageOptimizerServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            FormsServiceProvider::class,
            ImageOptimizerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        
        // Setup fake disk for testing uploads
        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => __DIR__.'/temp',
        ]);
        config()->set('filesystems.default', 'public');
    }
}
