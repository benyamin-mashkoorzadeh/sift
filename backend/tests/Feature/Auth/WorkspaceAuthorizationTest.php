<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\ReviewItem;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function workspaceRouteProvider(): array
    {
        return [
            'overview' => ['GET', 'workspaces.overview.show', []],
            'settings view' => ['GET', 'workspaces.settings.show', []],
            'settings update' => ['PATCH', 'workspaces.settings.update', ['name' => 'Renamed Workspace']],
            'widget provision' => ['POST', 'workspaces.settings.widget.store', []],
            'widget update' => ['PATCH', 'workspaces.settings.widget.update', ['enabled' => true]],
            'widget rotation' => ['POST', 'workspaces.settings.widget.rotate-key', []],
            'knowledge view' => ['GET', 'workspaces.documents.index', []],
            'knowledge manage' => ['POST', 'workspaces.documents.store', []],
            'assistant' => ['POST', 'workspaces.answers.store', []],
            'conversations' => ['GET', 'workspaces.assistant-interactions.index', []],
            'review view' => ['GET', 'workspaces.review-items.index', []],
            'review resolve' => ['PATCH', 'workspaces.review-items.resolve', ['resolution' => 'Resolved by a human.']],
        ];
    }

    #[DataProvider('workspaceRouteProvider')]
    public function test_every_workspace_route_requires_authentication(
        string $method,
        string $routeName,
        array $payload,
    ): void {
        [$workspace, $reviewItem] = $this->createWorkspaceWithReviewItem('unauthenticated');

        $this->request($method, $routeName, $workspace, $reviewItem, $payload)
            ->assertUnauthorized();
    }

    #[DataProvider('workspaceRouteProvider')]
    public function test_cross_workspace_access_is_concealed_as_not_found(
        string $method,
        string $routeName,
        array $payload,
    ): void {
        $ownedWorkspace = $this->createWorkspace('owned');
        $this->actingAsWorkspaceMember($ownedWorkspace, WorkspaceRole::Owner);
        [$otherWorkspace, $reviewItem] = $this->createWorkspaceWithReviewItem('private');
        $originalName = $otherWorkspace->name;

        $this->request($method, $routeName, $otherWorkspace, $reviewItem, $payload)
            ->assertNotFound();

        $this->assertSame($originalName, $otherWorkspace->fresh()->name);
        $this->assertSame('pending', $reviewItem->fresh()->status->value);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('assistant_interactions', 0);
    }

    public function test_owner_has_every_workspace_permission(): void
    {
        $this->assertSame(
            WorkspacePermission::cases(),
            WorkspaceRole::Owner->permissions(),
        );

        [$workspace, $reviewItem] = $this->createWorkspaceWithReviewItem('owner');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);

        $this->assertAllowedRouteResponses($workspace, $reviewItem, includeSettingsUpdate: true);
    }

    public function test_admin_has_every_permission_except_settings_update(): void
    {
        $this->assertSame([
            WorkspacePermission::ViewOverview,
            WorkspacePermission::UseAssistant,
            WorkspacePermission::ViewKnowledge,
            WorkspacePermission::ManageKnowledge,
            WorkspacePermission::ViewConversations,
            WorkspacePermission::ViewReview,
            WorkspacePermission::ResolveReview,
            WorkspacePermission::ViewSettings,
        ], WorkspaceRole::Admin->permissions());

        [$workspace, $reviewItem] = $this->createWorkspaceWithReviewItem('admin');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Admin);

        $this->assertAllowedRouteResponses($workspace, $reviewItem, includeSettingsUpdate: false);
        $this->patchJson(route('workspaces.settings.update', $workspace), [
            'name' => 'Forbidden Rename',
        ])->assertForbidden();
        $this->assertSame('Admin Workspace', $workspace->fresh()->name);
    }

    public function test_team_permissions_are_owner_only(): void
    {
        $this->assertTrue(WorkspaceRole::Owner->allows(WorkspacePermission::ViewTeam));
        $this->assertTrue(WorkspaceRole::Owner->allows(WorkspacePermission::ManageTeam));
        $this->assertFalse(WorkspaceRole::Admin->allows(WorkspacePermission::ViewTeam));
        $this->assertFalse(WorkspaceRole::Admin->allows(WorkspacePermission::ManageTeam));
        $this->assertFalse(WorkspaceRole::Member->allows(WorkspacePermission::ViewTeam));
        $this->assertFalse(WorkspaceRole::Member->allows(WorkspacePermission::ManageTeam));
    }

    public function test_member_can_use_overview_assistant_and_review_only(): void
    {
        $this->assertSame([
            WorkspacePermission::ViewOverview,
            WorkspacePermission::UseAssistant,
            WorkspacePermission::ViewReview,
            WorkspacePermission::ResolveReview,
        ], WorkspaceRole::Member->permissions());

        [$workspace, $reviewItem] = $this->createWorkspaceWithReviewItem('member');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Member);

        $this->getJson(route('workspaces.overview.show', $workspace))->assertOk();
        $this->postJson(route('workspaces.answers.store', $workspace), [])->assertUnprocessable();

        $this->getJson(route('workspaces.documents.index', $workspace))->assertForbidden();
        $this->postJson(route('workspaces.documents.store', $workspace), [])->assertForbidden();
        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.review-items.index', $workspace))->assertOk();
        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => 'Resolved by a support member.',
        ])->assertOk();
        $this->getJson(route('workspaces.settings.show', $workspace))->assertForbidden();
        $this->patchJson(route('workspaces.settings.update', $workspace), [
            'name' => 'Forbidden Rename',
        ])->assertForbidden();
        $this->getJson(route('workspaces.team.members.index', $workspace))->assertForbidden();

        $this->assertSame('Member Workspace', $workspace->fresh()->name);
        $this->assertSame('resolved', $reviewItem->fresh()->status->value);
    }

    public function test_browser_style_workspace_request_returns_json_unauthorized_without_redirecting(): void
    {
        $workspace = $this->createWorkspace('browser-request');

        $this->withHeader('Accept', 'text/html')
            ->get(route('workspaces.overview.show', $workspace))
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    private function assertAllowedRouteResponses(
        Workspace $workspace,
        ReviewItem $reviewItem,
        bool $includeSettingsUpdate,
    ): void {
        $this->getJson(route('workspaces.overview.show', $workspace))->assertOk();
        $this->postJson(route('workspaces.answers.store', $workspace), [])->assertUnprocessable();
        $this->getJson(route('workspaces.documents.index', $workspace))->assertOk();
        $this->postJson(route('workspaces.documents.store', $workspace), [])->assertUnprocessable();
        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.review-items.index', $workspace))->assertOk();
        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => 'Resolved by an authorized user.',
        ])->assertOk();
        $this->getJson(route('workspaces.settings.show', $workspace))->assertOk();

        if ($includeSettingsUpdate) {
            $this->patchJson(route('workspaces.settings.update', $workspace), [
                'name' => 'Authorized Rename',
            ])->assertOk();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(
        string $method,
        string $routeName,
        Workspace $workspace,
        ReviewItem $reviewItem,
        array $payload,
    ): TestResponse {
        $parameters = $routeName === 'workspaces.review-items.resolve'
            ? [$workspace, $reviewItem]
            : $workspace;

        return $this->json($method, route($routeName, $parameters), $payload);
    }

    /**
     * @return array{Workspace, ReviewItem}
     */
    private function createWorkspaceWithReviewItem(string $suffix): array
    {
        $workspace = $this->createWorkspace($suffix);
        $reviewItem = $workspace->reviewItems()->create([
            'question' => 'What is the policy?',
            'deduplication_key' => hash('sha256', "question-{$suffix}"),
            'status' => 'pending',
            'last_asked_at' => now(),
        ]);

        return [$workspace, $reviewItem];
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => str($suffix)->headline().' Workspace',
            'slug' => "authorization-{$suffix}",
        ]);
    }
}
