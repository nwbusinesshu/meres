@extends('layouts.master', ['currentViewName' => 403])

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('global.403') }}</h1>
<h2>403</h2>
<a href="{{ route('login') }}" role="button" class="btn btn-outline-secondary">{{ __('global.error-back-to') }}</a>
@endsection

@section('scripts')
@endsection