<?php

namespace App\Http\Requests;

use App\Enums\ReviewDecision;
use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ReviewTaskStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('review', $step) ?? false);
    }

    /**
     * TaskStepPolicy::review() refuses both "not your call" and "there is nothing left
     * to decide" — and a bare 403 for the second one reads as though the reviewer's
     * permissions broke. It is an easy state to land in: the review is two-stage, so a
     * Manager reviewing a self-assigned step approves twice, and one extra click (or a
     * Back-then-resubmit) hits an already-approved step. Say that plainly instead.
     */
    protected function failedAuthorization(): void
    {
        $step = $this->route('step');

        if ($step instanceof TaskStep
            && ($step->workflow_status->isTerminal() || $step->task->isClosed())
            && ! $this->expectsJson()) {
            throw new HttpResponseException(
                redirect()->route('tasks.show', $step->task_id)
                    ->with('status', __('agencyos.tasks.flash.already_decided')),
            );
        }

        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'comment' => [
                Rule::requiredIf($this->input('decision') === ReviewDecision::ChangesRequested->value),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
