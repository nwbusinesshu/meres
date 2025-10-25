@extends('layouts.master', ['currentViewName' => 404])

@section('head-extra')
@endsection

@section('content')
<div class="error-container">
    <div class="error-content">
        <div class="error-text">
            <h1>{{ __('global.404') }}</h1>
            <a href="{{ route('login') }}" role="button" class="btn btn-outline-secondary">{{ __('global.error-back-to') }}</a>
        </div>
        <div class="error-visual">
            <div class="error-number">404</div>
            <img src="{{ asset('assets/img/monster-error-tile.svg') }}" alt="Error Monster" class="error-monster">
        </div>
    </div>
</div>
@endsection

@section('scripts')
@endsection