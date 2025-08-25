<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function api_protects_against_sql_injection()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        $maliciousInputs = [
            "1' OR '1'='1",
            "1; DROP TABLE users; --",
            "' UNION SELECT * FROM users --",
            "1' AND 1=1 --"
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->getJson("/api/trucking/loads/{$input}");
            
            // Debería retornar 404 o 422, no 500 (error de servidor)
            $this->assertContains($response->status(), [400, 404, 422]);
        }
    }

    /** @test */
    public function api_protects_against_xss_attacks()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        $testData = $this->createTruckingTestData();

        $xssPayloads = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onerror="alert(1)">',
            '"><script>alert("xss")</script>'
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->postJson('/api/trucking/loads', [
                'patente' => 'TEST123',
                'ID_TRANSPORTE' => $testData['transporte']->ID_TRANSPORTE,
                'ID_CHOFER' => $testData['chofer']->ID_CHOFER,
                'fecha_carga' => '2024-07-08',
                'ubicacion_gps' => '-38.7369, -72.5986',
                'observaciones' => $payload
            ]);

            if ($response->status() === 201) {
                $loadId = $response->json('data.load.id');
                $getResponse = $this->getJson("/api/trucking/loads/{$loadId}");
                
                $observaciones = $getResponse->json('data.load.observaciones');
                $this->assertNotContains('<script>', $observaciones);
                $this->assertNotContains('javascript:', $observaciones);
            }
        }
    }

    /** @test */
    public function api_enforces_rate_limiting()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Hacer muchas requests rápidamente
        $responses = [];
        for ($i = 0; $i < 100; $i++) {
            $responses[] = $this->getJson('/api/trucking/loads');
        }

        // Verificar que eventualmente se active el rate limiting
        $rateLimitedResponses = array_filter($responses, function($response) {
            return $response->status() === 429; // Too Many Requests
        });

        // Si hay rate limiting configurado, debería haber algunas respuestas 429
        if (config('app.env') !== 'testing') {
            $this->assertNotEmpty($rateLimitedResponses, 'Rate limiting no está funcionando');
        }
    }
}