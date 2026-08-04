<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;

/** BRD §7.1 — clients are disabled, never deleted, to preserve historical reference. */
class ClientService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $attributes, User $actor): Client
    {
        $client = Client::create($attributes + [
            'status' => ClientStatus::Active->value,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            action: 'client.created',
            entityType: 'client',
            entityId: $client->id,
            after: ['name' => $client->name],
            actorId: $actor->id,
        );

        return $client;
    }

    public function update(Client $client, array $attributes, User $actor): Client
    {
        $before = $client->only(array_keys($attributes));

        $client->fill($attributes)->save();

        $this->audit->log(
            action: 'client.updated',
            entityType: 'client',
            entityId: $client->id,
            before: $before,
            after: $attributes,
            actorId: $actor->id,
        );

        return $client->refresh();
    }

    public function deactivate(Client $client, User $actor): Client
    {
        $client->forceFill(['status' => ClientStatus::Inactive->value])->save();

        $this->audit->log(
            action: 'client.deactivated',
            entityType: 'client',
            entityId: $client->id,
            before: ['status' => ClientStatus::Active->value],
            after: ['status' => ClientStatus::Inactive->value],
            actorId: $actor->id,
        );

        return $client->refresh();
    }

    public function reactivate(Client $client, User $actor): Client
    {
        $client->forceFill(['status' => ClientStatus::Active->value])->save();

        $this->audit->log(
            action: 'client.reactivated',
            entityType: 'client',
            entityId: $client->id,
            before: ['status' => ClientStatus::Inactive->value],
            after: ['status' => ClientStatus::Active->value],
            actorId: $actor->id,
        );

        return $client->refresh();
    }
}
