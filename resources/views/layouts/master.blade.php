<!DOCTYPE html>
<html>
    @include('layouts.head', ['currentViewName' => $currentViewName])
    <body>
      <div class="page-container">
        <div class="main-container {{ $currentViewName }}-container">
          @includeWhen(MyAuth::isAuthorized(UserType::NORMAL) && !isset($exception), 'navs.navbar')
          <div class="main-content">
            @yield('content')
          </div>
        </div>
      </div>

      @include("js.$currentViewName")
      @yield('scripts')
        @yield('modals')
  @stack('modals')
    @yield('scripts')
  @stack('scripts')
  @include('layouts.footer')
<a href="#" class="floating-help-btn" title="Súgó">
  <span class="icon">?</span>
  <span class="help-label">Súgóközpont</span>
</a>
    </body>
</html>