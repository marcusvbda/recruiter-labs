<?php

use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Tests\TestCase;

uses(TestCase::class);

it('configures the Filament panel branding assets', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getFavicon())->toBe(asset('assets/image/favicon.png').'?v=2')
        ->and($panel->getBrandLogo())->toBe(asset('assets/image/logo.png'))
        ->and($panel->getBrandLogoHeight())->toBe('3rem')
        ->and($panel->getMaxContentWidth())->toBe(Width::Full);
});
