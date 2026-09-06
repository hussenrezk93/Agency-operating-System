<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantChatRequest;
use App\Services\GroqService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private readonly GroqService $groq) {}

    public function chat(AssistantChatRequest $request): JsonResponse
    {
        try {
            $reply = $this->groq->reply(
                $request->string('message')->toString(),
                $request->input('history', []),
                $request->user(),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => __('agencyos.assistant.error')], 502);
        }

        return response()->json(['reply' => $reply['text'], 'suggestions' => $reply['suggestions']]);
    }
}
