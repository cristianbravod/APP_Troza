<?php

// tests/Feature/ModulesAndPermissionsTest.php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Group;
use App\Models\Module;
use Laravel\Sanctum\Sanctum;

class ModulesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function user_can_access_module_based_on_group_permissions()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $group = Group::factory()->create(['name' => 'operators']);
        $module = Module::factory()->create(['name' => 'trucking']);
        
        $user->groups()->attach($group->id);
        $group->modules()->attach($module->id);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/modules/check/trucking');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'has_access' => true
                ]);
    }

    /** @test */
    public function user_cannot_access_module_without_permissions()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $group = Group::factory()->create(['name' => 'operators']);
        $module = Module::factory()->create(['name' => 'admin']);
        
        $user->groups()->attach($group->id);
        // No attachamos el módulo al grupo
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/modules/check/admin');

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'has_access' => false
                ]);
    }

    /** @test */
    public function user_gets_correct_modules_list()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $group = Group::factory()->create(['name' => 'operators']);
        
        $module1 = Module::factory()->create(['name' => 'trucking', 'priority' => 1]);
        $module2 = Module::factory()->create(['name' => 'reports', 'priority' => 2]);
        $module3 = Module::factory()->create(['name' => 'admin', 'priority' => 3]);
        
        $user->groups()->attach($group->id);
        $group->modules()->attach([$module1->id, $module2->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/modules');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'transportes' => [
                            '*' => [
                                'ID_TRANSPORTE',
                                'NOMBRE_TRANSPORTES',
                                'RUT',
                                'VIGENCIA'
                            ]
                        ]
                    ]
                ]);

        $transportes = $response->json('data.transportes');
        $this->assertCount(3, $transportes);
        
        // Verificar que todos los transportes son activos
        foreach ($transportes as $transporte) {
            $this->assertEquals(1, $transporte['VIGENCIA']);
        }
    }

    /** @test */
    public function user_can_get_drivers_by_transport()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $transporte = TransportesPack::factory()->create(['VIGENCIA' => 1]);
        
        // Crear choferes para este transporte
        ChoferesPack::factory()->count(3)->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'VIGENCIA' => 1
        ]);
        
        // Crear choferes de otro transporte
        $otherTransporte = TransportesPack::factory()->create(['VIGENCIA' => 1]);
        ChoferesPack::factory()->count(2)->create([
            'ID_TRANSPORTE' => $otherTransporte->ID_TRANSPORTE,
            'VIGENCIA' => 1
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/transportes/{$transporte->ID_TRANSPORTE}/choferes");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'choferes' => [
                            '*' => [
                                'ID_CHOFER',
                                'RUT_CHOFER',
                                'NOMBRE_CHOFER',
                                'TELEFONO',
                                'ID_TRANSPORTE',
                                'VIGENCIA'
                            ]
                        ]
                    ]
                ]);

        $choferes = $response->json('data.choferes');
        $this->assertCount(3, $choferes);
        
        // Verificar que todos los choferes pertenecen al transporte correcto
        foreach ($choferes as $chofer) {
            $this->assertEquals($transporte->ID_TRANSPORTE, $chofer['ID_TRANSPORTE']);
            $this->assertEquals(1, $chofer['VIGENCIA']);
        }
    }

    /** @test */
    public function user_can_get_all_active_drivers()
    {
        $user = User::factory()->create(['User_App' => 1]);
        $transporte = TransportesPack::factory()->create(['VIGENCIA' => 1]);
        
        // Crear choferes activos e inactivos
        ChoferesPack::factory()->count(4)->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'VIGENCIA' => 1
        ]);
        ChoferesPack::factory()->count(2)->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'VIGENCIA' => 0
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/choferes');

        $response->assertStatus(200);
        
        $choferes = $response->json('data.choferes');
        $this->assertCount(4, $choferes);
        
        // Verificar que todos los choferes son activos
        foreach ($choferes as $chofer) {
            $this->assertEquals(1, $chofer['VIGENCIA']);
        }
    }
}