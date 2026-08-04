<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\ChatConversation;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatConversation> */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    /**
     * Defaults to `all_tls`, which is one of the types that must NOT name a department —
     * a CHECK constraint pairs the two, so the default state is deliberately consistent.
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::AllTls->value,
            'department_id' => null,
            'title' => null,
            'created_at' => now(),
        ];
    }

    public function departmentGroup(?Department $department = null): static
    {
        return $this->state(fn (): array => [
            'type' => ConversationType::DepartmentGroup->value,
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    public function employeeTl(): static
    {
        return $this->state(fn (): array => [
            'type' => ConversationType::EmployeeTl->value,
            'department_id' => null,
        ]);
    }

    public function managerTls(): static
    {
        return $this->state(fn (): array => [
            'type' => ConversationType::ManagerTls->value,
            'department_id' => null,
        ]);
    }
}
