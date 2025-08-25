<?php

// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar el entorno de testing
        $this->withoutExceptionHandling();
        
        // Configurar storage fake por defecto para tests con archivos
        if (method_exists($this, 'setUpStorage')) {
            $this->setUpStorage();
        }
    }

    /**
     * Crear un usuario autenticado para tests
     */
    protected function authenticatedUser($attributes = [])
    {
        $user = \App\Models\User::factory()->create(array_merge([
            'User_App' => 1,
            'active' => 1,
            'User_Activo' => 1
        ], $attributes));

        $this->actingAs($user, 'sanctum');
        
        return $user;
    }

    /**
     * Crear un usuario admin autenticado
     */
    protected function authenticatedAdmin()
    {
        $user = $this->authenticatedUser();
        $adminGroup = \App\Models\Group::factory()->create(['name' => 'admin']);
        $user->groups()->attach($adminGroup->id);
        
        return $user;
    }

    /**
     * Crear datos de prueba para el módulo de camiones
     */
    protected function createTruckingTestData()
    {
        $transporte = \App\Models\TransportesPack::factory()->create(['VIGENCIA' => 1]);
        $chofer = \App\Models\ChoferesPack::factory()->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'VIGENCIA' => 1
        ]);

        return [
            'transporte' => $transporte,
            'chofer' => $chofer
        ];
    }

    /**
     * Crear una carga de camión con bancos de prueba
     */
    protected function createLoadWithBanks($user, $banksCount = 2, $closed = false)
    {
        $testData = $this->createTruckingTestData();
        
        $load = \App\Models\AbasTrozaHead::factory()->create([
            'user_id' => $user->id,
            'ID_TRANSPORTE' => $testData['transporte']->ID_TRANSPORTE,
            'ID_CHOFER' => $testData['chofer']->ID_CHOFER,
            'estado' => 'ABIERTO'
        ]);

        for ($i = 1; $i <= $banksCount; $i++) {
            \App\Models\AbasTrozaDetail::factory()->count(3)->create([
                'head_id' => $load->id,
                'numero_banco' => $i,
                'estado' => $closed ? 'CERRADO' : 'ABIERTO'
            ]);
        }

        return $load;
    }

    /**
     * Assert que una respuesta JSON contiene la estructura de éxito esperada
     */
    protected function assertSuccessResponse($response, $dataStructure = null)
    {
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        if ($dataStructure) {
            $response->assertJsonStructure([
                'success',
                'data' => $dataStructure
            ]);
        }
    }

    /**
     * Assert que una respuesta JSON contiene la estructura de error esperada
     */
    protected function assertErrorResponse($response, $statusCode = 400, $message = null)
    {
        $response->assertStatus($statusCode)
                ->assertJson(['success' => false]);

        if ($message) {
            $response->assertJson(['message' => $message]);
        }
    }
}