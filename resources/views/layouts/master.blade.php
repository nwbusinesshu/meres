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

      @once
        @includeWhen(view()->exists("js.$currentViewName"), "js.$currentViewName")
      @endonce

      {{-- MODALS: támogatja a régi @section és az új @push használatát is --}}
      @if (View::hasSection('modals'))
        @yield('modals')
      @endif
      @stack('modals')

      {{-- SCRIPTS: támogatja a régi @section és az új @push használatát is --}}
      @if (View::hasSection('scripts'))
        @yield('scripts')
      @endif
      @stack('scripts')

      @include('layouts.footer')

      <a href="#" class="floating-help-btn" title="Súgó">
        <span class="icon">?</span>
        <span class="help-label">Súgóközpont</span>
      </a>
    </body>
</html>
