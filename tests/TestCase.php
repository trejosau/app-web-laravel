<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config(['recaptcha.enabled' => false]);
    }

    protected function requirePostgresSchema(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('security_audit_logs')
        ) {
            $this->markTestSkipped('PostgreSQL schema is not migrated in this environment.');
        }
    }
}
