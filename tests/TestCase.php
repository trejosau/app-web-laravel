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

    protected function requireDatabaseSchema(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('security_audit_logs')
        ) {
            $this->markTestSkipped('The configured test database schema is not migrated.');
        }
    }
}
