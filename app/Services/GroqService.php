<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Thin wrapper around Groq's OpenAI-compatible chat completions endpoint, powering
 * the Za'tar assistant widget. The API key never reaches the browser — the widget
 * only ever talks to AssistantController.
 *
 * When $actor is given, the model is offered a role-scoped set of tools backed by
 * AssistantToolService (read-only) and AssistantActionService (propose/confirm
 * writes) — see toolDefinitions(). Every tool re-runs the same Policy/Service the
 * matching real screen uses, so the model never sees or does more than $actor
 * already can.
 */
class GroqService
{
    public function __construct(
        private readonly AssistantToolService $tools,
        private readonly AssistantActionService $actions,
    ) {}

    /**
     * @param  array<int, array{role: string, text: string}>  $history  Prior turns,
     *                                                                  oldest first, as sent back by the widget (it keeps history client-side —
     *                                                                  nothing is persisted server-side). Role is 'user'/'model' on the wire
     *                                                                  (a holdover from this service's original Gemini backing); 'model' maps
     *                                                                  to OpenAI's 'assistant' role when building the request below.
     */
    public function reply(string $message, array $history, ?User $actor): string
    {
        $apiKey = config('services.groq.key');

        if (! $apiKey) {
            throw new RuntimeException('Groq API key is not configured.');
        }

        $model = config('services.groq.model');
        $messages = $this->buildMessages($message, $history, $actor);
        $tools = $actor === null ? null : $this->toolDefinitions($actor);

        $responseMessage = $this->call($apiKey, $model, $messages, $tools);
        $toolCalls = $responseMessage['tool_calls'] ?? [];

        // One round of tool calls is enough — a write action always spans two
        // separate user turns (propose, then a human confirmation) by design.
        if ($actor !== null && ! empty($toolCalls)) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $responseMessage['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $name = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                $result = $this->runTool($actor, $name, $arguments);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];
            }

            $responseMessage = $this->call($apiKey, $model, $messages, $tools);
        }

        $text = trim((string) ($responseMessage['content'] ?? ''));

        if ($text === '') {
            throw new RuntimeException('Groq returned no reply.');
        }

