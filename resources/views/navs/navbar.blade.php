<div class="navbar">
  <div class="logo">
    <img src="{{ asset('assets/logo/chaos360.svg') }}" alt="mewo-logo">
  </div>
  <div class="menuitems">
    <a class="menuitem" href="{{ route('home-redirect')}}" data-route="home" data-route-secondary="admin.home">
      <i class="fa fa-house"></i>
      <span>{{ __('titles.home') }}</span>
    </a>
    @if (MyAuth::isAuthorized(UserType::ADMIN))
      <a class="menuitem" href="{{ route('admin.results.index') }}" data-route="admin.results.index">
        <i class="fa fa-chart-line"></i>
        <span>{{ __('titles.admin.results') }}</span>
      </a>
      @if (!AssessmentService::isAssessmentRunning())
      <a class="menuitem" href="{{ route('admin.employee.index') }}" data-route="admin.employee.index">
        <i class="fa fa-users"></i>
        <span>{{ __('titles.admin.employees') }}</span>
      </a>
      <a class="menuitem" href="{{ route('admin.competency.index') }}" data-route="admin.competency.index">
        <i class="fa fa-medal"></i>
        <span>{{ __('titles.admin.competencies') }}</span>
      </a>
      <a class="menuitem" href="{{ route('admin.ceoranks.index') }}" data-route="admin.ceoranks.index">
        <i class="fa fa-ranking-star"></i>
        <span>{{ __('titles.admin.ceoranks') }}</span>
      </a>
      @endif
    @else
      @if (Route::currentRouteName() == "assessment.index")
      <a class="menuitem disabled" href="#" data-route="assessment.index">
        <i class="fa fa-file-pen"></i>
        <span>{{ __('titles.assessment') }}</span>
      </a>  
      @endif
      @if (Route::currentRouteName() == "ceorank.index")
      <a class="menuitem disabled" href="#" data-route="ceorank.index">
        <i class="fa fa-ranking-star"></i>
        <span>{{ __('titles.ceorank') }}</span>
      </a>  
      @endif
      <a class="menuitem" href="{{ route('results.index') }}" data-route="results.index">
        <i class="fa fa-chart-line"></i>
        <span>{{ __('titles.results') }}</span>
      </a>
    @endif
  </div>
  <div class="userinfo">
    <span>{{ session('uname') }}</span>
    <a href="{{ route('logout') }}">
      <i class="fa fa-right-from-bracket" data-tippy-content="{{ __('titles.logout') }}"></i>
    </a>
    <img src="{{ session('uavatar') }}" alt="avatar">
  </div>
</div>