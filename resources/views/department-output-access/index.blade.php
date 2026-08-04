@extends('layouts.app')
@section('title', __('agencyos.output_access.index.title'))
@section('page', 'output-access')
@section('content')
<main class="page">
    <div class="page-head"><div>
        <h1>{{ __('agencyos.output_access.index.title') }}</h1>
        <div class="page-sub">{{ __('agencyos.output_access.index.subtitle') }}</div>
    </div></div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.output_access.index.new_rule') }}</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('department-output-access.upsert') }}" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr auto">
                @csrf
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.viewer') }}</label>
                    <select name="viewer_department_id" required>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.source') }}</label>
                    <select name="source_department_id" required>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.scope') }}</label>
                    <select name="scope" required>
                        <option value="final_only">{{ __('agencyos.output_access.scope.final_only') }}</option>
                        <option value="all_outputs">{{ __('agencyos.output_access.scope.all_outputs') }}</option>
                    </select>
                </div>
                <div class="field">
                    <input type="hidden" name="is_allowed" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.output_access.index.new_rule') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.output_access.fields.viewer') }}</th>
                    <th>{{ __('agencyos.output_access.fields.source') }}</th>
                    <th>{{ __('agencyos.output_access.fields.scope') }}</th>
                    <th>{{ __('agencyos.output_access.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td><b>{{ $rule->viewerDepartment->name }}</b></td>
                        <td>{{ $rule->sourceDepartment->name }}</td>
                        <td><span class="badge {{ $rule->scope->value === 'all_outputs' ? 'b-progress' : 'b-approved' }}">{{ __('agencyos.output_access.scope.'.$rule->scope->value) }}</span></td>
                        <td>
                            @if($rule->is_allowed)
                                <span class="badge b-approved"><span class="bdot"></span>{{ __('agencyos.output_access.index.allowed') }}</span>
                            @else
                                <span class="badge b-cancel">{{ __('agencyos.output_access.index.blocked') }}</span>
                            @endif
                        </td>
                        <td style="text-align:end;white-space:nowrap">
                            <form method="POST" action="{{ route('department-output-access.upsert') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="viewer_department_id" value="{{ $rule->viewer_department_id }}">
                                <input type="hidden" name="source_department_id" value="{{ $rule->source_department_id }}">
                                <input type="hidden" name="scope" value="{{ $rule->scope->value === 'all_outputs' ? 'final_only' : 'all_outputs' }}">
                                <input type="hidden" name="is_allowed" value="{{ $rule->is_allowed ? '1' : '0' }}">
                                <button type="submit" class="btn btn-sm btn-outline">⇄ {{ __('agencyos.output_access.index.flip_scope') }}</button>
                            </form>
                            <form method="POST" action="{{ route('department-output-access.upsert') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="viewer_department_id" value="{{ $rule->viewer_department_id }}">
                                <input type="hidden" name="source_department_id" value="{{ $rule->source_department_id }}">
                                <input type="hidden" name="scope" value="{{ $rule->scope->value }}">
                                <input type="hidden" name="is_allowed" value="{{ $rule->is_allowed ? '0' : '1' }}">
                                <button type="submit" class="btn btn-sm {{ $rule->is_allowed ? 'btn-danger-outline' : 'btn-outline' }}">
                                    {{ $rule->is_allowed ? __('agencyos.output_access.index.block') : __('agencyos.output_access.index.allow') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.output_access.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
