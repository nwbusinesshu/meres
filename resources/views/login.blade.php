@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="tile">
  <img class="chaos-360" src="{{ asset('assets/logo/chaos360.svg') }}" alt="chaos-360">
  <a href="{{ route('trigger-login') }}" role="button" class="btn btn-primary trigger-login">{{ $_('login') }} <i class="fa fa-google"></i></a>
  <img class="mewocont-logo" src="{{ asset('assets/logo/mewocont_logo_dark.svg') }}" alt="">
</div>
@endsection

@section('scripts')
@endsection