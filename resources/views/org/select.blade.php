@extends('layouts.master')
@section('head-extra')
@endsection
@section('content')
<div class="company-select-tile">
  <h2>{{ __('global.company-select-title') }}</h2>
  <p>{{ __('global.company-select-description') }}</p>
  <form method="POST" action="{{ route('org.switch') }}">
    @csrf
    <div class="form-group">
      <select name="org_id" class="form-control" required>
        @foreach($orgs as $org)
          <option value="{{ $org->id }}" {{ session('org_id') == $org->id ? 'selected' : '' }}>
            {{ $org->name }}
          </option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('global.company-select-enter') }}</button>
  </form>
@endsection