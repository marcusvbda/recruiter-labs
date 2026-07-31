<?php

use App\Enums\SocialNetwork;
use Tests\TestCase;

uses(TestCase::class);

it('resolves every social network case to a real translated label, not a raw translation key', function () {
    foreach (SocialNetwork::cases() as $network) {
        $label = $network->label();

        expect($label)->not->toStartWith('leads.networks.');
        expect($label)->not->toStartWith('candidates.networks.');
    }
});
