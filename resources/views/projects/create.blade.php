@extends('layouts.app')
@section('title', __('agencyos.projects.create.title'))
@section('page', 'projects')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.projects.create.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.projects.create.subtitle') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-section-head"><span class="n">1</span><h2>{{ __('agencyos.projects.fields.name') }}</h2></div>
            <div class="form-grid">
                <div class="field @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.projects.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2">
                    <label>{{ __('agencyos.projects.fields.description') }}</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>
                <div class="field span2 @error('department_ids') bad @enderror">
                    <label class="req">{{ __('agencyos.projects.fields.departments') }}</label>
                    <div style="display:flex;gap:14px;flex-wrap:wrap">
                        @foreach($departments as $department)
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                                <input type="checkbox" name="department_ids[]" value="{{ $department->id }}"
                                    @checked(collect(old('department_ids', []))->contains((string) $department->id))>
                                {{ $department->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('department_ids')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head"><span class="n">2</span><h2>{{ __('agencyos.projects.fields.client') }}</h2></div>
            @php($isNewClient = old('client_source', 'existing') === 'new')
            <div class="choice-toggle" role="radiogroup" aria-label="{{ __('agencyos.projects.fields.client') }}">
                <label class="opt @if(! $isNewClient) sel @endif">
                    <input type="radio" name="client_source" value="existing" @checked(! $isNewClient) id="client-source-existing">
                    <span class="ic-bub">📇</span><span><b>{{ __('agencyos.projects.create.existing_client') }}</b><span class="hint">{{ __('agencyos.projects.create.existing_client_hint') }}</span></span>
                </label>
                <label class="opt @if($isNewClient) sel @endif">
                    <input type="radio" name="client_source" value="new" @checked($isNewClient) id="client-source-new">
                    <span class="ic-bub">✨</span><span><b>{{ __('agencyos.projects.create.new_client') }}</b><span class="hint">{{ __('agencyos.projects.create.new_client_hint') }}</span></span>
                </label>
            </div>

            <div class="field @error('client_id') bad @enderror" id="existing-client-box" style="margin-top:12px;{{ $isNewClient ? 'display:none' : '' }}">
                <label class="req">{{ __('agencyos.projects.fields.client') }}</label>
                <select name="client_id">
                    <option value="">—</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                @error('client_id')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="nested-panel" id="new-client-box" style="{{ $isNewClient ? '' : 'display:none' }}">
                <div class="form-grid">
                    <div class="field @error('new_client_name') bad @enderror">
                        <label class="req">{{ __('agencyos.clients.fields.name') }}</label>
                        <input type="text" name="new_client_name" value="{{ old('new_client_name') }}" maxlength="255">
                        @error('new_client_name')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field @error('new_client_phone') bad @enderror">
                        <label class="req">{{ __('agencyos.clients.fields.phone') }}</label>
                        <input type="text" name="new_client_phone" value="{{ old('new_client_phone') }}" maxlength="50">
                        @error('new_client_phone')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field span2 @error('new_client_email') bad @enderror">
                        <label>{{ __('agencyos.clients.fields.company_email') }}</label>
                        <input type="email" name="new_client_email" value="{{ old('new_client_email') }}" maxlength="255">
                        <div class="hint">{{ __('agencyos.projects.create.new_client_saved_hint') }}</div>
                        @error('new_client_email')<div class="err">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head"><span class="n">3</span><h2>{{ __('agencyos.tasks.fields.reference_links') }}</h2></div>
            <div class="form-grid">
                @for($i = 0; $i < 3; $i++)
                    <div class="field">
                        <label>{{ __('agencyos.tasks.fields.reference_link_n', ['n' => $i + 1]) }} — {{ __('agencyos.tasks.fields.url') }}</label>
                        <input type="url" name="links[{{ $i }}][url]" value="{{ old("links.$i.url") }}" placeholder="https://">
                    </div>
                    <div class="field">
                        <label>{{ __('agencyos.tasks.fields.label') }}</label>
                        <input type="text" name="links[{{ $i }}][label]" value="{{ old("links.$i.label") }}" maxlength="255">
                    </div>
                @endfor
            </div>
        </div>

        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('projects.index') }}">{{ __('agencyos.projects.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.projects.create.submit') }}</button>
        </div>
    </form>
</main>
<script>
(function () {
    var existingRadio = document.getElementById('client-source-existing');
    var newRadio = document.getElementById('client-source-new');
    var existingBox = document.getElementById('existing-client-box');
    var newBox = document.getElementById('new-client-box');
    if (! existingRadio || ! newRadio) return;

    function sync() {
        var isNew = newRadio.checked;
        existingBox.style.display = isNew ? 'none' : '';
        newBox.style.display = isNew ? '' : 'none';
    }

    existingRadio.addEventListener('change', sync);
    newRadio.addEventListener('change', sync);
})();
</script>
@endsection
