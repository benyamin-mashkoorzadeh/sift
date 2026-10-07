<?php

namespace Tests\Feature\Api;

use App\Enums\AssistantInteractionOrigin;
use App\Enums\RagAnswerStatus;
use App\Enums\WorkspaceRole;
use App\Models\AssistantInteraction;
use App\Models\User;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use App\Services\AI\RagService;
use App\Services\Widget\WidgetConversationTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class WorkspaceWidgetSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_provision_once_and_widget_starts_disabled(): void
    {
        config()->set('widget.frontend_url', 'https://app.example.test/');
        $workspace = $this->createWorkspace('provision');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);

        $firstKey = $this->postJson(route('workspaces.settings.widget.store', $workspace))
            ->assertCreated()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.script_url', 'https://app.example.test/widget.js')
            ->json('data.public_key');

        $this->postJson(route('workspaces.settings.widget.store', $workspace))
            ->assertOk()
            ->assertJsonPath('data.public_key', $firstKey)
            ->assertJsonPath('data.enabled', false);

        $this->assertDatabaseCount('workspace_widgets', 1);
        $this->assertSame($workspace->id, WorkspaceWidget::query()->sole()->workspace_id);
    }

    public function test_settings_show_returns_null_or_safe_widget_configuration_for_owner_and_admin(): void
    {
        config()->set('widget.frontend_url', 'https://support.example.test/base/');
        $workspace = $this->createWorkspace('reading');
        $owner = $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);

        $this->getJson(route('workspaces.settings.show', $workspace))
            ->assertOk()
            ->assertJsonPath('data.widget', null);

        $widget = $workspace->widget()->create([
            'public_key' => 'sift_w_'.str_repeat('a', 43),
            'enabled' => true,
            'key_rotated_at' => now(),
        ]);

        $this->getJson(route('workspaces.settings.show', $workspace))
            ->assertOk()
            ->assertJsonPath('data.widget.enabled', true)
            ->assertJsonPath('data.widget.public_key', $widget->public_key)
            ->assertJsonPath('data.widget.script_url', 'https://support.example.test/base/widget.js')
            ->assertJsonPath('data.widget.key_rotated_at', $widget->key_rotated_at->toISOString())
            ->assertJsonMissingPath('data.widget.id')
            ->assertJsonMissingPath('data.widget.workspace_id');

        $this->actingAs($owner = $this->replaceMembershipUser($workspace, $owner, WorkspaceRole::Admin));

        $this->getJson(route('workspaces.settings.show', $workspace))
            ->assertOk()
            ->assertJsonPath('data.widget.public_key', $widget->public_key);
    }

    public function test_only_owner_can_mutate_widget_settings_and_member_cannot_read_settings(): void
    {
        $workspace = $this->createWorkspace('roles');
        $admin = $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Admin);

        $this->postJson(route('workspaces.settings.widget.store', $workspace))->assertForbidden();

        $this->actingAs($this->replaceMembershipUser($workspace, $admin, WorkspaceRole::Owner));
        $this->postJson(route('workspaces.settings.widget.store', $workspace))->assertCreated();

        $admin = $this->replaceCurrentUser($workspace, WorkspaceRole::Admin);
        $this->patchJson(route('workspaces.settings.widget.update', $workspace), ['enabled' => true])
            ->assertForbidden();
        $this->postJson(route('workspaces.settings.widget.rotate-key', $workspace))->assertForbidden();

        $this->actingAs($this->replaceMembershipUser($workspace, $admin, WorkspaceRole::Member));
        $this->getJson(route('workspaces.settings.show', $workspace))->assertForbidden();
        $this->postJson(route('workspaces.settings.widget.store', $workspace))->assertForbidden();
        $this->patchJson(route('workspaces.settings.widget.update', $workspace), ['enabled' => true])
            ->assertForbidden();
        $this->postJson(route('workspaces.settings.widget.rotate-key', $workspace))->assertForbidden();
    }

    public function test_cross_workspace_widget_mutations_are_concealed_as_not_found(): void
    {
        $ownedWorkspace = $this->createWorkspace('owned');
        $privateWorkspace = $this->createWorkspace('private');
        $this->actingAsWorkspaceMember($ownedWorkspace, WorkspaceRole::Owner);

        $this->postJson(route('workspaces.settings.widget.store', $privateWorkspace))->assertNotFound();
        $this->patchJson(route('workspaces.settings.widget.update', $privateWorkspace), ['enabled' => true])
            ->assertNotFound();
        $this->postJson(route('workspaces.settings.widget.rotate-key', $privateWorkspace))->assertNotFound();

        $this->assertDatabaseCount('workspace_widgets', 0);
    }

    public function test_owner_can_enable_and_disable_widget_and_public_availability_follows_state(): void
    {
        $workspace = $this->createWorkspace('toggle');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        $publicKey = $this->postJson(route('workspaces.settings.widget.store', $workspace))
            ->json('data.public_key');

        $this->getJson(route('widget.bootstrap', $publicKey))->assertNotFound();

        $this->patchJson(route('workspaces.settings.widget.update', $workspace), ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);
        $this->getJson(route('widget.bootstrap', $publicKey))
            ->assertOk()
            ->assertJsonPath('data.workspace_name', $workspace->name);

        $this->patchJson(route('workspaces.settings.widget.update', $workspace), ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->getJson(route('widget.bootstrap', $publicKey))->assertNotFound();
    }

    public function test_widget_update_validates_only_enabled(): void
    {
        $workspace = $this->createWorkspace('validation');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        $this->postJson(route('workspaces.settings.widget.store', $workspace));

        foreach ([
            [],
            ['enabled' => 'not-a-boolean'],
            ['enabled' => true, 'public_key' => 'attacker-controlled'],
            ['enabled' => true, 'workspace_id' => 999],
        ] as $payload) {
            $this->patchJson(route('workspaces.settings.widget.update', $workspace), $payload)
                ->assertUnprocessable();
        }

        $this->assertFalse($workspace->widget->fresh()->enabled);
    }

    public function test_rotation_changes_key_invalidates_old_access_and_preserves_history(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $workspace = $this->createWorkspace('rotation');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        $oldKey = $this->postJson(route('workspaces.settings.widget.store', $workspace))
            ->json('data.public_key');
        $this->patchJson(route('workspaces.settings.widget.update', $workspace), ['enabled' => true]);
        [$rawToken, $conversation] = $this->createConversation($workspace);
        $interaction = AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
            'origin' => AssistantInteractionOrigin::Widget,
            'widget_conversation_id' => $conversation->id,
            'question' => 'Historical widget question',
            'status' => RagAnswerStatus::Answered,
            'answer' => 'Historical grounded answer.',
            'provider' => 'test',
            'model' => 'test-model',
        ]);
        Carbon::setTestNow('2026-10-04 11:00:00');

        $response = $this->postJson(route('workspaces.settings.widget.rotate-key', $workspace))
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.key_rotated_at', now()->toISOString());
        $newKey = $response->json('data.public_key');

        $this->assertNotSame($oldKey, $newKey);
        $this->getJson(route('widget.bootstrap', $oldKey))->assertNotFound();
        $this->getJson(route('widget.bootstrap', $newKey))->assertOk();

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $this->withHeader('X-Sift-Conversation-Token', $rawToken)
            ->postJson(route('widget.messages.store', $newKey), ['question' => 'Can you help?'])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Conversation unavailable.',
                'code' => 'conversation_unavailable',
            ]);

        $this->assertDatabaseHas('widget_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseHas('assistant_interactions', ['id' => $interaction->id]);
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => str($suffix)->headline().' Widget Workspace',
            'slug' => 'widget-settings-'.$suffix.'-'.Str::lower((string) Str::ulid()),
        ]);
    }

    /**
     * @return array{string, WidgetConversation}
     */
    private function createConversation(Workspace $workspace): array
    {
        $token = $this->app->make(WidgetConversationTokenGenerator::class)->generate();
        $conversation = WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => $token->hash,
            'expires_at' => now()->addDay(),
            'last_activity_at' => now(),
        ]);

        return [$token->rawToken, $conversation];
    }

    private function replaceCurrentUser(Workspace $workspace, WorkspaceRole $role): User
    {
        $current = $this->app['auth']->user();

        return $this->replaceMembershipUser($workspace, $current, $role);
    }

    private function replaceMembershipUser(Workspace $workspace, User $user, WorkspaceRole $role): User
    {
        $workspace->users()->detach($user->id);

        return $this->actingAsWorkspaceMember($workspace, $role);
    }
}
