<?php

namespace DaniHidayatX\ImageOptimizer\Tests;

use DaniHidayatX\ImageOptimizer\ImageOptimizerServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
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
            ActionsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
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
            'root' => __DIR__ . '/temp',
        ]);
        config()->set('filesystems.default', 'public');
    }
}
