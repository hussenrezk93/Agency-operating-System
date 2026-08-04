<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddProjectLinkRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProjectLinkController extends Controller
{
    public function store(AddProjectLinkRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $link = $project->links()->create([
            'url' => $request->string('url')->toString(),
            'label' => $request->input('label'),
            'added_by' => $request->user()->id,
        ]);

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', __('agencyos.projects.flash.link_added'));
        }

        return response()->json(['data' => $link], 201);
    }
}
