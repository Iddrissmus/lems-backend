<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every feature test runs on a freshly migrated MySQL test database
// (lems_backend_test, set in phpunit.xml), never on lems_backend.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
