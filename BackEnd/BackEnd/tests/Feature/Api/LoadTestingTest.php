<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class LoadTestingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function api_can_handle_multiple_simultaneous_requests()
    {
        $this->markTestSkipped('Este test requiere herramientas especiales para load testing');
        
        // Este test se puede implementar con herramientas como:
        // - Apache Bench (ab)
        // - Siege
        // - Artillery
        // - JMeter
        
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        $responses = [];
        $startTime = microtime(true);

        // Simular 50 requests simultáneas
        for ($i = 0; $i < 50; $i++) {
            $responses[] = $this->getJson('/api/trucking/loads');
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Verificar que todas las respuestas sean exitosas
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Verificar que el tiempo total sea razonable
        $this->assertLessThan(10, $totalTime, "50 requests tomaron demasiado tiempo: {$totalTime} segundos");
    }

    /** @test */
    public function api_responds_within_acceptable_time_limits()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        $endpoints = [
            'GET /api/auth/profile',
            'GET /api/modules',
            'GET /api/transportes',
            'GET /api/trucking/loads'
        ];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            
            [$method, $url] = explode(' ', $endpoint);
            $response = $this->json($method, $url);
            
            $endTime = microtime(true);
            $responseTime = $endTime - $startTime;

            $response->assertStatus(200);
            $this->assertLessThan(2, $responseTime, "Endpoint {$endpoint} tomó demasiado tiempo: {$responseTime} segundos");
        }
    }
}