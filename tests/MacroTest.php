<?php

use Filament\Forms\Components\FileUpload;

it('registers the optimize macro on FileUpload', function () {
    expect(FileUpload::hasMacro('optimize'))->toBeTrue();
});

it('registers the resize macro on FileUpload', function () {
    expect(FileUpload::hasMacro('resize'))->toBeTrue();
});

it('registers the maxImageWidth macro on FileUpload', function () {
    expect(FileUpload::hasMacro('maxImageWidth'))->toBeTrue();
});

it('registers the maxImageHeight macro on FileUpload', function () {
    expect(FileUpload::hasMacro('maxImageHeight'))->toBeTrue();
});

it('can chain optimization methods', function () {
    $component = FileUpload::make('attachment')
        ->optimize('webp')
        ->resize(50)
        ->maxImageWidth(1000);

    // We can't easily assert internal state without reflection or public getters,
    // but ensuring no exception is thrown confirms the macros exist and return $this.
    expect($component)->toBeInstanceOf(FileUpload::class);
});
