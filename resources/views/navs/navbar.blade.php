<div class="navbar">
  <div class="logo">
    <img src="{{ asset('assets/logo/quarma360.svg') }}" alt="mewo-logo">
  </div>

  @php
    use Illuminate\Support\Facades\Auth;
    use App\Models\Enums\UserType;
    use App\Services\AssessmentService;

    $user = Auth::user();
    $orgId = session('org_id');
    $isSuperadmin = $user && $user->type === UserType::SUPERADMIN;
    $hasOrg = session()->has('org_id');

    $organizations = collect();
    $currentOrg = null;

    if ($user) {
        if ($user->type === UserType::SUPERADMIN) {
            $organizations = \App\Models\Organization::whereNull('removed_at')->get();
        } elseif ($user->type === UserType::ADMIN) {
            $organizations = $user->organizations()
                                  ->wherePivot('role', 'admin')
                                  ->whereNull('organization.removed_at')
                                  ->get();
        } else {
            $organizations = $user->organizations()
                                  ->whereNull('organization.removed_at')
                                  ->get();
        }

        $currentOrg = $organizations->firstWhere('id', $orgId);
    }

    $configChildRoutes = [
      'admin.employee.index',
      'admin.competency.index',
      'admin.ceoranks.index',
      'admin.settings.index',
    ];
    $isOnConfigChild = request()->routeIs($configChildRoutes);
  @endphp

  <div class="menuitems">
    @if ($isSuperadmin && !$hasOrg)
      <a class="menuitem" href="{{ route('superadmin.dashboard') }}" data-route="superadmin.dashboard">
        <i class="fa fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a class="menuitem" href="{{ route('superadmin.competency.index') }}" data-route="superadmin.competency.index">
        <i class="fa fa-medal"></i>
        <span>Globális kompetenciák</span>
      </a>
      <a class="menuitem" href="{{ route('org.select') }}" data-route="org.select">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>Belépés cégbe</span>
      </a>
    @else
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
          <div class="menuitem dropdown-toggle {{ $isOnConfigChild ? 'active' : '' }}" id="config-dropdown-toggle">
            <i class="fa fa-gears"></i>
            <span>Konfiguráció</span>
          </div>
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

      @if ($isSuperadmin && $hasOrg)
        <a class="menuitem text-danger" href="{{ route('superadmin.exit-company') }}">
          <i class="fa fa-right-from-bracket"></i>
          <span>Kilépés a cégből</span>
        </a>
      @endif
    @endif
  </div> <!-- menuitems lezárása -->

  <div class="userinfo">
    @php
      $avatar = session('uavatar');
      $uname = session('uname');
      $uemail = session('uemail');
    @endphp

    @if ($avatar)
      <img class="avatar" src="{{ $avatar }}" alt="avatar">
    @else
      <i class="fa fa-user-circle fallback-avatar"></i>
    @endif

    <div class="userinfo-text">
      <div class="userinfo-name">{{ $uname }}</div>
      <div class="userinfo-email">{{ $uemail }}</div>

      @if ($currentOrg)
        @if ($organizations->count() > 1 && !$isSuperadmin)
          <form class="org-switch" method="POST" action="{{ route('org.switch') }}">
            @csrf
            <select name="org_id" onchange="this.form.submit()">
              @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @if($org->id == $orgId) selected @endif>
                  {{ $org->name }}
                </option>
              @endforeach
            </select>
          </form>
        @else
          <div class="userinfo-org">{{ $currentOrg->name }}</div>
        @endif
      @endif
    </div>

    <a href="{{ route('logout') }}">
      <i class="fa fa-right-from-bracket" data-tippy-content="{{ __('titles.logout') }}"></i>
    </a>
  </div>
</div> <!-- navbar lezárása -->

@if (MyAuth::isAuthorized(UserType::ADMIN) && !AssessmentService::isAssessmentRunning())
  <div class="config-bar" id="config-dropdown" style="{{ $isOnConfigChild ? 'display: flex;' : 'display: none;' }}">
    <a class="menuitem {{ request()->routeIs('admin.employee.index') ? 'active' : '' }}" 
       href="{{ route('admin.employee.index') }}" data-route="admin.employee.index">
      <i class="fa fa-users"></i>
      <span>{{ __('titles.admin.employees') }}</span>
    </a>
    <a class="menuitem {{ request()->routeIs('admin.competency.index') ? 'active' : '' }}" 
       href="{{ route('admin.competency.index') }}" data-route="admin.competency.index">
      <i class="fa fa-medal"></i>
      <span>{{ __('titles.admin.competencies') }}</span>
    </a>
    <a class="menuitem {{ request()->routeIs('admin.ceoranks.index') ? 'active' : '' }}" 
       href="{{ route('admin.ceoranks.index') }}" data-route="admin.ceoranks.index">
      <i class="fa fa-ranking-star"></i>
      <span>{{ __('titles.admin.ceoranks') }}</span>
    </a>
    <a class="menuitem {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" 
   href="{{ route('admin.settings.index') }}" data-route="admin.settings.index">
  <i class="fa fa-sliders"></i>
  <span>Beállítások</span>
</a>

  </div>
@endif

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById('config-dropdown-toggle');
    const dropdown = document.getElementById('config-dropdown');

    if (toggle && dropdown) {
      const isInitiallyOpen = dropdown.style.display === 'flex';

      toggle.addEventListener('click', function () {
        if (!isInitiallyOpen) {
          dropdown.style.display = (dropdown.style.display === 'none' || dropdown.style.display === '') ? 'flex' : 'none';
        }
      });
    }
  });
</script>
