<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thin controller — validation in Form Requests, business rules in ClientService. */
class ClientController extends Controller
{
    public function __construct(private readonly ClientService $service) {}

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()->withCount('projects')->orderBy('name')->get();

        if (! $request->expectsJson()) {
            return view('clients.index', [
                'clients' => $clients,
                'canCreate' => $request->user()->can('create', Client::class),
                'canUpdate' => $request->user()->can('update', Client::class),
                'canDeactivate' => $request->user()->can('deactivate', Client::class),
                'canReactivate' => $request->user()->can('reactivate', Client::class),
            ]);
        }

        return response()->json(['data' => $clients]);
    }

    /** Blade-only — the create-client form. */
    public function create(Request $request): View
    {
        $this->authorize('create', Client::class);

        return view('clients.create');
    }

    /** Blade-only — the edit-client form. */
    public function edit(Request $request, Client $client): View
    {
        $this->authorize('update', Client::class);

        return view('clients.edit', ['client' => $client]);
    }

    public function store(StoreClientRequest $request): JsonResponse|RedirectResponse
    {
        $client = $this->service->create($request->validated(), $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('clients.index')->with('status', __('agencyos.clients.flash.created'));
        }

        return response()->json(['data' => $client], 201);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse|RedirectResponse
    {
        $updated = $this->service->update($client, $request->validated(), $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('clients.index')->with('status', __('agencyos.clients.flash.updated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function deactivate(Request $request, Client $client): JsonResponse|RedirectResponse
    {
        $this->authorize('deactivate', Client::class);

        $updated = $this->service->deactivate($client, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('clients.index')->with('status', __('agencyos.clients.flash.deactivated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function reactivate(Request $request, Client $client): JsonResponse|RedirectResponse
    {
        $this->authorize('reactivate', Client::class);

        $updated = $this->service->reactivate($client, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('clients.index')->with('status', __('agencyos.clients.flash.reactivated'));
        }

        return response()->json(['data' => $updated]);
    }
}
