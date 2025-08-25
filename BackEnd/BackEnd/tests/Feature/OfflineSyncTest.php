<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\AbasTrozaHead;
use App\Models\AbasTrozaDetail;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Storage::fake('public');
    }

    /** @test */
    public function user_can_sync_offline_data()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        $syncData = [
            'loads' => [
                [
                    'temp_id' => 'temp_load_1',
                    'patente' => 'ABC123',
                    'ID_TRANSPORTE' => 1,
                    'ID_CHOFER' => 1,
                    'fecha_carga' => '2024-07-08',
                    'ubicacion_gps' => '-38.7369, -72.5986',
                    'banks' => [
                        [
                            'temp_id' => 'temp_bank_1',
                            'numero_banco' => 1,
                            'estado' => 'CERRADO',
                            'ubicacion_gps' => '-38.7369, -72.5986',
                            'fecha_cierre' => '2024-07-08 10:30:00',
                            'trozas' => [
                                ['diametro' => 22, 'cantidad' => 10],
                                ['diametro' => 24, 'cantidad' => 8]
                            ]
                        ]
                    ]
                ]
            ],
            'photos' => [
                [
                    'temp_bank_id' => 'temp_bank_1',
                    'photo_base64' => base64_encode('fake_photo_data'),
                    'filename' => 'banco_1.jpg'
                ]
            ]
        ];

        $response = $this->postJson('/api/sync/upload', $syncData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'synced_loads' => [
                            '*' => [
                                'temp_id',
                                'server_id',
                                'status'
                            ]
                        ],
                        'errors'
                    ]
                ]);

        // Verificar que los datos se guardaron en la base de datos
        $this->assertDatabaseHas('ABAS_Troza_HEAD', [
            'patente' => 'ABC123',
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas('ABAS_Troza_DETAIL', [
            'numero_banco' => 1,
            'diametro' => 22,
            'cantidad' => 10
        ]);
    }

    /** @test */
    public function sync_handles_duplicate_data_gracefully()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Crear datos existentes
        $existingLoad = AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'patente' => 'ABC123',
            'fecha_carga' => '2024-07-08'
        ]);

        $syncData = [
            'loads' => [
                [
                    'temp_id' => 'temp_load_1',
                    'patente' => 'ABC123', // Misma patente
                    'ID_TRANSPORTE' => $existingLoad->ID_TRANSPORTE,
                    'ID_CHOFER' => $existingLoad->ID_CHOFER,
                    'fecha_carga' => '2024-07-08', // Misma fecha
                    'ubicacion_gps' => '-38.7369, -72.5986',
                    'banks' => []
                ]
            ],
            'photos' => []
        ];

        $response = $this->postJson('/api/sync/upload', $syncData);

        $response->assertStatus(200);
        
        $syncResult = $response->json('data.synced_loads.0');
        $this->assertEquals('duplicate', $syncResult['status']);
        $this->assertEquals($existingLoad->id, $syncResult['server_id']);
    }

    /** @test */
    public function user_can_check_sync_status()
    {
        $user = User::factory()->create(['User_App' => 1]);
        Sanctum::actingAs($user);

        // Crear datos de prueba
        AbasTrozaHead::factory()->count(3)->create([
            'user_id' => $user->id,
            'synced' => true,
            'updated_at' => now()->subHours(2)
        ]);

        AbasTrozaHead::factory()->count(2)->create([
            'user_id' => $user->id,
            'synced' => false,
            'updated_at' => now()->subMinutes(30)
        ]);

        $response = $this->getJson('/api/sync/status');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'last_sync',
                        'pending_loads',
                        'total_loads',
                        'sync_percentage'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['pending_loads']);
        $this->assertEquals(5, $data['total_loads']);
        $this->assertEquals(60, $data['sync_percentage']);
    }
}