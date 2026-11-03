<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real internet (e.g. send a real Telegram message
        // with the token from .env): a request without an Http::fake() fails the test
        Http::preventStrayRequests();
    }
}
