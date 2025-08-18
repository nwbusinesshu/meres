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
    </body>
</html>