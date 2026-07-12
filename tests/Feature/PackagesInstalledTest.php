<?php

use Illuminate\Support\Facades\Schema;

it('has the spatie permission and activitylog tables', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue();
    expect(Schema::hasTable('roles'))->toBeTrue();
    expect(Schema::hasTable('model_has_roles'))->toBeTrue();
    expect(Schema::hasTable('model_has_permissions'))->toBeTrue();
    expect(Schema::hasTable('role_has_permissions'))->toBeTrue();
    expect(Schema::hasTable('activity_log'))->toBeTrue();
});
