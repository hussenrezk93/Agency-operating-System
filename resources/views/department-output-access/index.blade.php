@extends('layouts.app')
@section('title', __('agencyos.output_access.index.title'))
@section('page', 'output-access')
@section('page_header')
    <div class="page-head"><div>
        <h1>{{ __('agencyos.output_access.index.title') }}</h1>
        <div class="page-sub">{{ __('agencyos.output_access.index.subtitle') }}</div>
    </div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="dx-card" style="margin-bottom:18px">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.output_access.index.new_rule') }}</h2></div></div>
        <div class="dx-card-body">
            <form method="POST" action="{{ route('department-output-access.upsert') }}" class="form-row form-row-quad" style="align-items:end">
                @csrf
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.viewer') }}</label>
                    <x-form-select name="viewer_department_id" required
                        :options="$departments->pluck('name', 'id')"
                        :selected="old('viewer_department_id')"/>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.source') }}</label>
                    <x-form-select name="source_department_id" required
                        :options="$departments->pluck('name', 'id')"
                        :selected="old('source_department_id')"/>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.output_access.fields.scope') }}</label>
                    <x-form-select name="scope" required
                        :options="['final_only' => __('agencyos.output_access.scope.final_only'), 'all_outputs' => __('agencyos.output_access.scope.all_outputs')]"
                        :selected="old('scope', 'final_only')"/>
                </div>
                <div class="field">
                    <input type="hidden" name="is_allowed" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.output_access.index.new_rule') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="dx-table">
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
                        <td><span class="dx-td-main">{{ $rule->viewerDepartment->name }}</span></td>
                        <td>{{ $rule->sourceDepartment->name }}</td>
                        <td><x-dx-pill :badge="['class' => $rule->scope->value === 'all_outputs' ? 'b-progress' : 'b-approved', 'label' => __('agencyos.output_access.scope.'.$rule->scope->value)]"/></td>
                        <td>
                            @if($rule->is_allowed)
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.output_access.index.allowed')]"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-cancel', 'label' => __('agencyos.output_access.index.blocked')]"/>
                            @endif
                        </td>
                        <td class="dx-td-end" style="white-space:nowrap">
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
                    <tr><td colspan="5" class="dx-empty-cell">{{ __('agencyos.output_access.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
