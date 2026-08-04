<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Enums\WhatsappLinkAction;
use App\Models\Project;
use App\Models\ProjectWhatsappLinkVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectWhatsappLinkVersion> */
class ProjectWhatsappLinkVersionFactory extends Factory
{
    protected $model = ProjectWhatsappLinkVersion::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version_no' => 1,
            'group_url' => 'https://chat.whatsapp.com/'.fake()->lexify('????????????'),
            'group_label' => fake()->words(2, true),
            'action_type' => WhatsappLinkAction::Created->value,
            'created_by' => User::factory()->role(RoleCode::Manager),
            'created_at' => now(),
            'is_current' => true,
        ];
    }

    /** Only one version per project may be current (partial unique index). */
    public function superseded(): static
    {
        return $this->state(fn (): array => ['is_current' => false]);
    }

    public function updated(int $versionNo): static
    {
        return $this->state(fn (): array => [
            'version_no' => $versionNo,
            'action_type' => WhatsappLinkAction::Updated->value,
        ]);
    }

    /** A removal clears the URL — `pwlv_url_check` pairs the two. */
    public function removed(int $versionNo): static
    {
        return $this->state(fn (): array => [
            'version_no' => $versionNo,
            'action_type' => WhatsappLinkAction::Removed->value,
            'group_url' => null,
            'group_label' => null,
        ]);
    }
}
