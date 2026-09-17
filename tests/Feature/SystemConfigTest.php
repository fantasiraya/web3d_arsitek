<?php

use App\Domains\SystemConfig\Models\SystemSetting;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;

it('caches system settings and returns them', function () {
    $repo = app(SystemSettingRepository::class);

    // Create setting
    $repo->set('test_key', 'test_value', 'string');

    // Get from repo (should be cached)
    $all = $repo->all();
    expect($all)->toHaveKey('test_key', 'test_value');

    // Direct DB update
    SystemSetting::where('key', 'test_key')->update(['value' => 'new_value']);

    // Cache shouldn't reflect direct DB update without model events unless cleared manually
    expect($repo->get('test_key'))->toBe('test_value');

    // Update using model to trigger events
    $setting = SystemSetting::where('key', 'test_key')->first();
    $setting->value = 'model_updated_value';
    $setting->save();

    // Cache should be refreshed
    expect($repo->get('test_key'))->toBe('model_updated_value');
});

it('properly types system settings', function () {
    $repo = app(SystemSettingRepository::class);

    $repo->set('int_key', 123, 'integer');
    $repo->set('bool_key', true, 'boolean');
    $repo->set('json_key', ['a' => 1], 'json');

    expect($repo->get('int_key'))->toBe(123)
        ->and($repo->get('bool_key'))->toBeTrue()
        ->and($repo->get('json_key'))->toBe(['a' => 1]);
});
