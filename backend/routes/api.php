<?php

use App\Http\Controllers\Api\AcceptWorkspaceInvitationController;
use App\Http\Controllers\Api\AssistantInteractionIndexController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\DemoLoginController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\DocumentIndexController;
use App\Http\Controllers\Api\DocumentUploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RegenerateWorkspaceInvitationLinkController;
use App\Http\Controllers\Api\ResolveReviewItemController;
use App\Http\Controllers\Api\ReviewItemIndexController;
use App\Http\Controllers\Api\RotateWorkspaceWidgetKeyController;
use App\Http\Controllers\Api\Widget\DemoWidgetMessageController;
use App\Http\Controllers\Api\Widget\WidgetBootstrapController;
use App\Http\Controllers\Api\Widget\WidgetConversationController;
use App\Http\Controllers\Api\Widget\WidgetMessageController;
use App\Http\Controllers\Api\WorkspaceAnswerController;
use App\Http\Controllers\Api\WorkspaceInvitationController;
use App\Http\Controllers\Api\WorkspaceInvitationIndexController;
use App\Http\Controllers\Api\WorkspaceInvitationPreviewController;
use App\Http\Controllers\Api\WorkspaceMemberController;
use App\Http\Controllers\Api\WorkspaceMemberIndexController;
use App\Http\Controllers\Api\WorkspaceOverviewController;
use App\Http\Controllers\Api\WorkspaceSettingsController;
use App\Http\Controllers\Api\WorkspaceWidgetSettingsController;
use App\Http\Middleware\AddInvitationSecurityHeaders;
use App\Http\Middleware\AddWidgetSecurityHeaders;
use App\Http\Middleware\EnforceWidgetRequestSize;
use App\Http\Middleware\ResolvePublicWidget;
use App\Http\Middleware\ResolveWidgetConversation;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', HealthController::class);

Route::middleware(AddInvitationSecurityHeaders::class)
    ->prefix('invitations')
    ->group(function (): void {
        Route::get('/{token}', WorkspaceInvitationPreviewController::class)
            ->middleware('throttle:30,1')
            ->name('invitations.show');
        Route::post('/{token}/accept', AcceptWorkspaceInvitationController::class)
            ->middleware('throttle:10,1')
            ->name('invitations.accept');
    });

Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->middleware([
        AddWidgetSecurityHeaders::class,
        EnforceWidgetRequestSize::class.':demo.widget.maximum_request_bytes',
    ])
    ->prefix('demo/widget')
    ->group(function (): void {
        Route::post('/messages', DemoWidgetMessageController::class)
            ->middleware('throttle:demo-widget')
            ->name('demo.widget.messages.store');
    });

Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->middleware([
        AddWidgetSecurityHeaders::class,
        EnforceWidgetRequestSize::class,
        ResolvePublicWidget::class,
    ])
    ->prefix('widget/v1/{widgetKey}')
    ->group(function (): void {
        Route::get('/', WidgetBootstrapController::class)
            ->middleware('throttle:widget-bootstrap')
            ->name('widget.bootstrap');
        Route::post('/conversations', WidgetConversationController::class)
            ->middleware('throttle:widget-conversations')
            ->name('widget.conversations.store');
        Route::post('/messages', WidgetMessageController::class)
            ->middleware([
                'throttle:widget-messages',
                ResolveWidgetConversation::class,
            ])
            ->name('widget.messages.store');
    });

Route::prefix('auth')->group(function (): void {
    Route::post('/demo', DemoLoginController::class)
        ->middleware('throttle:demo-entry')
        ->name('auth.demo');
    Route::post('/register', RegisterController::class)
        ->middleware('throttle:auth-registration')
        ->name('auth.register');
    Route::post('/login', LoginController::class)
        ->middleware('throttle:auth-login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', CurrentUserController::class)->name('auth.user');
        Route::post('/logout', LogoutController::class)->name('auth.logout');
    });
});

Route::middleware(['auth:sanctum', 'workspace.member'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/workspaces/{workspace}/overview', WorkspaceOverviewController::class)
            ->can('viewOverview', 'workspace')
            ->name('workspaces.overview.show');
        Route::get('/workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'show'])
            ->can('viewSettings', 'workspace')
            ->name('workspaces.settings.show');
        Route::patch('/workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'update'])
            ->can('updateSettings', 'workspace')
            ->name('workspaces.settings.update');
        Route::post('/workspaces/{workspace}/settings/widget', [WorkspaceWidgetSettingsController::class, 'store'])
            ->can('updateSettings', 'workspace')
            ->name('workspaces.settings.widget.store');
        Route::patch('/workspaces/{workspace}/settings/widget', [WorkspaceWidgetSettingsController::class, 'update'])
            ->can('updateSettings', 'workspace')
            ->name('workspaces.settings.widget.update');
        Route::post('/workspaces/{workspace}/settings/widget/rotate-key', RotateWorkspaceWidgetKeyController::class)
            ->can('updateSettings', 'workspace')
            ->name('workspaces.settings.widget.rotate-key');
        Route::get('/workspaces/{workspace}/documents', DocumentIndexController::class)
            ->can('viewKnowledge', 'workspace')
            ->name('workspaces.documents.index');
        Route::post('/workspaces/{workspace}/documents', DocumentUploadController::class)
            ->can('manageKnowledge', 'workspace')
            ->name('workspaces.documents.store');
        Route::post('/workspaces/{workspace}/answers', WorkspaceAnswerController::class)
            ->can('useAssistant', 'workspace')
            ->middleware('throttle:workspace-assistant')
            ->name('workspaces.answers.store');
        Route::get('/workspaces/{workspace}/assistant-interactions', AssistantInteractionIndexController::class)
            ->can('viewConversations', 'workspace')
            ->name('workspaces.assistant-interactions.index');
        Route::get('/workspaces/{workspace}/review-items', ReviewItemIndexController::class)
            ->can('viewReview', 'workspace')
            ->name('workspaces.review-items.index');
        Route::patch('/workspaces/{workspace}/review-items/{reviewItem}/resolve', ResolveReviewItemController::class)
            ->can('resolveReview', 'workspace')
            ->name('workspaces.review-items.resolve');
        Route::get('/workspaces/{workspace}/team/members', WorkspaceMemberIndexController::class)
            ->can('viewTeam', 'workspace')
            ->name('workspaces.team.members.index');
        Route::patch('/workspaces/{workspace}/team/members/{user}', [WorkspaceMemberController::class, 'update'])
            ->can('manageTeam', 'workspace')
            ->name('workspaces.team.members.update');
        Route::delete('/workspaces/{workspace}/team/members/{user}', [WorkspaceMemberController::class, 'destroy'])
            ->can('manageTeam', 'workspace')
            ->name('workspaces.team.members.destroy');
        Route::get('/workspaces/{workspace}/team/invitations', WorkspaceInvitationIndexController::class)
            ->can('viewTeam', 'workspace')
            ->name('workspaces.team.invitations.index');
        Route::post('/workspaces/{workspace}/team/invitations', [WorkspaceInvitationController::class, 'store'])
            ->can('manageTeam', 'workspace')
            ->name('workspaces.team.invitations.store');
        Route::delete('/workspaces/{workspace}/team/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
            ->can('manageTeam', 'workspace')
            ->name('workspaces.team.invitations.destroy');
        Route::post('/workspaces/{workspace}/team/invitations/{invitation}/regenerate-link', RegenerateWorkspaceInvitationLinkController::class)
            ->can('manageTeam', 'workspace')
            ->name('workspaces.team.invitations.regenerate-link');
    });
