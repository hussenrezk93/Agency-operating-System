<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Base exception for valid requests that violate the task workflow. */
class WorkflowException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->back()->withErrors(['workflow' => $this->getMessage()]);
        }

        return response()->json([
            'error' => 'workflow_violation',
            'message' => $this->getMessage(),
        ], 422);
    }
}
