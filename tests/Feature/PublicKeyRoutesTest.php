<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\ReferralController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

uses(TestCase::class);

it('registers a public key route', function (string $path, string $uri, string $name, string $key, string $controller) {
    $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));

    expect($route)
        ->toBeInstanceOf(Route::class)
        ->and($route->uri())->toBe($uri)
        ->and($route->getName())->toBe($name)
        ->and($route->parameter('key'))->toBe($key)
        ->and($route->getControllerClass())->toBe($controller);
})->with([
    'job' => ['/job/job-key', 'job/{key}', 'job.show', 'job-key', JobController::class],
    'referral' => ['/referal/referral-key', 'referal/{key}', 'referral.show', 'referral-key', ReferralController::class],
]);

it('returns 404 for a malformed public key', function (string $routeName) {
    $this->get(route($routeName, ['key' => 'not-a-uuid']))
        ->assertNotFound();
})->with([
    'job' => 'job.show',
    'referral' => 'referral.show',
]);
