<?php

namespace App\Http\Controllers\Api\Widget;

use App\Actions\Widget\AnswerWidgetQuestion;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicWidget;
use App\Http\Middleware\ResolveWidgetConversation;
use App\Http\Requests\Widget\StoreWidgetMessageRequest;
use App\Http\Resources\Widget\WidgetAnswerResource;
use App\Models\WidgetConversation;
use App\Models\WorkspaceWidget;

class WidgetMessageController extends Controller
{
    public function __invoke(
        StoreWidgetMessageRequest $request,
        AnswerWidgetQuestion $answerWidgetQuestion,
    ): WidgetAnswerResource {
        /** @var WorkspaceWidget $widget */
        $widget = $request->attributes->get(ResolvePublicWidget::ATTRIBUTE);
        /** @var WidgetConversation $conversation */
        $conversation = $request->attributes->get(ResolveWidgetConversation::ATTRIBUTE);

        return new WidgetAnswerResource($answerWidgetQuestion->handle(
            $widget->workspace,
            $conversation,
            $request->validated('question'),
        ));
    }
}
