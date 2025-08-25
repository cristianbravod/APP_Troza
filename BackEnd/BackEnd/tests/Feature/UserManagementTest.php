<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Group;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function admin_can_list_users()
    {
        $admin = User::factory()->create();
        $adminGroup = Group::factory()->create(['name' => 'admin']);
        $admin->groups()->attach($adminGroup->id);
        
        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'users' => [
                            '*' => [
                                'id',
                                'username',
                                'email',
                                'first_name',
                                'last_name',
                                'active',
                                'groups'
                            ]
                        ],
                        'total',
                        'page',
                        'per_page'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_create_user()
    {
        $admin = User::factory()->create();
        $adminGroup = Group::factory()->create(['name' => 'admin']);
        $admin->groups()->attach($adminGroup->id);
        
        Sanctum::actingAs($admin);

        $userData = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '123456789',
            'User_App' => 1,
            'groups' => [1, 2]
        ];

        $response = $this->postJson('/api/admin/users', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'username',
                            'email',
                            'first_name',
                            'last_name'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com'
        ]);
    }

    /** @test */
    public function admin_can_update_user()
    {
        $admin = User::factory()->create();
        $adminGroup = Group::factory()->create(['name' => 'admin']);
        $admin->groups()->attach($adminGroup->id);
        
        $user = User::factory()->create([
            'username' => 'oldusername',
            'email' => 'old@example.com'
        ]);

        Sanctum::actingAs($admin);

        $updateData = [
            'username' => 'newusername',
            'email' => 'new@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name'
        ];

        $response = $this->putJson("/api/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'newusername',
            'email' => 'new@example.com'
        ]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $admin = User::factory()->create();
        $adminGroup = Group::factory()->create(['name' => 'admin']);
        $admin->groups()->attach($adminGroup->id);
        
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id
        ]);
    }

    /** @test */
    public function non_admin_cannot_access_user_management()
    {
        $user = User::factory()->create();
        $userGroup = Group::factory()->create(['name' => 'user']);
        $user->groups()->attach($userGroup->id);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}