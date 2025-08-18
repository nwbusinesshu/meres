@extends('layouts.master', ['currentViewName' => 500])

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('global.500') }}</h1>
<h2>500</h2>
<a href="{{ route('login') }}" role="button" class="btn btn-outline-secondary">{{ __('global.error-back-to') }}</a>
@endsection

@section('scripts')
@endsection