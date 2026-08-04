<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetWhatsappLinkRequest;
use App\Models\Project;
use App\Services\ProjectWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Thin controller — validation in the Form Request, business rules in ProjectWhatsappService. */
class ProjectWhatsappController extends Controller
{
    public function __construct(private readonly ProjectWhatsappService $service) {}

    /** Version history + the invite-deliveries ledger for this project. JSON only — the real UI loads this data directly on the project show page. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => [
                'versions' => $project->whatsappLinkVersions()->latest('version_no')->get(),
                'deliveries' => $project->inviteDeliveries()->latest('id')->get(),
            ],
        ]);
    }

    public function store(SetWhatsappLinkRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $version = $this->service->setLink(
            $project,
            $request->string('url')->toString(),
            $request->input('label'),
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', __('agencyos.projects.flash.whatsapp_set'));
        }

        return response()->json(['data' => $version], 201);
    }

    public function destroy(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $version = $this->service->removeLink($project, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', __('agencyos.projects.flash.whatsapp_removed'));
        }

        return response()->json(['data' => $version]);
    }
}
