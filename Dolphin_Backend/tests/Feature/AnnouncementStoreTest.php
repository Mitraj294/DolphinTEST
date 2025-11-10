<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnnouncementStoreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function superadmin_can_create_and_dispatch_announcement()
    {
    /** @var \App\Models\User $superadmin */
    $superadmin = User::factory()->create();
        $role = Role::create(['name' => 'superadmin']);
        $superadmin->roles()->attach($role->id);

        $org = Organization::factory()->create(['user_id' => $superadmin->id]);
        $member = User::factory()->create();
        $org->members()->attach($member->id);

        $payload = [
            'message' => 'Test Announcement',
            'organization_ids' => [$org->id],
        ];

    // Ensure actingAs receives an Authenticatable instance
    $response = $this->actingAs($superadmin, 'api')
        ->postJson('/api/announcements', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Announcement created and dispatched (if recipients provided)']);
    }
}
