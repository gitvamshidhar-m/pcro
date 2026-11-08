<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_personal_workspace_and_owner_membership(): void
    {
        $response = $this->post('/register', [
            'name' => 'Avery Stone',
            'email' => 'avery@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'avery@example.com')->firstOrFail();
        $workspace = Workspace::where('owner_id', $user->id)->firstOrFail();

        $this->assertSame($workspace->id, $user->current_workspace_id);
        $this->assertSame('owner', $workspace->roleFor($user)?->value);
        $this->assertTrue($workspace->hasMember($user));
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_workspace_has_a_public_ulid_route_key(): void
    {
        $workspace = Workspace::factory()->create();

        $this->assertNotNull($workspace->ulid);
        $this->assertSame('ulid', $workspace->getRouteKeyName());
        $this->assertSame($workspace->ulid, $workspace->getRouteKey());
    }
}