        return $text;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildMessages(string $message, array $history, ?User $actor): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($actor)]];

        foreach ($history as $turn) {
            $messages[] = [
                'role' => ($turn['role'] ?? 'user') === 'model' ? 'assistant' : 'user',
                'content' => (string) ($turn['text'] ?? ''),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function systemPrompt(?User $actor): string
    {
        $name = __('agencyos.assistant.name');
        $language = app()->isLocale('ar') ? 'Arabic' : 'English';
        $whoText = $actor === null ? '' : " You are currently talking to {$actor->full_name}, whose role is ".
            "{$actor->roleCode()->label()}. You already know their name — never ask for it, and address ".
            'them by name where it feels natural (e.g. greetings, confirmations) rather than every message.';

        return "Your name is {$name}. You are the AI assistant built into Agency OS, an ".
            "internal workflow-management system. Always answer as {$name} — if asked your ".
            "name or who you are, say {$name}. Never mention Groq, Meta, Llama, or any underlying ".
            "model/provider name.{$whoText} The app's interface language is currently {$language} — reply ".
            "in {$language} by default, unless the user writes in a different language, in ".
            'which case match their language instead. When available, use the provided tools '.
            'to look up real data — never invent task codes, statuses, deadlines, reports, or '.
            'user/department names. For any of these actions — creating a user, creating a '.
            'department, appointing a temporary team leader, creating a task, or assigning a '.
            'task to someone — you must first call the matching propose_* tool. Never call '.
            'confirm_action in the same turn as a propose_* call. After proposing, clearly '.
            'summarize in plain language exactly what will happen and ask the user to confirm. '.
            "Only call confirm_action (it takes no arguments) after the user's NEXT message ".
            'clearly confirms (e.g. "yes", "confirm", "ايوة", "أكد") — it always acts on '.
            'whatever you most recently proposed for this user. If they decline or want to '.
            'change something, do not call confirm_action — propose again with the corrected '.
            'details instead (this replaces the earlier proposal).';
    }

    /** @return array<string, mixed> The first choice's message. */
    private function call(string $apiKey, string $model, array $messages, ?array $tools): array
    {
        $payload = ['model' => $model, 'messages' => $messages];

        if ($tools !== null) {
            $payload['tools'] = $tools;
        }

        $response = Http::timeout(20)
            ->withToken($apiKey)
            ->post('https://api.groq.com/openai/v1/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Groq request failed: '.$response->body());
        }

        return $response->json('choices.0.message') ?? [];
    }

    /** Only the tools $actor's role can actually use are ever offered to the model. */
    private function toolDefinitions(User $actor): array
    {
        $tools = collect($this->allToolDefinitions());
        $names = match (true) {
            $actor->hasRole(RoleCode::Admin) => ['get_suspicious_activity'],
            $actor->hasRole(RoleCode::Manager) => [
                'get_my_tasks', 'get_task_status', 'get_department_report', 'get_employee_report',
                'get_due_or_overdue_tasks', 'propose_create_user', 'propose_create_department',
                'propose_assign_temporary_tl', 'confirm_action',
            ],
            $actor->hasRole(RoleCode::TeamLeader) => [
                'get_my_tasks', 'get_task_status', 'get_department_report', 'get_employee_report',
                'get_due_or_overdue_tasks', 'propose_create_task', 'propose_assign_task_step', 'confirm_action',
            ],
            default => ['get_my_tasks', 'get_task_status', 'get_employee_report', 'get_due_or_overdue_tasks'],
        };

        return $tools->filter(fn (array $t) => in_array($t['function']['name'], $names, true))->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function allToolDefinitions(): array
    {
        $empty = ['type' => 'object', 'properties' => new stdClass, 'required' => []];

        return [
            $this->def('get_my_tasks', "List the current user's own Agency OS tasks (up to 30, most recent first).", $empty),
            $this->def('get_task_status', 'Look up a single Agency OS task by its task code (e.g. TSK-2026-00344).', [
                'type' => 'object',
                'properties' => ['task_code' => ['type' => 'string', 'description' => 'e.g. TSK-2026-00344']],
                'required' => ['task_code'],
            ]),
            $this->def('get_department_report', 'Get this month\'s performance report for a department (due/on-time/overdue counts and score). A Team Leader always gets their own department regardless of the name given.', [
                'type' => 'object',
                'properties' => ['department_name' => ['type' => 'string', 'description' => 'Department name (Manager only — ignored for a Team Leader)']],
                'required' => [],
            ]),
            $this->def('get_employee_report', "Get this month's performance report for a named employee.", [
                'type' => 'object',
                'properties' => ['employee_name' => ['type' => 'string', 'description' => "The employee's full name, or the current user's own name for their own report"]],
                'required' => ['employee_name'],
            ]),
            $this->def('get_due_or_overdue_tasks', 'List tasks whose current step is due soon or already overdue, scoped to what the current user can see.', $empty),
            $this->def('get_suspicious_activity', 'Summarize suspicious login activity (failed/throttled/blocked logins) from the audit log over the last 7 days.', $empty),
            $this->def('propose_create_user', 'Validate and preview creating a new user. Does NOT create anything yet — call confirm_action after the user confirms.', [
                'type' => 'object',
                'properties' => [
                    'full_name' => ['type' => 'string'],
                    'username' => ['type' => 'string'],
                    'personal_email' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'description' => 'manager, tl, or employee'],
                    'department_name' => ['type' => 'string', 'description' => 'Required for tl/employee'],
                ],
                'required' => ['full_name', 'username', 'personal_email', 'role'],
            ]),
            $this->def('propose_create_department', 'Validate and preview creating a new department. Does NOT create anything yet — call confirm_action after the user confirms.', [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'primary_leader_name' => ['type' => 'string', 'description' => 'Must be an existing Team Leader'],
                ],
                'required' => ['name', 'primary_leader_name'],
            ]),
            $this->def('propose_assign_temporary_tl', 'Validate and preview appointing a temporary team leader. Does NOT appoint anyone yet — call confirm_action after the user confirms.', [
                'type' => 'object',
                'properties' => [
                    'department_name' => ['type' => 'string'],
                    'candidate_name' => ['type' => 'string', 'description' => 'Must be an Employee in that department'],
                    'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['department_name', 'candidate_name', 'start_date', 'end_date', 'reason'],
            ]),
            $this->def('propose_create_task', 'Validate and preview creating a new task routed to a department. Does NOT create anything yet — call confirm_action after the user confirms.', [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'brief' => ['type' => 'string'],
                    'first_department_name' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'description' => 'low, medium, high, or urgent (optional, defaults to medium)'],
                    'project_name' => ['type' => 'string', 'description' => 'optional'],
                ],
                'required' => ['title', 'brief', 'first_department_name'],
            ]),
            $this->def('propose_assign_task_step', 'Validate and preview assigning a waiting task to someone. Does NOT assign anything yet — call confirm_action after the user confirms.', [
                'type' => 'object',
                'properties' => [
                    'task_code' => ['type' => 'string'],
                    'assignee_name' => ['type' => 'string'],
                    'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                ],
                'required' => ['task_code', 'assignee_name', 'start_date', 'due_date'],
            ]),
            $this->def('confirm_action', 'Execute whatever action was most recently proposed via a propose_* tool for this user. Takes no arguments. Only call this after the user explicitly confirms in their next message.', $empty),
        ];
    }

    /** @return array<string, mixed> */
    private function def(string $name, string $description, array $parameters): array
    {
        return ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $parameters]];
    }

    private function runTool(User $actor, string $name, array $arguments): mixed
    {
        return match ($name) {
            'get_my_tasks' => $this->tools->myTasks($actor),
            'get_task_status' => $this->tools->taskStatus($actor, (string) ($arguments['task_code'] ?? ''))
                ?? ['error' => 'Task not found or not accessible to this user.'],
            'get_department_report' => $this->tools->departmentReport($actor, $arguments['department_name'] ?? null),
            'get_employee_report' => $this->tools->employeeReport($actor, (string) ($arguments['employee_name'] ?? '')),
            'get_due_or_overdue_tasks' => $this->tools->dueOrOverdueTasks($actor),
            'get_suspicious_activity' => $this->tools->suspiciousActivity($actor),
            'propose_create_user' => $this->actions->proposeCreateUser($actor, $arguments),
            'propose_create_department' => $this->actions->proposeCreateDepartment($actor, $arguments),
            'propose_assign_temporary_tl' => $this->actions->proposeAssignTemporaryTl($actor, $arguments),
            'propose_create_task' => $this->actions->proposeCreateTask($actor, $arguments),
            'propose_assign_task_step' => $this->actions->proposeAssignTaskStep($actor, $arguments),
            'confirm_action' => $this->actions->confirmAction($actor),
            default => ['error' => 'Unknown tool.'],
        };
    }
}
