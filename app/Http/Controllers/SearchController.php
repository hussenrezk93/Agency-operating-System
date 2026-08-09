<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BRD §16 — task number/title and project name, Urgent tasks sorted first. Visibility
 * mirrors TaskController::index()/ProjectPolicy::view() exactly — search never surfaces
 * something the searcher couldn't already open directly.
 *
 * BRD §15 keeps task/project CONTENT out of Admin's reach entirely, so Admin gets a
 * different search altogether: users and departments (the things Admin actually
 * manages), never task titles or project names.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $actor = $request->user();

        if ($actor->hasRole(RoleCode::Admin)) {
            return $this->adminIndex($query);
        }

        $tasks = collect();
        $projects = collect();

        if ($query !== '') {
            $tasks = Task::query()
                ->where(fn (Builder $q) => $q->where('task_code', 'like', "%{$query}%")->orWhere('title', 'like', "%{$query}%"))
                ->when($actor->hasRole(RoleCode::Employee), fn (Builder $q) => $q->whereHas('steps.assignments', fn ($qq) => $qq->where('assignee_id', $actor->id)))
                ->when($actor->hasRole(RoleCode::TeamLeader), fn (Builder $q) => $q->whereHas('steps', fn ($qq) => $qq->where('department_id', $actor->department_id)))
                ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
                ->latest('id')
                ->limit(25)
                ->get();

            $projects = Project::query()
                ->where('name', 'like', "%{$query}%")
                ->when(! $actor->hasRole(RoleCode::Manager), fn (Builder $q) => $q->whereHas('departments', fn ($qq) => $qq->where('departments.id', $actor->department_id)))
                ->latest('id')
                ->limit(25)
                ->get();
        }

        return view('search.index', [
            'query' => $query,
            'tasks' => $tasks,
            'projects' => $projects,
        ]);
    }

    private function adminIndex(string $query): View
    {
        $users = collect();
        $departments = collect();

        if ($query !== '') {
            $users = User::query()
                ->where(fn (Builder $q) => $q->where('full_name', 'like', "%{$query}%")->orWhere('username', 'like', "%{$query}%"))
                ->with('department:id,name')
                ->latest('id')
                ->limit(25)
                ->get();

            $departments = Department::query()
                ->where('name', 'like', "%{$query}%")
                ->latest('id')
                ->limit(25)
                ->get();
        }

        return view('search.admin-index', [
            'query' => $query,
            'users' => $users,
            'departments' => $departments,
        ]);
    }
}
