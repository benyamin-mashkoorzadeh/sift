<?php

namespace Tests\Feature\Api;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_safe_workspace_settings_and_real_upload_limits(): void
    {
        Carbon::setTestNow('2026-10-03 10:30:00');
        $workspace = $this->createWorkspace('support', 'Support Workspace');
        Carbon::setTestNow();
        Config::set('documents.max_upload_kb', 12_345);

        $this->getJson(route('workspaces.settings.show', $workspace))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => 'Support Workspace',
                        'created_at' => $workspace->created_at->toISOString(),
                    ],
                    'knowledge' => [
                        'supported_document_types' => [
                            [
                                'extension' => 'pdf',
                                'label' => 'PDF',
                            ],
                        ],
                        'maximum_upload_size_bytes' => 12_641_280,
                    ],
                    'widget' => null,
                ],
            ])
            ->assertJsonMissingPath('data.workspace.slug')
            ->assertJsonMissingPath('data.ai')
            ->assertJsonMissingPath('data.knowledge.storage_disk')
            ->assertJsonMissingPath('data.knowledge.chunk_size');
    }

    public function test_it_trims_and_updates_only_the_route_bound_workspace_name(): void
    {
        $workspace = $this->createWorkspace('primary', 'Original Workspace');
        $otherWorkspace = $this->createWorkspace('other', 'Other Workspace');

        $this->patchJson(route('workspaces.settings.update', $workspace), [
            'name' => '  Renamed Workspace  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.name', 'Renamed Workspace');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Renamed Workspace',
            'slug' => 'settings-primary',
        ]);
        $this->assertDatabaseHas('workspaces', [
            'id' => $otherWorkspace->id,
            'name' => 'Other Workspace',
            'slug' => 'settings-other',
        ]);
    }

    public function test_it_rejects_invalid_names_without_changing_the_workspace(): void
    {
        $workspace = $this->createWorkspace('validation', 'Original Workspace');

        foreach ([
            [],
            ['name' => '   '],
            ['name' => ['not', 'a', 'string']],
            ['name' => str_repeat('a', 256)],
        ] as $payload) {
            $this->patchJson(route('workspaces.settings.update', $workspace), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name');
        }

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Original Workspace',
        ]);
    }

    public function test_it_rejects_every_setting_other_than_name(): void
    {
        $workspace = $this->createWorkspace('strict', 'Original Workspace');

        $this->patchJson(route('workspaces.settings.update', $workspace), [
            'name' => 'Attempted Rename',
            'workspace_id' => 999,
            'slug' => 'changed-slug',
            'role' => 'owner',
            'embedding_model' => 'different-model',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'workspace_id',
                'slug',
                'role',
                'embedding_model',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Original Workspace',
            'slug' => 'settings-strict',
        ]);
    }

    public function test_unknown_workspaces_return_not_found(): void
    {
        $this->createWorkspace('known', 'Known Workspace');

        $this->getJson('/api/workspaces/999999/settings')->assertNotFound();
        $this->patchJson('/api/workspaces/999999/settings', ['name' => 'Missing'])
            ->assertNotFound();
    }

    private function createWorkspace(string $slug, string $name): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => $name,
            'slug' => "settings-{$slug}",
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }
}
