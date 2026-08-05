<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartDirectChatRequest;
use App\Http\Requests\StartDirectMessageRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Support\ChatPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thin controller — membership resolution and every write goes through ChatService. */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ChatConversation::class);
        $actor = $request->user();

        return view('chat.index', $this->sidebarPayload($actor) + [
            'conversation' => null,
            'messages' => collect(),
        ]);
    }

    public function show(Request $request, ChatConversation $conversation): View
    {
        $this->authorize('view', $conversation);
        $actor = $request->user();

        return view('chat.index', $this->sidebarPayload($actor) + [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->with('sender:id,full_name')->orderBy('created_at')->get(),
        ]);
    }

    /** The sidebar (conversation list + directory) is identical on both index() and show(). */
    private function sidebarPayload(User $actor): array
    {
        $canStartDirect = $actor->can('startDirect', ChatConversation::class);

        $conversations = EloquentCollection::make($this->chat->conversationsFor($actor)->all())
            ->loadMissing(['members.user:id,full_name', 'latestMessage.sender:id,full_name']);

        return [
            'conversations' => $conversations,
            'canStartDirect' => $canStartDirect,
            'directCandidates' => $canStartDirect ? $this->chat->directConversationCandidates($actor) : collect(),
            'directory' => $this->chat->directory($actor),
        ];
    }

    public function store(StoreChatMessageRequest $request, ChatConversation $conversation): JsonResponse|RedirectResponse
    {
        $message = $this->chat->sendMessage($conversation, $request->user(), $request->string('message')->toString())
            ->load('sender:id,full_name');

        if ($request->expectsJson()) {
            return response()->json(['data' => ChatPresenter::messagePayload($message, $request->user()->id)], 201);
        }

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Near-real-time polling (BRD §14 has no live-transport requirement, so this is a
     * plain, cheap `id > ?` fetch, not a websocket/SSE layer): the browser calls this
     * every few seconds while a conversation is open and appends whatever is new.
     * Deletions of messages the caller already has are NOT reflected here — this only
     * ever returns rows with `id` greater than what the caller has already seen; a full
     * page load is still what reconciles a deletion into an already-rendered older
     * message.
     */
    public function poll(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $actor = $request->user();

        $messages = $conversation->messages()
            ->with('sender:id,full_name')
            ->where('id', '>', $request->integer('after'))
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $messages->map(fn (ChatMessage $m) => ChatPresenter::messagePayload($m, $actor->id))->values(),
        ]);
    }

    public function destroy(Request $request, ChatMessage $message): RedirectResponse
    {
        $conversationId = $message->conversation_id;

        $this->chat->deleteMessage($message, $request->user());

        return redirect()->route('chat.show', $conversationId)->with('status', __('agencyos.chat.flash.message_deleted'));
    }

    public function startDirect(StartDirectChatRequest $request): RedirectResponse
    {
        $conversation = $this->chat->startDirectConversation(
            $request->user(),
            User::findOrFail($request->integer('user_id')),
        );

        return redirect()->route('chat.show', $conversation);
    }

    public function startDirectMessage(StartDirectMessageRequest $request): RedirectResponse
    {
        $conversation = $this->chat->startDirectMessage(
            $request->user(),
            User::findOrFail($request->integer('user_id')),
        );

        return redirect()->route('chat.show', $conversation);
    }
}
