@extends('layouts.app')
@section('title', __('agencyos.clients.edit.title'))
@section('page', 'clients')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.clients.edit.title') }}</h1><div class="page-sub">{{ $client->name }}</div></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <form method="POST" action="{{ route('clients.update', $client) }}" class="dx-card form-card">
        @csrf
        @method('PATCH')
        <div class="form-section">
            <div class="form-grid">
                <div class="field span2 @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.clients.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $client->name) }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('phone') bad @enderror">
                    <label class="req">{{ __('agencyos.clients.fields.phone') }}</label>
                    <input type="tel" inputmode="tel" name="phone" value="{{ old('phone', $client->phone) }}" maxlength="50" pattern="(?=(?:.*[0-9]){7,})[0-9\s()+\-]+" oninput="this.value=this.value.replace(/[^0-9+\s()\-]/g,'')" required>
                    @error('phone')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('company_email') bad @enderror">
                    <label>{{ __('agencyos.clients.fields.company_email') }}</label>
                    <input type="email" name="company_email" value="{{ old('company_email', $client->company_email) }}" maxlength="255">
                    @error('company_email')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2 @error('website_url') bad @enderror">
                    <label>{{ __('agencyos.clients.fields.website_url') }}</label>
                    <input type="url" name="website_url" value="{{ old('website_url', $client->website_url) }}" placeholder="https://">
                    @error('website_url')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2">
                    <label>{{ __('agencyos.clients.fields.short_description') }}</label>
                    <textarea name="short_description">{{ old('short_description', $client->short_description) }}</textarea>
                </div>
            </div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('clients.index') }}">{{ __('agencyos.clients.edit.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.clients.edit.submit') }}</button>
        </div>
    </form>
</main>
@endsection
