@extends('layouts.master')

@section('content')
<div class="tile">
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
    <button type="submit" class="btn btn-primary" style="margin-top:10px">{{ __('Belépés') }}</button>
  </form>
  @if($isSuperAdmin)
    <div style="margin-top:20px">
      <a href="{{ route('admin.home') }}" class="btn btn-link">{{ __('Ugrás az admin felületre (cégek kezelése)') }}</a>
    </div>
  @endif
</div>
@endsection
