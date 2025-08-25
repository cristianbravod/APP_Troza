<?php


namespace Tests\Feature\Commands;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function database_seeder_creates_default_admin_user()
    {
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->assertDatabaseHas('users', [
            'username' => 'admin',
            'User_Web' => 1,
            'User_App' => 1,
            'active' => 1
        ]);
    }

    /** @test */
    public function database_seeder_creates_default_groups()
    {
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        $expectedGroups = ['admin', 'operators', 'viewers'];
        
        foreach ($expectedGroups as $groupName) {
            $this->assertDatabaseHas('groups', [
                'name' => $groupName,
                'status' => 1
            ]);
        }
    }

    /** @test */
    public function database_seeder_creates_default_modules()
    {
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        $expectedModules = ['admin', 'trucking', 'reports'];
        
        foreach ($expectedModules as $moduleName) {
            $this->assertDatabaseHas('MODULOS', [
                'NAME' => $moduleName,
                'VIGENCIA' => 1
            ]);
        }
    }
}