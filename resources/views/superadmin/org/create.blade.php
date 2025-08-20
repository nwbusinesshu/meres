@extends('layouts.master')

@section('title', 'Új cég létrehozása')

@section('content')
  <div class="content-box">
    <h2>Új cég létrehozása</h2>

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('superadmin.organization.store') }}">
      @csrf

      <div class="form-group">
        <label for="organization_name">Cégnév</label>
        <input type="text" name="organization_name" required>
      </div>

      <div class="form-group">
        <label for="admin_name">Admin neve</label>
        <input type="text" name="admin_name" required>
      </div>

      <div class="form-group">
        <label for="admin_email">Admin email címe</label>
        <input type="email" name="admin_email" required>
      </div>

      <button type="submit">Létrehozás</button>
    </form>
  </div>
@endsection