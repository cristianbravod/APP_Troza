<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\AbasTrozaHead;
use App\Models\AbasTrozaDetail;
use App\Models\TransportesPack;
use App\Models\ChoferesPack;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TruckingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Storage::fake('public');
    }

    /** @test */
    public function user_can_create_truck_load_header()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $transporte = TransportesPack::factory()->create();
        $chofer = ChoferesPack::factory()->create(['ID_TRANSPORTE' => $transporte->ID_TRANSPORTE]);
        
        Sanctum::actingAs($user);

        $headerData = [
            'patente' => 'ABC123',
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $chofer->ID_CHOFER,
            'fecha_carga' => '2024-07-08',
            'ubicacion_gps' => '-38.7369, -72.5986',
            'observaciones' => 'Carga normal'
        ];

        $response = $this->postJson('/api/trucking/loads', $headerData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'load' => [
                            'id',
                            'patente',
                            'ID_TRANSPORTE',
                            'ID_CHOFER',
                            'fecha_carga',
                            'ubicacion_gps',
                            'estado',
                            'user_id'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('ABAS_Troza_HEAD', [
            'patente' => 'ABC123',
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $chofer->ID_CHOFER,
            'estado' => 'ABIERTO'
        ]);
    }

    /** @test */
    public function user_can_list_their_truck_loads()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $otherUser = User::factory()->create(['User_App' => 1]);
        
        // Cargas del usuario autenticado
        AbasTrozaHead::factory()->count(3)->create(['user_id' => $user->id]);
        
        // Cargas de otro usuario (no deberían aparecer)
        AbasTrozaHead::factory()->count(2)->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/trucking/loads');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'loads' => [
                            '*' => [
                                'id',
                                'patente',
                                'fecha_carga',
                                'estado',
                                'transporte',
                                'chofer',
                                'bancos_count'
                            ]
                        ]
                    ]
                ]);

        $this->assertCount(3, $response->json('data.loads'));
    }

    /** @test */
    public function user_can_get_specific_truck_load()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/trucking/loads/{$load->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'load' => [
                            'id',
                            'patente',
                            'fecha_carga',
                            'estado',
                            'transporte',
                            'chofer',
                            'bancos' => [
                                '*' => [
                                    'id',
                                    'numero_banco',
                                    'estado',
                                    'foto_path',
                                    'ubicacion_gps',
                                    'trozas'
                                ]
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function user_cannot_access_other_user_truck_loads()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $otherUser = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/trucking/loads/{$load->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_add_trozas_to_bank()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        $bankData = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 22, 'cantidad' => 10],
                ['diametro' => 24, 'cantidad' => 8],
                ['diametro' => 26, 'cantidad' => 6]
            ]
        ];

        $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", $bankData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'bank' => [
                            'id',
                            'numero_banco',
                            'estado',
                            'trozas'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('ABAS_Troza_DETAIL', [
            'head_id' => $load->id,
            'numero_banco' => 1,
            'diametro' => 22,
            'cantidad' => 10
        ]);
    }

    /** @test */
    public function user_can_close_bank_with_photo()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        // Crear algunos detalles de trozas para el banco
        AbasTrozaDetail::factory()->count(3)->create([
            'head_id' => $load->id,
            'numero_banco' => 1
        ]);
        
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('banco1.jpg');

        $response = $this->postJson("/api/trucking/loads/{$load->id}/banks/1/close", [
            'foto' => $photo,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'bank' => [
                            'numero_banco',
                            'estado',
                            'foto_path',
                            'ubicacion_gps',
                            'fecha_cierre'
                        ]
                    ]
                ]);

        $bankData = $response->json('data.bank');
        $this->assertEquals('CERRADO', $bankData['estado']);
        $this->assertNotNull($bankData['foto_path']);
        $this->assertNotNull($bankData['fecha_cierre']);

        // Verificar que la foto se guardó
        Storage::disk('public')->assertExists($bankData['foto_path']);
    }

    /** @test */
    public function user_cannot_close_bank_without_trozas()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('banco1.jpg');

        $response = $this->postJson("/api/trucking/loads/{$load->id}/banks/1/close", [
            'foto' => $photo,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede cerrar un banco sin trozas registradas'
                ]);
    }

    /** @test */
    public function user_cannot_modify_closed_bank()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        // Crear detalles de trozas con banco cerrado
        AbasTrozaDetail::factory()->create([
            'head_id' => $load->id,
            'numero_banco' => 1,
            'estado' => 'CERRADO'
        ]);
        
        Sanctum::actingAs($user);

        $bankData = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 22, 'cantidad' => 5]
            ]
        ];

        $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", $bankData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede modificar un banco cerrado'
                ]);
    }

    /** @test */
    public function user_can_complete_truck_load()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        // Crear 4 bancos cerrados
        for ($i = 1; $i <= 4; $i++) {
            AbasTrozaDetail::factory()->create([
                'head_id' => $load->id,
                'numero_banco' => $i,
                'estado' => 'CERRADO'
            ]);
        }
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/trucking/loads/{$load->id}/complete");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Carga completada exitosamente'
                ]);

        $this->assertDatabaseHas('ABAS_Troza_HEAD', [
            'id' => $load->id,
            'estado' => 'COMPLETADO'
        ]);
    }

    /** @test */
    public function user_cannot_complete_truck_load_without_closed_banks()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        // Solo crear 2 bancos cerrados (necesita al menos 1)
        AbasTrozaDetail::factory()->create([
            'head_id' => $load->id,
            'numero_banco' => 1,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/trucking/loads/{$load->id}/complete");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Debe cerrar al menos un banco antes de completar la carga'
                ]);
    }

    /** @test */
    public function truck_load_validation_works()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Test patente requerida
        $response = $this->postJson('/api/trucking/loads', [
            'ID_TRANSPORTE' => 1,
            'ID_CHOFER' => 1,
            'fecha_carga' => '2024-07-08'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['patente']);

        // Test formato de patente
        $response = $this->postJson('/api/trucking/loads', [
            'patente' => 'INVALID_PATENTE_FORMAT',
            'ID_TRANSPORTE' => 1,
            'ID_CHOFER' => 1,
            'fecha_carga' => '2024-07-08'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['patente']);
    }

    /** @test */
    public function diameter_validation_works()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $load = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ABIERTO'
        ]);
        
        Sanctum::actingAs($user);

        // Test diámetro inválido
        $bankData = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 21, 'cantidad' => 10], // Menor a 22
                ['diametro' => 25, 'cantidad' => 8],  // Número impar
                ['diametro' => 62, 'cantidad' => 6]   // Mayor a 60
            ]
        ];

        $response = $this->postJson("/api/trucking/loads/{$load->id}/banks", $bankData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['trozas.0.diametro', 'trozas.1.diametro', 'trozas.2.diametro']);
    }
}

