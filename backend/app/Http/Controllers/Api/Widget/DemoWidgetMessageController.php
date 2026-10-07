<?php

namespace App\Http\Controllers\Api\Widget;

use App\Actions\Widget\AnswerDemoWidgetQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\StoreDemoWidgetMessageRequest;
use App\Http\Resources\Widget\WidgetAnswerResource;

class DemoWidgetMessageController extends Controller
{
    public function __invoke(
        StoreDemoWidgetMessageRequest $request,
        AnswerDemoWidgetQuestion $answerDemoWidgetQuestion,
    ): WidgetAnswerResource {
        return new WidgetAnswerResource($answerDemoWidgetQuestion->handle(
            $request->validated('question'),
        ));
    }
}
