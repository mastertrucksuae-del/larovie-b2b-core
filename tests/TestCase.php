<?php

namespace Tests;

use App\Models\Setting;
use App\Support\AccountInsights;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `Setting::current()` memoises the settings row in a static, which is
        // right for a request but leaks across tests: RefreshDatabase rolls the
        // row back while the stale model survives in memory, so one test's
        // settings silently apply to the next. Reset it per test.
        Setting::clearCache();
        AccountInsights::flush();
    }
}
