<?php

namespace Database\Seeders;

use App\Enums\ActivationState;
use App\Enums\AdjustmentType;
use App\Enums\DepartmentSpecialRole;
use App\Enums\Priority;
use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentDailyReport;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\DepartmentRoute;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\ClientService;
use App\Services\DeadlineService;
use App\Services\DepartmentReportService;
use App\Services\PayrollService;
use App\Services\PerformanceAdjustmentService;
use App\Services\PerformanceService;
use App\Services\ProjectService;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * A believable workspace to look around in, so the app is worth opening on a fresh
 * install instead of showing empty dashboards and zeroed reports.
 *
 * Everything here is invented. The point is not the data but the SHAPE of it: tasks
 * sitting in every workflow state at once, deadlines in every condition, a month of
 * performance to score, reports at each stage of their own cycle, and a payroll with
 * one month already paid and frozen.
 *
 * It is built by driving the real services rather than inserting rows, so the demo
 * cannot contain a state the application itself would refuse to produce -- every task
 * here carries genuine history, timestamps and deadline states.
 *
 * Shared password for every account: demo123
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo123';

    /** @var array<string, User> */
    private array $people = [];

    /** @var array<string, Department> */
    private array $departments = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(RoleSeeder::class);

        $this->seedDepartments();
        $this->seedPeople();
        $this->seedRouting();
        $this->seedClientsAndProjects();
        $this->seedTasks();
        $this->seedDailyReports();
        $this->seedPerformanceAndPay();

        $this->command?->info('Demo workspace ready. Sign in with any username below, password: '.self::PASSWORD);
        $this->command?->table(
            ['Username', 'Role'],
            [
                ['manager', 'Manager — sees everything, approves, runs payroll'],
                ['admin', 'Admin — configuration and audit only'],
                ['leila.mansour', 'Team Leader — Graphic'],
                ['omar.zaki', 'Team Leader — Content'],
                ['salma.fouad', 'Employee — Graphic'],
            ],
        );
    }

    // ------------------------------------------------------------- organisation

    private function seedDepartments(): void
    {
        $definitions = [
            ['name' => 'Marketing', 'special_role' => null],
            ['name' => 'Content', 'special_role' => DepartmentSpecialRole::Content->value],
            ['name' => 'Graphic', 'special_role' => DepartmentSpecialRole::Graphic->value],
            ['name' => 'Photography/Videography', 'special_role' => DepartmentSpecialRole::PhotographyVideography->value],
            ['name' => 'Moderation', 'special_role' => DepartmentSpecialRole::Moderator->value],
            ['name' => 'Sales', 'special_role' => DepartmentSpecialRole::Sales->value],
            ['name' => 'Web Development', 'special_role' => null],
        ];

        foreach ($definitions as $d) {
            $this->departments[$d['name']] = Department::updateOrCreate(
                ['name' => $d['name']],
                ['is_active' => true, 'special_role' => $d['special_role']],
            );
        }
    }

    private function seedPeople(): void
    {
        $role = fn (RoleCode $c) => Role::where('code', $c->value)->firstOrFail()->id;

        $make = function (string $username, string $name, RoleCode $roleCode, ?string $department = null) use ($role): User {
            return $this->people[$username] = User::updateOrCreate(
                ['username' => $username],
                [
                    'full_name' => $name,
                    'role_id' => $role($roleCode),
                    'department_id' => $department ? $this->departments[$department]->id : null,
                    'personal_email' => $username.'@example.com',
                    'password_hash' => Hash::make(self::PASSWORD),
                    'status' => 'active',
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ],
            );
        };

        $make('admin', 'System Administrator', RoleCode::Admin);
        $manager = $make('manager', 'Rana Toulan', RoleCode::Manager);

        $leaders = [
            'leila.mansour' => ['Leila Mansour', 'Graphic'],
            'omar.zaki' => ['Omar Zaki', 'Content'],
            'karim.adel' => ['Karim Adel', 'Marketing'],
            'nour.sabry' => ['Nour Sabry', 'Photography/Videography'],
            'mariam.nabil' => ['Mariam Nabil', 'Moderation'],
            'tarek.sami' => ['Tarek Sami', 'Sales'],
            'amir.helmy' => ['Amir Helmy', 'Web Development'],
        ];

        foreach ($leaders as $username => [$name, $department]) {
            $leader = $make($username, $name, RoleCode::TeamLeader, $department);

            DepartmentLeadershipAssignment::updateOrCreate(
                [
                    'department_id' => $this->departments[$department]->id,
                    'assignment_type' => 'primary',
                    'is_active' => true,
                ],
                [
                    'user_id' => $leader->id,
                    'start_date' => now()->subMonths(6)->toDateString(),
                    'activation_state' => ActivationState::Active->value,
                    'assigned_by' => $manager->id,
                ],
            );
        }

        $employees = [
            'salma.fouad' => ['Salma Fouad', 'Graphic'],
            'youssef.halim' => ['Youssef Halim', 'Graphic'],
            'hana.rashad' => ['Hana Rashad', 'Content'],
            'dina.sherif' => ['Dina Sherif', 'Content'],
            'seif.gaber' => ['Seif Gaber', 'Marketing'],
            'laila.hosny' => ['Laila Hosny', 'Photography/Videography'],
            'mostafa.raafat' => ['Mostafa Raafat', 'Moderation'],
            'reem.sabbagh' => ['Reem Sabbagh', 'Sales'],
        ];

        foreach ($employees as $username => [$name, $department]) {
            $make($username, $name, RoleCode::Employee, $department);
        }
    }

    /** The matrix that decides which department may hand work to which. */
    private function seedRouting(): void
    {
        $allow = function (string $from, string $to): void {
            DepartmentRoute::updateOrCreate(
                [
                    'from_department_id' => $this->departments[$from]->id,
                    'to_department_id' => $this->departments[$to]->id,
                ],
                ['is_allowed' => true, 'updated_by' => $this->people['manager']->id],
            );
        };

        $allow('Marketing', 'Content');
        $allow('Marketing', 'Graphic');
        $allow('Marketing', 'Photography/Videography');
        $allow('Content', 'Graphic');
        $allow('Content', 'Moderation');
        $allow('Graphic', 'Moderation');
        $allow('Graphic', 'Content');
        $allow('Photography/Videography', 'Graphic');
        $allow('Sales', 'Marketing');
        $allow('Web Development', 'Graphic');
    }

    /** Built through the real services, so project codes and audit entries are the ones
     *  the application itself would have produced. */
    private function seedClientsAndProjects(): void
    {
        $clients = app(ClientService::class);
        $projects = app(ProjectService::class);
        $manager = $this->people['manager'];

        $roster = [
            ['name' => 'Northwind Coffee', 'phone' => '+20 100 000 0001'],
            ['name' => 'Atlas Fitness', 'phone' => '+20 100 000 0002'],
            ['name' => 'Verdant Interiors', 'phone' => '+20 100 000 0003'],
        ];

        foreach ($roster as $attributes) {
            if (Client::where('name', $attributes['name'])->exists()) {
                continue;
            }

            $clients->create($attributes, $manager);
        }

        $plan = [
            'Autumn Menu Launch' => ['Northwind Coffee', ['Marketing', 'Graphic', 'Photography/Videography']],
            'Membership Campaign' => ['Atlas Fitness', ['Marketing', 'Content', 'Web Development']],
        ];

        foreach ($plan as $name => [$clientName, $departments]) {
            if (Project::where('name', $name)->exists()) {
                continue;
            }

            $projects->create(
                ['name' => $name],
                Client::where('name', $clientName)->firstOrFail(),
                array_map(fn (string $d) => $this->departments[$d]->id, $departments),
                [],
                $manager,
            );
        }
    }

    // -------------------------------------------------------------------- tasks

    /**
     * One task per interesting state, so every screen has something real on it: a
     * queue waiting to be assigned, work in progress, submissions waiting on each
     * review stage in turn, a change request sent back, an overdue step, and a
     * finished task with its full trail.
     */
    private function seedTasks(): void
    {
        if (Task::query()->exists()) {
            return;   // already seeded; leave the workspace as it stands
        }

        $workflow = app(TaskWorkflowService::class);
        $deadlines = app(DeadlineService::class);
        $manager = $this->people['manager'];

        $project = Project::where('name', 'Autumn Menu Launch')->first();

        $make = function (string $title, string $brief, string $department, Priority $priority, ?Project $project = null) use ($workflow, $manager): Task {
            return $workflow->createTask($manager, [
                'title' => $title,
                'brief' => $brief,
                'priority' => $priority->value,
                'first_department_id' => $this->departments[$department]->id,
                'project_id' => $project?->id,
            ]);
        };

        // 1. Sitting in a Team Leader's queue, nobody assigned yet.
        $make(
            'Ramadan campaign key visual',
            'Three key visuals for the seasonal campaign, one per channel.',
            'Graphic',
            Priority::High,
            $project,
        );

        // 2. Assigned and being worked on, comfortably inside its deadline.
        $inProgress = $make(
            'Product photography for the autumn menu',
            'Twelve dishes, natural light, overhead and 45-degree angles.',
            'Photography/Videography',
            Priority::Medium,
            $project,
        );
        $workflow->assign(
            $inProgress->currentStep,
            $this->people['nour.sabry'],
            $this->people['laila.hosny'],
            now()->subDays(2)->toDateString(),
            now()->addDays(3)->toDateString(),
        );

        // 3. Submitted, waiting on the Team Leader's review.
        $underReview = $make(
            'Weekly social copy',
            'Seven posts, Arabic and English, tone per the brand guide.',
            'Content',
            Priority::Medium,
        );
        $workflow->assign(
            $underReview->currentStep,
            $this->people['omar.zaki'],
            $this->people['hana.rashad'],
            now()->subDays(3)->toDateString(),
            now()->addDay()->toDateString(),
        );
        $workflow->addOutput($underReview->currentStep->refresh(), $this->people['hana.rashad'], 'https://example.com/drafts/weekly-social-copy');
        $workflow->submit($underReview->currentStep->refresh(), $this->people['hana.rashad']);

        // 4. Graphic work approved by its Team Leader, now with Content for review --
        //    the extra stage that only this department's work passes through.
        $withContent = $make(
            'Menu board redesign',
            'In-store menu boards, two sizes, print-ready.',
            'Graphic',
            Priority::High,
            $project,
        );
        $workflow->assign(
            $withContent->currentStep,
            $this->people['leila.mansour'],
            $this->people['salma.fouad'],
            now()->subDays(4)->toDateString(),
            now()->addDays(2)->toDateString(),
        );
        $workflow->addOutput($withContent->currentStep->refresh(), $this->people['salma.fouad'], 'https://example.com/drafts/menu-board-v2');
        $workflow->submit($withContent->currentStep->refresh(), $this->people['salma.fouad']);
        $workflow->approve($withContent->currentStep->refresh(), $this->people['leila.mansour'], 'Layout works. Sending to Content.');

        // 5. Past both earlier stages, waiting on the Manager.
        $withManager = $make(
            'Loyalty programme one-pager',
            'A single page explaining the new loyalty tiers.',
            'Content',
            Priority::Medium,
        );
        $workflow->assign(
            $withManager->currentStep,
            $this->people['omar.zaki'],
            $this->people['dina.sherif'],
            now()->subDays(5)->toDateString(),
            now()->addDay()->toDateString(),
        );
        $workflow->addOutput($withManager->currentStep->refresh(), $this->people['dina.sherif'], 'https://example.com/drafts/loyalty-one-pager');
        $workflow->submit($withManager->currentStep->refresh(), $this->people['dina.sherif']);
        $workflow->approve($withManager->currentStep->refresh(), $this->people['omar.zaki'], 'Reads well.');

        // 6. Sent back for changes, with the reason the reviewer gave.
        $changes = $make(
            'Instagram story templates',
            'Five reusable story templates in the brand palette.',
            'Graphic',
            Priority::Low,
        );
        $workflow->assign(
            $changes->currentStep,
            $this->people['leila.mansour'],
            $this->people['youssef.halim'],
            now()->subDays(6)->toDateString(),
            now()->addDays(4)->toDateString(),
        );
        $workflow->addOutput($changes->currentStep->refresh(), $this->people['youssef.halim'], 'https://example.com/drafts/story-templates-v1');
        $workflow->submit($changes->currentStep->refresh(), $this->people['youssef.halim']);
        $workflow->requestChanges(
            $changes->currentStep->refresh(),
            $this->people['leila.mansour'],
            'The type is too tight on templates 2 and 4 -- give the headline more room.',
        );

        // 7. Overdue: assigned with a due date that has already passed, then run through
        //    the real classifier rather than having the status written by hand.
        $overdue = $make(
            'Landing page for the membership campaign',
            'One page, mobile first, with the sign-up form wired to the CRM.',
            'Web Development',
            Priority::Urgent,
        );
        $workflow->assign(
            $overdue->currentStep,
            $this->people['amir.helmy'],
            $this->people['amir.helmy'],
            now()->subDays(10)->toDateString(),
            now()->subDays(2)->toDateString(),
        );
        $deadlines->recomputeStep($overdue->currentStep->refresh());

        // 8. A finished task, carrying the whole trail behind it.
        $done = $make(
            'Summer campaign wrap-up report',
            'Performance summary for the summer campaign across all channels.',
            'Marketing',
            Priority::Medium,
        );
        $workflow->assign(
            $done->currentStep,
            $this->people['karim.adel'],
            $this->people['seif.gaber'],
            now()->subDays(20)->toDateString(),
            now()->subDays(12)->toDateString(),
        );
        $workflow->addOutput($done->currentStep->refresh(), $this->people['seif.gaber'], 'https://example.com/reports/summer-wrap-up');
        $workflow->submit($done->currentStep->refresh(), $this->people['seif.gaber']);
        $workflow->approve($done->currentStep->refresh(), $this->people['karim.adel'], 'Numbers check out.');
        $workflow->approve($done->currentStep->refresh(), $manager, 'Approved.');
        $workflow->completeTask($done->currentStep->refresh(), $manager);
    }

    // ------------------------------------------------------------------ reports

    private function seedDailyReports(): void
    {
        $reports = app(DepartmentReportService::class);
        $reports->generateForToday();

        // One department has already written and submitted today's report, and the
        // Manager has approved it -- so the screen shows every stage of the cycle at
        // once rather than a column of identical "not submitted" rows.
        $marketing = DepartmentDailyReport::where('department_id', $this->departments['Marketing']->id)
            ->where('type', 'summary')
            ->first();

        if ($marketing !== null && ! $marketing->isSubmitted()) {
            $reports->submit(
                $marketing,
                $this->people['karim.adel'],
                "Closed out the summer wrap-up and briefed the autumn menu shoot.\n"
                .'Two posts scheduled for tomorrow morning.',
                [],
                'Waiting on the client to confirm the shoot date.',
            );
            $reports->approve($marketing->refresh(), $this->people['manager']);
        }

        // Sales writes collectively, so its report is shown mid-way through: two of the
        // three members have written their part and one has not.
        $sales = DepartmentDailyReport::where('department_id', $this->departments['Sales']->id)
            ->where('type', 'summary')
            ->first();

        if ($sales !== null && ! $sales->isSubmitted()) {
            $reports->contribute($sales, $this->people['tarek.sami'], 'Two client calls, one proposal sent.');
            $reports->contribute($sales, $this->people['reem.sabbagh'], 'Followed up on three leads from the campaign.');
        }
    }

    // ------------------------------------------------------------ money and score

    private function seedPerformanceAndPay(): void
    {
        $performance = app(PerformanceService::class);
        $adjustments = app(PerformanceAdjustmentService::class);
        $payroll = app(PayrollService::class);
        $manager = $this->people['manager'];

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $performance->snapshotAll($lastMonth);
        $performance->snapshotAll($thisMonth);

        // Everything below writes money, and none of it is naturally repeatable: a second
        // run would stack a duplicate bonus onto the month and then fail outright trying
        // to open a pay window that already exists. One guard for the lot.
        if (PayrollPeriod::query()->exists()) {
            return;
        }

        // A month reads as a list of decisions, each with its reason.
        $adjustments->add($this->people['salma.fouad'], $manager, AdjustmentType::Bonus, '1500.00',
            'Delivered the menu boards two days early and covered the shoot.', $thisMonth);
        $adjustments->add($this->people['youssef.halim'], $manager, AdjustmentType::Deduction, '250.00',
            'Story templates missed the agreed deadline by two days.', $thisMonth);
        $adjustments->add($this->people['hana.rashad'], $manager, AdjustmentType::Bonus, '800.00',
            'Took on the loyalty copy on top of her own week.', $thisMonth);

        $salaries = [
            'leila.mansour' => '14000.00', 'omar.zaki' => '13500.00', 'karim.adel' => '13500.00',
            'nour.sabry' => '12000.00', 'mariam.nabil' => '11000.00', 'tarek.sami' => '12500.00',
            'amir.helmy' => '16000.00', 'salma.fouad' => '9000.00', 'youssef.halim' => '8500.00',
            'hana.rashad' => '9000.00', 'dina.sherif' => '8500.00', 'seif.gaber' => '8000.00',
            'laila.hosny' => '8500.00', 'mostafa.raafat' => '7500.00', 'reem.sabbagh' => '8000.00',
        ];

        foreach ($salaries as $username => $amount) {
            $payroll->setSalary($this->people[$username], $manager, $amount);
        }

        // Last month is paid and frozen; this month is still open and reads live.
        foreach (['leila.mansour', 'salma.fouad', 'hana.rashad'] as $username) {
            $period = $payroll->openPeriod(
                $this->people[$username],
                $manager,
                $lastMonth,
                $lastMonth->toDateString(),
                $lastMonth->clone()->endOfMonth()->toDateString(),
            );
            $payroll->closePeriod($period, $manager);
        }

        foreach (['leila.mansour', 'salma.fouad', 'youssef.halim', 'hana.rashad'] as $username) {
            $payroll->openPeriod(
                $this->people[$username],
                $manager,
                $thisMonth,
                $thisMonth->toDateString(),
                $thisMonth->clone()->endOfMonth()->toDateString(),
            );
        }

        $expenses = [
            [5, 'Transport for the autumn menu shoot', '450.00'],
            [7, 'Studio lighting rental, two days', '1800.00'],
            [9, 'Camera body maintenance and sensor clean', '950.00'],
            [12, 'Design software subscriptions, monthly', '2400.00'],
            [15, 'Print samples for the menu boards', '620.00'],
        ];

        foreach ($expenses as [$day, $description, $amount]) {
            $date = $thisMonth->clone()->addDays($day - 1);

            if ($date->isFuture()) {
                $date = now();
            }

            $payroll->addExpense($manager, $date->toDateString(), $description, $amount);
        }
    }
}
