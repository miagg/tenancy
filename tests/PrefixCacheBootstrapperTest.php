<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Bootstrappers\PrefixCacheTenancyBootstrapper;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Listeners\BootstrapTenancy;

beforeEach(function () {
    config([
        'tenancy.bootstrappers' => [PrefixCacheTenancyBootstrapper::class],
        'cache.default' => 'redis',
    ]);

    Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
});

test('cache prefix is separate for each tenant', function () {
    $originalPrefix = config('cache.prefix') . ':';

    expect($originalPrefix)
        ->toBe(app('cache')->getPrefix())
        ->toBe(app('cache.store')->getPrefix());

    $tenant1 = Tenant::create();
    $tenant2 = Tenant::create();

    $tenantOnePrefix = 'tenant_' . $tenant1->id . ':';

    tenancy()->initialize($tenant1);
    expect($tenantOnePrefix)
        ->toBe(app('cache')->getPrefix())
        ->toBe(app('cache.store')->getPrefix());

    $tenantTwoPrefix = 'tenant_' . $tenant2->id . ':';

    tenancy()->initialize($tenant2);
    expect($tenantTwoPrefix)
        ->toBe(app('cache')->getPrefix())
        ->toBe(app('cache.store')->getPrefix());
});

test('cache is persisted when reidentification is used', function () {
    $tenant1 = Tenant::create();
    $tenant2 = Tenant::create();
    tenancy()->initialize($tenant1);

    cache(['foo' => 'bar'], 10);
    expect(cache('foo'))->toBe('bar');

    tenancy()->initialize($tenant2);
    tenancy()->end();

    tenancy()->initialize($tenant1);
    expect(cache('foo'))->toBe('bar');
});

test('prefix separate cache well enough', function () {
    $tenant1 = Tenant::create();
    tenancy()->initialize($tenant1);

    cache()->put('foo', 'bar', 1);
    expect(cache()->get('foo'))->toBe('bar');

    $tenant2 = Tenant::create();
    tenancy()->initialize($tenant2);

    pest()->assertNotSame('bar', cache()->get('foo'));

    cache()->put('foo', 'xyz', 1);
    expect(cache()->get('foo'))->toBe('xyz');
});
