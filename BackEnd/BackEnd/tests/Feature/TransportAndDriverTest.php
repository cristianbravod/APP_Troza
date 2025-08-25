<?php

// tests/Feature/TransportAndDriverTest.php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\TransportesPack;
use App\Models\ChoferesPack;
use Laravel\Sanctum\Sanctum;

class TransportAndDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }