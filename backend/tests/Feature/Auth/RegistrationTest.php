<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_atomically_creates_a_user_workspace_and_owner_membership(): void
    {
        $response = $this->statefulPostJson(route('auth.register'), [
            'name' => '  Jane Smith  ',
            'email' => '  JANE@EXAMPLE.COM  ',
            'company_name' => '  Acme Support  ',
            'password' => 'Sift123a',
            'password_confirmation' => 'Sift123a',
            'role' => WorkspaceRole::Admin->value,
            'workspace_id' => 999,
        ]);

        $user = User::query()->sole();
        $workspace = $user->workspaces()->sole();

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
                    ],
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => 'Acme Support',
                        'role' => WorkspaceRole::Owner->value,
                    ],
                    'access_mode' => 'normal',
                    'permissions' => [
                        'overview.view',
                        'assistant.use',
                        'knowledge.view',
                        'knowledge.manage',
                        'conversations.view',
                        'review.view',
                        'review.resolve',
                        'settings.view',
                        'settings.update',
                        'team.view',
                        'team.manage',
                    ],
                ],
            ]);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('Sift123a', $user->password));
        $this->assertNotSame('Sift123a', $user->password);
        $this->assertSame(WorkspaceRole::Owner, $workspace->pivot->role);
        $this->assertStringStartsWith('acme-support-', $workspace->slug);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_user', 1);
    }

    public function test_invalid_registration_creates_no_partial_company_data(): void
    {
        $this->statefulPostJson(route('auth.register'), [
            'name' => 'Jane Smith',
            'email' => 'not-an-email',
            'company_name' => 'Acme Support',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_user', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_complex_password_shorter_than_eight_characters(): void
    {
        $this->statefulPostJson(route('auth.register'), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'company_name' => 'Acme Support',
            'password' => 'Sift12a',
            'password_confirmation' => 'Sift12a',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_user', 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPostJson(string $uri, array $data)
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }
}
