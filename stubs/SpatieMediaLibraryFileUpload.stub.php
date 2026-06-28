<?php

namespace Filament\Forms\Components;

class SpatieMediaLibraryFileUpload extends FileUpload
{
    /**
     * @param  mixed  $file
     * @return array<mixed>
     */
    public function getCustomProperties($file): array
    {
        return [];
    }

    /**
     * @return array<mixed>
     */
    public function getManipulations(): array
    {
        return [];
    }

    public function getConversionsDisk(): ?string
    {
        return null;
    }

    public function hasResponsiveImages(): bool
    {
        return false;
    }

    public function getCollection(): string
    {
        return '';
    }
}
