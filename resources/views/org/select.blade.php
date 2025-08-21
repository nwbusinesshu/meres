@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="company-select-tile">
  <h2>{{ __('Válassz céget') }}</h2>
  <p>{{ __('Kérlek válaszd ki, melyik cég felületén szeretnél dolgozni.') }}</p>

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
    <button type="submit" class="btn btn-primary">{{ __('Belépés') }}</button>
  </form>
@endsection
