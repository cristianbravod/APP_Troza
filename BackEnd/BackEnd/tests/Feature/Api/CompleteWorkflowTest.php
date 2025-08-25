<?php

// tests/Feature/Api/CompleteWorkflowTest.php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Group;
use App\Models\Module;
use App\Models\TransportesPack;
use App\Models\ChoferesPack;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompleteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $transporte;
    protected $chofer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Storage::fake('public');
        
        // Configurar datos de prueba
        $this->setupTestData();
    }

    private function setupTestData()
    {
        // Crear grupo y módulo
        $group = Group::factory()->create(['name' => 'operators']);
        $module = Module::factory()->create(['NAME' => 'trucking']);
        $group->modules()->attach($module->id);

        // Crear usuario
        $this->user = User::factory()->create([
            'User_App' => 1,
            'active' => 1,
            'username' => 'testoperator',
            'password' => bcrypt('password123')
        ]);
        $this->user->groups()->attach($group->id);

        // Crear transporte y chofer
        $this->transporte = TransportesPack::factory()->create(['VIGENCIA' => 1]);
        $this->chofer = ChoferesPack::factory()->create([
            'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
            'VIGENCIA' => 1
        ]);
    }

    /** @test */
    public function complete_trucking_workflow_from_login_to_completion()
    {
        // 1. LOGIN
        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'testoperator',
            'password' => 'password123'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');
        
        // Configurar autenticación para las siguientes requests
        $this->withHeaders(['Authorization' => 'Bearer ' . $token]);

        // 2. VERIFICAR MÓDULOS DISPONIBLES
        $modulesResponse = $this->getJson('/api/modules');
        $modulesResponse->assertStatus(200)
                       ->assertJsonPath('data.modules.0.name', 'trucking');

        // 3. OBTENER TRANSPORTES
        $transportesResponse = $this->getJson('/api/transportes');
        $transportesResponse->assertStatus(200);
        $transportes = $transportesResponse->json('data.transportes');
        $this->assertNotEmpty($transportes);

        // 4. OBTENER CHOFERES DEL TRANSPORTE
        $choferesResponse = $this->getJson("/api/transportes/{$this->transporte->ID_TRANSPORTE}/choferes");
        $choferesResponse->assertStatus(200);
        $choferes = $choferesResponse->json('data.choferes');
        $this->assertNotEmpty($choferes);

        // 5. CREAR NUEVA CARGA
        $loadData = [
            'patente' => 'ABC123',
            'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $this->chofer->ID_CHOFER,
            'fecha_carga' => '2024-07-08',
            'ubicacion_gps' => '-38.7369, -72.5986',
            'observaciones' => 'Carga de prueba completa'
        ];

        $createLoadResponse = $this->postJson('/api/trucking/loads', $loadData);
        $createLoadResponse->assertStatus(201);
        $loadId = $createLoadResponse->json('data.load.id');

        // 6. AGREGAR TROZAS AL BANCO 1
        $bank1Data = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 22, 'cantidad' => 15],
                ['diametro' => 24, 'cantidad' => 12],
                ['diametro' => 26, 'cantidad' => 10],
                ['diametro' => 28, 'cantidad' => 8]
            ]
        ];

        $addTrozasResponse = $this->postJson("/api/trucking/loads/{$loadId}/banks", $bank1Data);
        $addTrozasResponse->assertStatus(201);

        // 7. CERRAR BANCO 1 CON FOTO
        $photo1 = UploadedFile::fake()->image('banco1.jpg', 800, 600);
        
        $closeBankResponse = $this->postJson("/api/trucking/loads/{$loadId}/banks/1/close", [
            'foto' => $photo1,
            'ubicacion_gps' => '-38.7370, -72.5987'
        ]);
        
        $closeBankResponse->assertStatus(200);
        $this->assertNotNull($closeBankResponse->json('data.bank.foto_path'));

        // 8. AGREGAR TROZAS AL BANCO 2
        $bank2Data = [
            'numero_banco' => 2,
            'trozas' => [
                ['diametro' => 30, 'cantidad' => 6],
                ['diametro' => 32, 'cantidad' => 5],
                ['diametro' => 34, 'cantidad' => 4]
            ]
        ];

        $this->postJson("/api/trucking/loads/{$loadId}/banks", $bank2Data)
             ->assertStatus(201);

        // 9. CERRAR BANCO 2
        $photo2 = UploadedFile::fake()->image('banco2.jpg', 800, 600);
        
        $this->postJson("/api/trucking/loads/{$loadId}/banks/2/close", [
            'foto' => $photo2,
            'ubicacion_gps' => '-38.7371, -72.5988'
        ])->assertStatus(200);

        // 10. VERIFICAR ESTADO DE LA CARGA
        $loadDetailResponse = $this->getJson("/api/trucking/loads/{$loadId}");
        $loadDetailResponse->assertStatus(200);
        
        $loadDetail = $loadDetailResponse->json('data.load');
        $this->assertCount(2, $loadDetail['bancos']);
        
        foreach ($loadDetail['bancos'] as $banco) {
            $this->assertEquals('CERRADO', $banco['estado']);
            $this->assertNotNull($banco['foto_path']);
        }

        // 11. COMPLETAR LA CARGA
        $completeResponse = $this->postJson("/api/trucking/loads/{$loadId}/complete");
        $completeResponse->assertStatus(200);

        // 12. VERIFICAR QUE LA CARGA ESTÁ COMPLETADA
        $finalLoadResponse = $this->getJson("/api/trucking/loads/{$loadId}");
        $finalLoadResponse->assertStatus(200)
                         ->assertJsonPath('data.load.estado', 'COMPLETADO');

        // 13. VERIFICAR QUE NO SE PUEDE MODIFICAR UNA CARGA COMPLETADA
        $attemptModifyResponse = $this->postJson("/api/trucking/loads/{$loadId}/banks", [
            'numero_banco' => 3,
            'trozas' => [['diametro' => 22, 'cantidad' => 5]]
        ]);
        
        $attemptModifyResponse->assertStatus(400);

        // 14. LISTAR TODAS LAS CARGAS DEL USUARIO
        $listLoadsResponse = $this->getJson('/api/trucking/loads');
        $listLoadsResponse->assertStatus(200);
        
        $loads = $listLoadsResponse->json('data.loads');
        $this->assertCount(1, $loads);
        $this->assertEquals('COMPLETADO', $loads[0]['estado']);

        // 15. LOGOUT
        $logoutResponse = $this->postJson('/api/auth/logout');
        $logoutResponse->assertStatus(200);

        // 16. VERIFICAR QUE NO SE PUEDE ACCEDER SIN TOKEN
        $this->withHeaders(['Authorization' => '']);
        $unauthorizedResponse = $this->getJson('/api/trucking/loads');
        $unauthorizedResponse->assertStatus(401);
    }

    /** @test */
    public function offline_sync_workflow_works_correctly()
    {
        Sanctum::actingAs($this->user);

        // Simular datos creados offline
        $offlineData = [
            'loads' => [
                [
                    'temp_id' => 'offline_load_1',
                    'patente' => 'OFF123',
                    'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
                    'ID_CHOFER' => $this->chofer->ID_CHOFER,
                    'fecha_carga' => '2024-07-08',
                    'ubicacion_gps' => '-38.7369, -72.5986',
                    'observaciones' => 'Creado offline',
                    'banks' => [
                        [
                            'temp_id' => 'offline_bank_1',
                            'numero_banco' => 1,
                            'estado' => 'CERRADO',
                            'ubicacion_gps' => '-38.7370, -72.5987',
                            'fecha_cierre' => '2024-07-08 10:30:00',
                            'trozas' => [
                                ['diametro' => 22, 'cantidad' => 10],
                                ['diametro' => 24, 'cantidad' => 8]
                            ]
                        ],
                        [
                            'temp_id' => 'offline_bank_2',
                            'numero_banco' => 2,
                            'estado' => 'CERRADO',
                            'ubicacion_gps' => '-38.7371, -72.5988',
                            'fecha_cierre' => '2024-07-08 11:15:00',
                            'trozas' => [
                                ['diametro' => 26, 'cantidad' => 6],
                                ['diametro' => 28, 'cantidad' => 4]
                            ]
                        ]
                    ]
                ]
            ],
            'photos' => [
                [
                    'temp_bank_id' => 'offline_bank_1',
                    'photo_base64' => base64_encode('fake_photo_data_1'),
                    'filename' => 'offline_banco_1.jpg'
                ],
                [
                    'temp_bank_id' => 'offline_bank_2',
                    'photo_base64' => base64_encode('fake_photo_data_2'),
                    'filename' => 'offline_banco_2.jpg'
                ]
            ]
        ];

        // 1. VERIFICAR ESTADO DE SYNC ANTES
        $syncStatusBefore = $this->getJson('/api/sync/status');
        $syncStatusBefore->assertStatus(200)
                         ->assertJsonPath('data.pending_loads', 0);

        // 2. SUBIR DATOS OFFLINE
        $syncResponse = $this->postJson('/api/sync/upload', $offlineData);
        $syncResponse->assertStatus(200);

        $syncResult = $syncResponse->json('data');
        $this->assertCount(1, $syncResult['synced_loads']);
        $this->assertEquals('success', $syncResult['synced_loads'][0]['status']);

        $serverId = $syncResult['synced_loads'][0]['server_id'];

        // 3. VERIFICAR QUE LA CARGA SE CREÓ CORRECTAMENTE
        $loadResponse = $this->getJson("/api/trucking/loads/{$serverId}");
        $loadResponse->assertStatus(200);

        $load = $loadResponse->json('data.load');
        $this->assertEquals('OFF123', $load['patente']);
        $this->assertCount(2, $load['bancos']);

        foreach ($load['bancos'] as $banco) {
            $this->assertEquals('CERRADO', $banco['estado']);
            $this->assertNotNull($banco['foto_path']);
            $this->assertNotEmpty($banco['trozas']);
        }

        // 4. VERIFICAR ESTADO DE SYNC DESPUÉS
        $syncStatusAfter = $this->getJson('/api/sync/status');
        $syncStatusAfter->assertStatus(200);
        
        $statusData = $syncStatusAfter->json('data');
        $this->assertEquals(1, $statusData['total_loads']);
        $this->assertEquals(100, $statusData['sync_percentage']);
    }

    /** @test */
    public function admin_workflow_manages_users_and_permissions()
    {
        // Crear usuario admin
        $adminGroup = Group::factory()->create(['name' => 'admin']);
        $adminModule = Module::factory()->create(['NAME' => 'admin']);
        $truckingModule = Module::factory()->create(['NAME' => 'trucking']);
        
        $adminGroup->modules()->attach([$adminModule->id, $truckingModule->id]);
        
        $admin = User::factory()->create([
            'User_Web' => 1,
            'User_App' => 1,
            'username' => 'admin',
            'password' => bcrypt('admin123')
        ]);
        $admin->groups()->attach($adminGroup->id);

        // 1. LOGIN COMO ADMIN
        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');
        $this->withHeaders(['Authorization' => 'Bearer ' . $token]);

        // 2. LISTAR USUARIOS
        $usersResponse = $this->getJson('/api/admin/users');
        $usersResponse->assertStatus(200);

        // 3. CREAR NUEVO USUARIO
        $newUserData = [
            'username' => 'newoperator',
            'email' => 'newoperator@test.com',
            'password' => 'password123',
            'first_name' => 'New',
            'last_name' => 'Operator',
            'User_App' => 1,
            'groups' => [$adminGroup->id] // Asignar al grupo de operadores
        ];

        $createUserResponse = $this->postJson('/api/admin/users', $newUserData);
        $createUserResponse->assertStatus(201);
        $newUserId = $createUserResponse->json('data.user.id');

        // 4. VERIFICAR QUE EL USUARIO PUEDE HACER LOGIN
        $newUserLoginResponse = $this->postJson('/api/auth/login', [
            'username' => 'newoperator',
            'password' => 'password123'
        ]);

        $newUserLoginResponse->assertStatus(200);
        $newUserToken = $newUserLoginResponse->json('data.token');

        // 5. VERIFICAR PERMISOS DEL NUEVO USUARIO
        $this->withHeaders(['Authorization' => 'Bearer ' . $newUserToken]);
        
        $modulesResponse = $this->getJson('/api/modules');
        $modulesResponse->assertStatus(200);
        
        $modules = $modulesResponse->json('data.modules');
        $moduleNames = array_column($modules, 'name');
        $this->assertContains('admin', $moduleNames);
        $this->assertContains('trucking', $moduleNames);

        // 6. VOLVER COMO ADMIN Y ACTUALIZAR USUARIO
        $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
        
        $updateUserResponse = $this->putJson("/api/admin/users/{$newUserId}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'User_App' => 0 // Quitar acceso a la app
        ]);

        $updateUserResponse->assertStatus(200);

        // 7. VERIFICAR QUE EL USUARIO ACTUALIZADO NO PUEDE HACER LOGIN
        $deniedLoginResponse = $this->postJson('/api/auth/login', [
            'username' => 'newoperator',
            'password' => 'password123'
        ]);

        $deniedLoginResponse->assertStatus(403)
                           ->assertJsonPath('message', 'Sin permisos para acceder a la aplicación móvil');

        // 8. ELIMINAR USUARIO
        $deleteUserResponse = $this->deleteJson("/api/admin/users/{$newUserId}");
        $deleteUserResponse->assertStatus(200);

        // 9. VERIFICAR QUE EL USUARIO ELIMINADO NO EXISTE
        $this->assertDatabaseMissing('users', ['id' => $newUserId]);
    }

    /** @test */
    public function error_handling_and_validation_workflow()
    {
        Sanctum::actingAs($this->user);

        // 1. INTENTAR CREAR CARGA CON DATOS INVÁLIDOS
        $invalidLoadData = [
            'patente' => 'INVALID_PATENTE_TOO_LONG',
            'ID_TRANSPORTE' => 999999, // ID que no existe
            'ID_CHOFER' => 999999,     // ID que no existe
            'fecha_carga' => 'invalid-date',
            'ubicacion_gps' => 'invalid-gps'
        ];

        $invalidLoadResponse = $this->postJson('/api/trucking/loads', $invalidLoadData);
        $invalidLoadResponse->assertStatus(422)
                           ->assertJsonValidationErrors([
                               'patente',
                               'ID_TRANSPORTE',
                               'ID_CHOFER',
                               'fecha_carga',
                               'ubicacion_gps'
                           ]);

        // 2. CREAR CARGA VÁLIDA
        $validLoad = $this->createLoadWithBanks($this->user, 0); // Sin bancos

        // 3. INTENTAR AGREGAR TROZAS CON DIÁMETROS INVÁLIDOS
        $invalidTrozasData = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 21, 'cantidad' => 10], // Diámetro inválido
                ['diametro' => 25, 'cantidad' => -5], // Cantidad negativa
                ['diametro' => 61, 'cantidad' => 0]   // Diámetro fuera de rango
            ]
        ];

        $invalidTrozasResponse = $this->postJson("/api/trucking/loads/{$validLoad->id}/banks", $invalidTrozasData);
        $invalidTrozasResponse->assertStatus(422)
                             ->assertJsonValidationErrors([
                                 'trozas.0.diametro',
                                 'trozas.1.cantidad',
                                 'trozas.2.diametro'
                             ]);

        // 4. INTENTAR CERRAR BANCO SIN TROZAS
        $closeBankWithoutTrozasResponse = $this->postJson("/api/trucking/loads/{$validLoad->id}/banks/1/close", [
            'foto' => UploadedFile::fake()->image('test.jpg'),
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $closeBankWithoutTrozasResponse->assertStatus(400)
                                      ->assertJsonPath('message', 'No se puede cerrar un banco sin trozas registradas');

        // 5. AGREGAR TROZAS VÁLIDAS
        $validTrozasData = [
            'numero_banco' => 1,
            'trozas' => [
                ['diametro' => 22, 'cantidad' => 10],
                ['diametro' => 24, 'cantidad' => 8]
            ]
        ];

        $this->postJson("/api/trucking/loads/{$validLoad->id}/banks", $validTrozasData)
             ->assertStatus(201);

        // 6. CERRAR BANCO EXITOSAMENTE
        $photo = UploadedFile::fake()->image('banco.jpg');
        $closeBankResponse = $this->postJson("/api/trucking/loads/{$validLoad->id}/banks/1/close", [
            'foto' => $photo,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $closeBankResponse->assertStatus(200);

        // 7. INTENTAR MODIFICAR BANCO CERRADO
        $modifyClosedBankResponse = $this->postJson("/api/trucking/loads/{$validLoad->id}/banks", [
            'numero_banco' => 1,
            'trozas' => [['diametro' => 26, 'cantidad' => 5]]
        ]);

        $modifyClosedBankResponse->assertStatus(400)
                                 ->assertJsonPath('message', 'No se puede modificar un banco cerrado');

        // 8. INTENTAR ACCEDER A CARGA DE OTRO USUARIO
        $otherUser = User::factory()->create(['User_App' => 1]);
        $otherUserLoad = $this->createLoadWithBanks($otherUser, 1);

        $unauthorizedAccessResponse = $this->getJson("/api/trucking/loads/{$otherUserLoad->id}");
        $unauthorizedAccessResponse->assertStatus(403);

        // 9. INTENTAR COMPLETAR CARGA SIN BANCOS CERRADOS
        $loadWithoutClosedBanks = $this->createLoadWithBanks($this->user, 2, false); // Bancos abiertos

        $completeWithoutClosedBanksResponse = $this->postJson("/api/trucking/loads/{$loadWithoutClosedBanks->id}/complete");
        $completeWithoutClosedBanksResponse->assertStatus(400)
                                          ->assertJsonPath('message', 'Debe cerrar al menos un banco antes de completar la carga');
    }

    /** @test */
    public function file_upload_and_storage_workflow()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        // 1. CREAR CARGA Y AGREGAR TROZAS
        $load = $this->createLoadWithBanks($this->user, 0);
        
        $this->postJson("/api/trucking/loads/{$load->id}/banks", [
            'numero_banco' => 1,
            'trozas' => [['diametro' => 22, 'cantidad' => 10]]
        ])->assertStatus(201);

        // 2. PROBAR DIFERENTES TIPOS DE ARCHIVO
        $validImage = UploadedFile::fake()->image('valid.jpg', 800, 600);
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 100);
        $oversizedImage = UploadedFile::fake()->image('huge.jpg', 5000, 5000)->size(15000); // 15MB

        // 3. INTENTAR SUBIR ARCHIVO INVÁLIDO
        $invalidFileResponse = $this->postJson("/api/trucking/loads/{$load->id}/banks/1/close", [
            'foto' => $invalidFile,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $invalidFileResponse->assertStatus(422)
                           ->assertJsonValidationErrors(['foto']);

        // 4. INTENTAR SUBIR ARCHIVO MUY GRANDE
        $oversizedResponse = $this->postJson("/api/trucking/loads/{$load->id}/banks/1/close", [
            'foto' => $oversizedImage,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $oversizedResponse->assertStatus(422)
                         ->assertJsonValidationErrors(['foto']);

        // 5. SUBIR IMAGEN VÁLIDA
        $validUploadResponse = $this->postJson("/api/trucking/loads/{$load->id}/banks/1/close", [
            'foto' => $validImage,
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $validUploadResponse->assertStatus(200);
        $photoPath = $validUploadResponse->json('data.bank.foto_path');

        // 6. VERIFICAR QUE EL ARCHIVO SE GUARDÓ
        Storage::disk('public')->assertExists($photoPath);

        // 7. VERIFICAR METADATOS DEL ARCHIVO
        $this->assertNotNull($photoPath);
        $this->assertStringContains('banks/', $photoPath);
        $this->assertStringEndsWith('.jpg', $photoPath);

        // 8. OBTENER INFORMACIÓN DE LA CARGA Y VERIFICAR LA RUTA DE LA FOTO
        $loadDetailResponse = $this->getJson("/api/trucking/loads/{$load->id}");
        $loadDetailResponse->assertStatus(200);
        
        $bankData = $loadDetailResponse->json('data.load.bancos.0');
        $this->assertEquals($photoPath, $bankData['foto_path']);
    }

    /** @test */
    public function performance_and_bulk_operations_workflow()
    {
        Sanctum::actingAs($this->user);

        $startTime = microtime(true);

        // 1. CREAR MÚLTIPLES CARGAS SIMULTÁNEAMENTE
        $loads = [];
        for ($i = 1; $i <= 5; $i++) {
            $loadData = [
                'patente' => "PERF{$i}23",
                'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
                'ID_CHOFER' => $this->chofer->ID_CHOFER,
                'fecha_carga' => '2024-07-08',
                'ubicacion_gps' => '-38.7369, -72.5986',
                'observaciones' => "Carga de performance test {$i}"
            ];

            $response = $this->postJson('/api/trucking/loads', $loadData);
            $response->assertStatus(201);
            $loads[] = $response->json('data.load.id');
        }

        // 2. AGREGAR MÚLTIPLES TROZAS A CADA CARGA
        foreach ($loads as $loadId) {
            for ($banco = 1; $banco <= 4; $banco++) {
                $trozasData = [
                    'numero_banco' => $banco,
                    'trozas' => []
                ];

                // Agregar trozas de diferentes diámetros
                for ($diametro = 22; $diametro <= 60; $diametro += 2) {
                    $trozasData['trozas'][] = [
                        'diametro' => $diametro,
                        'cantidad' => rand(1, 20)
                    ];
                }

                $response = $this->postJson("/api/trucking/loads/{$loadId}/banks", $trozasData);
                $response->assertStatus(201);

                // Cerrar banco con foto
                $photo = UploadedFile::fake()->image("banco_{$loadId}_{$banco}.jpg");
                $closeResponse = $this->postJson("/api/trucking/loads/{$loadId}/banks/{$banco}/close", [
                    'foto' => $photo,
                    'ubicacion_gps' => '-38.7369, -72.5986'
                ]);
                $closeResponse->assertStatus(200);
            }

            // Completar la carga
            $completeResponse = $this->postJson("/api/trucking/loads/{$loadId}/complete");
            $completeResponse->assertStatus(200);
        }

        // 3. LISTAR TODAS LAS CARGAS Y VERIFICAR PERFORMANCE
        $listResponse = $this->getJson('/api/trucking/loads');
        $listResponse->assertStatus(200);
        
        $allLoads = $listResponse->json('data.loads');
        $this->assertCount(5, $allLoads);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verificar que el tiempo de ejecución sea razonable (menos de 30 segundos)
        $this->assertLessThan(30, $executionTime, "Workflow de performance tomó demasiado tiempo: {$executionTime} segundos");

        // 4. VERIFICAR INTEGRIDAD DE DATOS
        foreach ($allLoads as $load) {
            $this->assertEquals('COMPLETADO', $load['estado']);
            $this->assertEquals(4, $load['bancos_count']);
        }

        // 5. VERIFICAR QUE TODAS LAS FOTOS SE GUARDARON
        $totalPhotos = 0;
        foreach ($loads as $loadId) {
            $detailResponse = $this->getJson("/api/trucking/loads/{$loadId}");
            $detailResponse->assertStatus(200);
            
            $bancos = $detailResponse->json('data.load.bancos');
            foreach ($bancos as $banco) {
                $this->assertNotNull($banco['foto_path']);
                Storage::disk('public')->assertExists($banco['foto_path']);
                $totalPhotos++;
            }
        }

        $this->assertEquals(20, $totalPhotos); // 5 cargas × 4 bancos = 20 fotos
    }

    /** @test */
    public function concurrent_access_and_data_consistency_workflow()
    {
        // Crear dos usuarios diferentes
        $user1 = $this->user;
        $user2 = User::factory()->create(['User_App' => 1]);

        // 1. AMBOS USUARIOS HACEN LOGIN
        $token1 = $this->postJson('/api/auth/login', [
            'username' => $user1->username,
            'password' => 'password'
        ])->json('data.token');

        $token2 = $this->postJson('/api/auth/login', [
            'username' => $user2->username,
            'password' => 'password'
        ])->json('data.token');

        // 2. USUARIO 1 CREA UNA CARGA
        $this->withHeaders(['Authorization' => "Bearer {$token1}"]);
        
        $loadResponse = $this->postJson('/api/trucking/loads', [
            'patente' => 'CONC123',
            'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $this->chofer->ID_CHOFER,
            'fecha_carga' => '2024-07-08',
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        $loadId = $loadResponse->json('data.load.id');

        // 3. USUARIO 2 INTENTA ACCEDER A LA CARGA DEL USUARIO 1
        $this->withHeaders(['Authorization' => "Bearer {$token2}"]);
        
        $unauthorizedResponse = $this->getJson("/api/trucking/loads/{$loadId}");
        $unauthorizedResponse->assertStatus(403);

        // 4. USUARIO 2 CREA SU PROPIA CARGA CON LA MISMA PATENTE
        $duplicateLoadResponse = $this->postJson('/api/trucking/loads', [
            'patente' => 'CONC123', // Misma patente
            'ID_TRANSPORTE' => $this->transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $this->chofer->ID_CHOFER,
            'fecha_carga' => '2024-07-08',
            'ubicacion_gps' => '-38.7369, -72.5986'
        ]);

        // Debería permitir la misma patente para diferentes usuarios
        $duplicateLoadResponse->assertStatus(201);
        $user2LoadId = $duplicateLoadResponse->json('data.load.id');

        // 5. VERIFICAR QUE CADA USUARIO SOLO VE SUS PROPIAS CARGAS
        $this->withHeaders(['Authorization' => "Bearer {$token1}"]);
        $user1LoadsResponse = $this->getJson('/api/trucking/loads');
        $user1Loads = $user1LoadsResponse->json('data.loads');
        $this->assertCount(1, $user1Loads);
        $this->assertEquals($loadId, $user1Loads[0]['id']);

        $this->withHeaders(['Authorization' => "Bearer {$token2}"]);
        $user2LoadsResponse = $this->getJson('/api/trucking/loads');
        $user2Loads = $user2LoadsResponse->json('data.loads');
        $this->assertCount(1, $user2Loads);
        $this->assertEquals($user2LoadId, $user2Loads[0]['id']);

        // 6. VERIFICAR INTEGRIDAD DE DATOS EN BASE DE DATOS
        $this->assertDatabaseHas('ABAS_Troza_HEAD', [
            'id' => $loadId,
            'user_id' => $user1->id,
            'patente' => 'CONC123'
        ]);

        $this->assertDatabaseHas('ABAS_Troza_HEAD', [
            'id' => $user2LoadId,
            'user_id' => $user2->id,
            'patente' => 'CONC123'
        ]);
    }
}