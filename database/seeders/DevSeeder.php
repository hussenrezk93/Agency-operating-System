<?php

namespace Database\Seeders;

use App\Enums\ActivationState;
use App\Enums\Priority;
use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\DepartmentRoute;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\ChatService;
use App\Services\DeadlineService;
use App\Services\PerformanceService;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * DEVELOPMENT ONLY — refuses to run in production.
 * Credentials (documented, non-production): password for every account = Demo123!
 *   admin / manager / tl / tl2 / tl3 / employee / employee2   (ready to use)
 *   newuser                                   (forced password change on first login)
 *   disabled.user                             (deactivated — login blocked)
 *
 * Beyond accounts, this also seeds a small but varied set of clients/projects/tasks (one
 * of each workflow state — waiting assignment, in progress, overdue, under review, changes
 * requested, transferred between departments, completed, on hold, cancelled, urgent) plus a
 * few chat messages and this/last month's performance snapshots, so every real screen has
 * something to show right after a fresh `migrate:fresh --seed` instead of empty states.
 * That demo-content block runs once — it's skipped if any Task already exists.
 */
class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo123!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(RoleSeeder::class);
        $role = fn (RoleCode $c) => Role::where('code', $c->value)->firstOrFail()->id;

        $marketing = Department::firstOrCreate(['name' => 'Marketing'], ['is_active' => true]);
        $content = Department::firstOrCreate(['name' => 'Content'], ['is_active' => true]);
        $design = Department::firstOrCreate(['name' => 'Design'], ['is_active' => true]);

        $mk = fn (array $attrs) => User::updateOrCreate(
            ['username' => $attrs['username']],
            $attrs + ['password_hash' => Hash::make(self::DEMO_PASSWORD), 'status' => 'active',
                'must_change_password' => false, 'email_verified_at' => now()],
        );

        $admin = $mk(['username' => 'admin', 'full_name' => 'Khaled Samir',
            'role_id' => $role(RoleCode::Admin), 'personal_email' => 'admin@dev.local']);
        $manager = $mk(['username' => 'manager', 'full_name' => 'Omar El-Sayed',
            'role_id' => $role(RoleCode::Manager), 'personal_email' => 'manager@dev.local']);
        $sara = $mk(['username' => 'tl', 'full_name' => 'Sara Mostafa',
            'role_id' => $role(RoleCode::TeamLeader), 'department_id' => $marketing->id,
            'personal_email' => 'tl@dev.local']);
        $mohamed = $mk(['username' => 'employee', 'full_name' => 'Mohamed Ali',
            'role_id' => $role(RoleCode::Employee), 'department_id' => $marketing->id,
            'personal_email' => 'employee@dev.local']);
        $nada = $mk(['username' => 'newuser', 'full_name' => 'Nada Adel',
            'role_id' => $role(RoleCode::Employee), 'department_id' => $content->id,
            'personal_email' => 'newuser@dev.local', 'must_change_password' => true]);
        $mk(['username' => 'disabled.user', 'full_name' => 'Tarek Hussein',
            'role_id' => $role(RoleCode::Employee), 'department_id' => $content->id,
            'personal_email' => 'disabled@dev.local', 'status' => 'inactive']);
        $laila = $mk(['username' => 'tl2', 'full_name' => 'Laila Fathy',
            'role_id' => $role(RoleCode::TeamLeader), 'department_id' => $content->id,
            'personal_email' => 'tl2@dev.local']);
        $ahmed = $mk(['username' => 'tl3', 'full_name' => 'Ahmed Nabil',
            'role_id' => $role(RoleCode::TeamLeader), 'department_id' => $design->id,
            'personal_email' => 'tl3@dev.local']);
        $yasmin = $mk(['username' => 'employee2', 'full_name' => 'Yasmin Adel',
            'role_id' => $role(RoleCode::Employee), 'department_id' => $design->id,
            'personal_email' => 'employee2@dev.local']);

        foreach ([[$marketing, $sara], [$content, $laila], [$design, $ahmed]] as [$department, $leader]) {
            DepartmentLeadershipAssignment::firstOrCreate(
                ['department_id' => $department->id, 'assignment_type' => 'primary', 'is_active' => true],
                [
                    'user_id' => $leader->id,
                    'start_date' => now()->toDateString(),
                    'activation_state' => ActivationState::Active->value,
                    'assigned_by' => $manager->id,
                ],
            );
        }

        // Admin-managed routing matrix (BRD §15) — a minimal, obviously-demo sample.
        foreach ([[$marketing, $content], [$marketing, $design], [$content, $design]] as [$from, $to]) {
            DepartmentRoute::firstOrCreate(
                ['from_department_id' => $from->id, 'to_department_id' => $to->id],
                ['is_allowed' => true, 'updated_by' => $admin->id],
            );
        }

        if (Task::query()->exists()) {
            return;
        }

        $this->seedDemoOperationalContent($manager, $sara, $laila, $ahmed, $mohamed, $nada, $yasmin, $marketing, $content, $design);
    }

    private function seedDemoOperationalContent(
        User $manager,
        User $sara,
        User $laila,
        User $ahmed,
        User $mohamed,
        User $nada,
        User $yasmin,
        Department $marketing,
        Department $content,
        Department $design,
    ): void {
        $workflow = app(TaskWorkflowService::class);
        $chat = app(ChatService::class);
        $performance = app(PerformanceService::class);
        $deadlines = app(DeadlineService::class);

        $nileClient = Client::firstOrCreate(['name' => 'Nile Retail Group'],
            ['phone' => '+20 100 111 2222', 'status' => 'active', 'created_by' => $manager->id]);
        $cairoClient = Client::firstOrCreate(['name' => 'Cairo Fintech Co'],
            ['phone' => '+20 100 333 4444', 'status' => 'active', 'created_by' => $manager->id]);
        $deltaClient = Client::firstOrCreate(['name' => 'Delta Logistics'],
            ['phone' => '+20 100 555 6666', 'status' => 'active', 'created_by' => $manager->id]);

        $retailProject = Project::firstOrCreate(['project_code' => 'PRJ-DEMO-001'],
            ['client_id' => $nileClient->id, 'name' => 'Retail Rebrand 2026', 'status' => 'active', 'created_by' => $manager->id]);
        $retailProject->departments()->syncWithoutDetaching([$marketing->id => ['is_active' => true, 'added_at' => now()]]);

        $fintechProject = Project::firstOrCreate(['project_code' => 'PRJ-DEMO-002'],
            ['client_id' => $cairoClient->id, 'name' => 'Fintech App Launch Campaign', 'status' => 'active', 'created_by' => $manager->id]);
        $fintechProject->departments()->syncWithoutDetaching([
            $marketing->id => ['is_active' => true, 'added_at' => now()],
            $content->id => ['is_active' => true, 'added_at' => now()],
        ]);

        $deltaProject = Project::firstOrCreate(['project_code' => 'PRJ-DEMO-003'],
            ['client_id' => $deltaClient->id, 'name' => 'Logistics Brand Refresh', 'status' => 'active', 'created_by' => $manager->id]);
        $deltaProject->departments()->syncWithoutDetaching([$design->id => ['is_active' => true, 'added_at' => now()]]);

        $create = fn (array $data) => $workflow->createTask($manager, $data);

        // Waiting assignment — nothing picked up yet.
        $create([
            'title' => 'Design social media campaign assets',
            'brief' => 'A set of Instagram and Facebook creatives for the Q4 push.',
            'first_department_id' => $marketing->id,
        ]);
        $create([
            'title' => 'Write Q4 blog content calendar',
            'brief' => 'Topics and publish dates for October through December.',
            'first_department_id' => $content->id,
        ]);

        // In progress, on track.
        $taskC = $create([
            'title' => 'Produce product launch teaser video',
            'brief' => '15-30 second teaser for the new product line.',
            'first_department_id' => $marketing->id,
            'project_id' => $retailProject->id,
        ]);
        $workflow->assign($taskC->currentStep, $sara, $mohamed, now()->toDateString(), now()->addDays(4)->toDateString());

        // In progress, already overdue.
        $taskD = $create([
            'title' => 'Create new packaging mockups',
            'brief' => 'Three packaging directions for stakeholder review.',
            'first_department_id' => $design->id,
        ]);
        $workflow->assign($taskD->currentStep, $ahmed, $yasmin, now()->subDays(6)->toDateString(), now()->subDays(2)->toDateString());

        // Under review — submitted, awaiting the TL's decision.
        $taskE = $create([
            'title' => 'Draft email newsletter for October',
            'brief' => 'Monthly newsletter draft for the marketing list.',
            'first_department_id' => $marketing->id,
        ]);
        $stepE = $taskE->currentStep;
        $workflow->assign($stepE, $sara, $mohamed, now()->subDays(3)->toDateString(), now()->addDays(2)->toDateString());
        $workflow->addOutput($stepE, $mohamed, 'https://drive.example.com/demo/newsletter-oct-draft');
        $workflow->submit($stepE, $mohamed);

        // Changes requested — sent back once already.
        $taskF = $create([
            'title' => 'SEO audit report for client site',
            'brief' => 'Full technical and content SEO audit.',
            'first_department_id' => $content->id,
        ]);
        $stepF = $taskF->currentStep;
        $workflow->assign($stepF, $laila, $nada, now()->subDays(5)->toDateString(), now()->subDays(1)->toDateString());
        $workflow->addOutput($stepF, $nada, 'https://drive.example.com/demo/seo-audit-v1');
        $workflow->submit($stepF, $nada);
        $workflow->requestChanges($stepF, $laila, 'Please add competitor benchmarking and fix the broken links section.');

        // Approved on one department and transferred onward — second step waiting assignment.
        $taskG = $create([
            'title' => 'Full campaign brief: research to content plan',
            'brief' => 'Market research first, then hand off for content planning.',
            'first_department_id' => $marketing->id,
            'project_id' => $fintechProject->id,
        ]);
        $stepG1 = $taskG->currentStep;
        $workflow->assign($stepG1, $sara, $mohamed, now()->subDays(8)->toDateString(), now()->subDays(3)->toDateString());
        $workflow->addOutput($stepG1, $mohamed, 'https://drive.example.com/demo/campaign-brief-research');
        $workflow->submit($stepG1, $mohamed);
        $workflow->approve($stepG1, $sara, 'Good research, approved to move to Content.');
        $workflow->sendToNextDepartment($stepG1->fresh(), $sara, $content, 'Ready for content planning.');

        // Fully completed task.
        $taskH = $create([
            'title' => 'Logo refresh for Delta Logistics',
            'brief' => 'Updated logo mark and usage guidelines.',
            'first_department_id' => $design->id,
            'project_id' => $deltaProject->id,
        ]);
        $stepH = $taskH->currentStep;
        $workflow->assign($stepH, $ahmed, $yasmin, now()->subDays(4)->toDateString(), now()->addDays(1)->toDateString());
        $workflow->addOutput($stepH, $yasmin, 'https://drive.example.com/demo/logo-refresh-final');
        $workflow->submit($stepH, $yasmin);
        $workflow->approve($stepH, $ahmed, 'Looks great, approved.');
        $workflow->completeTask($stepH->fresh(), $ahmed);

        // On hold.
        $taskI = $create([
            'title' => 'Website homepage redesign',
            'brief' => 'New homepage layout and hero section.',
            'first_department_id' => $marketing->id,
        ]);
        $workflow->assign($taskI->currentStep, $sara, $mohamed, now()->toDateString(), now()->addDays(10)->toDateString());
        $workflow->hold($taskI, $manager, 'Client requested a pause pending budget approval.');

        // Cancelled.
        $taskJ = $create([
            'title' => 'Print brochure design',
            'brief' => 'Tri-fold brochure for the trade show.',
            'first_department_id' => $content->id,
        ]);
        $workflow->cancelTask($taskJ, $manager, 'Client cancelled the print run.');

        // Urgent, waiting assignment — useful for the search "urgent first" sort demo.
        $create([
            'title' => 'URGENT: Fix broken client-facing landing page',
            'brief' => 'The pricing page is returning a 404 for some visitors.',
            'first_department_id' => $design->id,
            'priority' => Priority::Urgent->value,
        ]);

        // Reclassify every live step's deadline_status against real "now" immediately,
        // rather than waiting for the scheduled sweep commands to run.
        $deadlines->sweepLiveSteps();

        $employeeTlConvo = $chat->resolveEmployeeTlConversation($mohamed);
        $chat->sendMessage($employeeTlConvo, $mohamed, 'Hi Sara, quick question about the teaser video brief — should it be 15s or 30s?');
        $chat->sendMessage($employeeTlConvo, $sara, '30s please, matches the other launch assets.');

        $marketingGroupConvo = $chat->resolveDepartmentGroupConversation($marketing);
        $chat->sendMessage($marketingGroupConvo, $sara, 'Reminder: the product launch teaser is due this week, please flag any blockers early.');

        $managerTlsConvo = $chat->resolveManagerTlsConversation();
        $chat->sendMessage($managerTlsConvo, $manager, 'Heads up — Nile Retail wants to review the campaign brief by Friday.');
        $chat->sendMessage($managerTlsConvo, $sara, 'Noted, Marketing is on track for that.');

        // So Reports/Dashboards/Performance show real numbers right after seeding, without
        // waiting for agencyos:performance-snapshot / agencyos:performance-refresh to run.
        $performance->snapshotAll(now()->startOfMonth()->subMonth());
        $performance->snapshotAll(now()->startOfMonth());
    }
}
