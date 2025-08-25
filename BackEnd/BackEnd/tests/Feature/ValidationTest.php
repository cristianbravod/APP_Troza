<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function patente_validation_works_correctly()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Test patentes válidas
        $validPatentes = [
            'ABC123',
            'AB1234',
            'ABCD12',
            'AA1111',
            'ZZ9999'
        ];

        foreach ($validPatentes as $patente) {
            $response = $this->postJson('/api/trucking/loads', [
                'patente' => $patente,
                'ID_TRANSPORTE' => 1,
                'ID_CHOFER' => 1,
                'fecha_carga' => '2024-07-08'
            ]);

            $response->assertStatus(201, "Patente válida falló: {$patente}");
        }

        // Test patentes inválidas
        $invalidPatentes = [
            'AB12',      // Muy corta
            'ABCDEFG',   // Muy larga
            '123456',    // Solo números
            'ABCDEF',    // Solo letras
            'abc123',    // Minúsculas
            'AB-123',    // Con guión
            'AB 123',    // Con espacio
        ];

        foreach ($invalidPatentes as $patente) {
            $response = $this->postJson('/api/trucking/loads', [
                'patente' => $patente,
                'ID_TRANSPORTE' => 1,
                'ID_CHOFER' => 1,
                'fecha_carga' => '2024-07-08'
            ]);

            $response->assertStatus(422, "Patente inválida pasó: {$patente}")
                    ->assertJsonValidationErrors(['patente']);
        }
    }

    /** @test */
    public function diameter_validation_allows_only_valid_values()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        // Test diámetros válidos (22 a 60, solo pares)
        $validDiameters = [22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60];
        
        foreach ($validDiameters as $diameter) {
            $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", [
                'numero_banco' => 1,
                'trozas' => [
                    ['diametro' => $diameter, 'cantidad' => 10]
                ]
            ]);

            $response->assertStatus(201, "Diámetro válido falló: {$diameter}");
            
            // Limpiar para el siguiente test
            AbasTrozaDetail::where('head_id', $load->id)->delete();
        }

        // Test diámetros inválidos
        $invalidDiameters = [20, 21, 23, 25, 61, 62, 100, 0, -1];
        
        foreach ($invalidDiameters as $diameter) {
            $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", [
                'numero_banco' => 1,
                'trozas' => [
                    ['diametro' => $diameter, 'cantidad' => 10]
                ]
            ]);

            $response->assertStatus(422, "Diámetro inválido pasó: {$diameter}")
                    ->assertJsonValidationErrors(['trozas.0.diametro']);
        }
    }

    /** @test */
    public function quantity_validation_works()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        // Test cantidades inválidas
        $invalidQuantities = [0, -1, -10, 1001]; // Assuming max is 1000
        
        foreach ($invalidQuantities as $quantity) {
            $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", [
                'numero_banco' => 1,
                'trozas' => [
                    ['diametro' => 22, 'cantidad' => $quantity]
                ]
            ]);

            $response->assertStatus(422, "Cantidad inválida pasó: {$quantity}")
                    ->assertJsonValidationErrors(['trozas.0.cantidad']);
        }
    }

    /** @test */
    public function gps_coordinates_validation_works()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Test coordenadas válidas
        $validCoordinates = [
            '-38.7369, -72.5986',
            '-33.4489, -70.6693',
            '40.7128, -74.0060',
            '51.5074, -0.1278'
        ];

        foreach ($validCoordinates as $coords) {
            $response = $this->postJson('/api/trucking/loads', [
                'patente' => 'ABC123',
                'ID_TRANSPORTE' => 1,
                'ID_CHOFER' => 1,
                'fecha_carga' => '2024-07-08',
                'ubicacion_gps' => $coords
            ]);

            $response->assertStatus(201, "Coordenadas válidas fallaron: {$coords}");
            
            // Limpiar para el siguiente test
            AbasTrozaHead::where('user_id', $user->id)->delete();
        }

        // Test coordenadas inválidas
        $invalidCoordinates = [
            '91, -180',      // Latitud > 90
            '-91, 180',      // Latitud < -90
            '45, 181',       // Longitud > 180
            '45, -181',      // Longitud < -180
            'invalid',       // Formato inválido
            '45.123',        // Solo una coordenada
            '45, 90, 10',    // Tres coordenadas
        ];

        foreach ($invalidCoordinates as $coords) {
            $response = $this->postJson('/api/trucking/loads', [
                'patente' => 'ABC123',
                'ID_TRANSPORTE' => 1,
                'ID_CHOFER' => 1,
                'fecha_carga' => '2024-07-08',
                'ubicacion_gps' => $coords
            ]);

            $response->assertStatus(422, "Coordenadas inválidas pasaron: {$coords}")
                    ->assertJsonValidationErrors(['ubicacion_gps']);
        }
    }
}
