<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartDirectChatRequest;
use App\Http\Requests\StartDirectMessageRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    public function store(StoreChatMessageRequest $request, ChatConversation $conversation): RedirectResponse
    {
        $this->chat->sendMessage($conversation, $request->user(), $request->string('message')->toString());

        return redirect()->route('chat.show', $conversation);
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
