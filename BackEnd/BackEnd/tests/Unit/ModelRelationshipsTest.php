<?php
namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Group;
use App\Models\Module;
use App\Models\AbasTrozaHead;
use App\Models\AbasTrozaDetail;
use App\Models\TransportesPack;
use App\Models\ChoferesPack;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function user_has_many_groups()
    {
        $user = User::factory()->create();
        $groups = Group::factory()->count(3)->create();
        
        $user->groups()->attach($groups->pluck('id'));
        
        $this->assertCount(3, $user->groups);
        $this->assertInstanceOf(Group::class, $user->groups->first());
    }

    /** @test */
    public function group_has_many_modules()
    {
        $group = Group::factory()->create();
        $modules = Module::factory()->count(3)->create();
        
        $group->modules()->attach($modules->pluck('id'));
        
        $this->assertCount(3, $group->modules);
        $this->assertInstanceOf(Module::class, $group->modules->first());
    }

    /** @test */
    public function abas_troza_head_has_many_details()
    {
        $head = AbasTrozaHead::factory()->create();
        $details = AbasTrozaDetail::factory()->count(5)->create([
            'head_id' => $head->id
        ]);
        
        $this->assertCount(5, $head->details);
        $this->assertInstanceOf(AbasTrozaDetail::class, $head->details->first());
    }

    /** @test */
    public function transporte_has_many_choferes()
    {
        $transporte = TransportesPack::factory()->create();
        $choferes = ChoferesPack::factory()->count(3)->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE
        ]);
        
        $this->assertCount(3, $transporte->choferes);
        $this->assertInstanceOf(ChoferesPack::class, $transporte->choferes->first());
    }

    /** @test */
    public function chofer_belongs_to_transporte()
    {
        $transporte = TransportesPack::factory()->create();
        $chofer = ChoferesPack::factory()->create([
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE
        ]);
        
        $this->assertInstanceOf(TransportesPack::class, $chofer->transporte);
        $this->assertEquals($transporte->ID_TRANSPORTE, $chofer->transporte->ID_TRANSPORTE);
    }
}
                    'success',
                    'data' => [
                        'modules' => [
                            '*' => [
                                'id',
                                'name',
                                'description',
                                'priority',
                                'url',
                                'icon'
                            ]
                        ]
                    ]
                ]);

        $modules = $response->json('data.modules');
        $this->assertCount(2, $modules);
        $this->assertEquals('trucking', $modules[0]['name']);
        $this->assertEquals('reports', $modules[1]['name']);
    }
}