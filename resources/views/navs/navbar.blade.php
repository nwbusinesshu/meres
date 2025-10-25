<div class="navbar">
  <div class="logo">
    <img src="{{ asset('assets/logo/quarma360.svg') }}" alt="mewo-logo">
  </div>

@php
  use Illuminate\Support\Facades\Auth;
  use App\Models\Enums\UserType;
  use App\Models\Enums\OrgRole;  // ✅ ADDED: OrgRole support
  use App\Services\AssessmentService;

  $user = Auth::user();
  $orgId = session('org_id');
  $orgRole = session('org_role');  // ✅ ADDED: Get org role from session
  $isSuperadmin = $user && $user->type === UserType::SUPERADMIN;
  $hasOrg = session()->has('org_id');
  
  // ✅ ADDED: Organization role checks
  $isAdmin = $orgRole === OrgRole::ADMIN;
  $isCeo = $orgRole === OrgRole::CEO;
  $isManager = $orgRole === OrgRole::MANAGER;

  $organizations = collect();
  $currentOrg = null;

  if ($user) {
      if ($user->type === UserType::SUPERADMIN) {
          $organizations = \App\Models\Organization::whereNull('removed_at')->get();
      } else {
          // ✅ CHANGED: Simplified - get all organizations user belongs to
          $organizations = $user->organizations()
                                ->whereNull('organization.removed_at')
                                ->get();
      }

      $currentOrg = $organizations->firstWhere('id', $orgId);
  }

  // Config child routes (for dropdown active state)
  $configChildRoutes = [
    'admin.employee.index',
    'admin.competency.index',
    'admin.ceoranks.index',
    'admin.settings.index',
    'admin.payments.index',
  ];
  $isOnConfigChild = request()->routeIs($configChildRoutes);
  
  // ✅ Check if bonuses should be shown (both settings must be ON)
  $showBonuses = false;
  if ($isAdmin && $orgId) {  // ✅ CHANGED: Use $isAdmin instead of MyAuth
      $showBonusMalus = \App\Services\OrgConfigService::getBool((int)$orgId, 'show_bonus_malus', true);
      $enableBonusCalculation = \App\Services\OrgConfigService::getBool((int)$orgId, 'enable_bonus_calculation', false);
      $showBonuses = $showBonusMalus && $enableBonusCalculation;
  }

  // ✅ Check for unpaid payments (for conditional payments button display)
  $hasUnpaidPayment = false;
  if ($orgId) {
      $hasUnpaidPayment = DB::table('payments')
          ->where('organization_id', $orgId)
          ->where('status', '!=', 'paid')
          ->exists();
  }
@endphp

  <div class="menuitems">
    @if ($isSuperadmin && !$hasOrg)
      <a class="menuitem" href="{{ route('superadmin.dashboard') }}" data-route="superadmin.dashboard">
        <i class="fa fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a class="menuitem" href="{{ route('superadmin.competency.index') }}" data-route="superadmin.competency.index">
        <i class="fa fa-medal"></i>
        <span>{{ __('titles.superadmin.global-competencies') }}</span>
      </a>
      <a class="menuitem {{ request()->routeIs('superadmin.tickets.index') ? 'active' : '' }}" 
         href="{{ route('superadmin.tickets.index') }}" data-route="superadmin.tickets.index">
        <i class="fa fa-ticket"></i>
        <span>{{ __('support.all-tickets') }}</span>
      </a>
      <a class="menuitem" href="{{ route('org.select') }}" data-route="org.select">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>{{ __('global.login-to-org') }}</span>
      </a>
    @else
      <a class="menuitem" href="{{ route('home-redirect')}}" data-route="home" data-route-secondary="admin.home">
        <i class="fa fa-house"></i>
        <span>{{ __('titles.home') }}</span>
      </a>

      @if ($isAdmin)  {{-- ✅ CHANGED: Use $isAdmin instead of MyAuth --}}
        <a class="menuitem" href="{{ route('admin.results.index') }}" data-route="admin.results.index">
          <i class="fa fa-chart-line"></i>
          <span>{{ __('titles.admin.results') }}</span>
        </a>

        @if($showBonuses)
          <a class="menuitem" href="{{ route('admin.bonuses.index') }}" data-route="admin.bonuses.index">
            <i class="fa fa-money-bill-wave"></i>
            <span>{{ __('titles.admin.bonuses') }}</span>
          </a>
        @endif

        @if (!AssessmentService::isAssessmentRunning())
          <div class="menuitem dropdown-toggle {{ $isOnConfigChild ? 'active' : '' }}" id="config-dropdown-toggle">
            <i class="fa fa-gears"></i>
            <span>{{ __('global.navbar-configuration') }}</span>
          </div>
        @endif

        {{-- ✅ NEW: Show payments menu during assessment if unpaid --}}
        @if (AssessmentService::isAssessmentRunning())
          <a class="menuitem {{ request()->routeIs('admin.payments.index') ? 'active' : '' }}"
             href="{{ route('admin.payments.index') }}" data-route="admin.payments.index">
            <i class="fas fa-credit-card"></i>
            <span>{{ __('titles.admin.payments') }}</span>
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

      @if ($isSuperadmin && $hasOrg)
        <a class="menuitem text-danger" href="{{ route('superadmin.exit-company') }}">
          <i class="fa fa-right-from-bracket"></i>
          <span>{{ __('global.logout-org') }}</span>
        </a>
      @endif
    @endif
  </div> {{-- menuitems closing --}}

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
</div> {{-- navbar closing --}}

{{-- ✅ CHANGED: Use $isAdmin instead of MyAuth --}}
@if ($isAdmin && !AssessmentService::isAssessmentRunning())
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
      <span>{{ __('titles.admin.settings') }}</span>
    </a>
    <a class="menuitem {{ request()->routeIs('admin.payments.index') ? 'active' : '' }}" 
       href="{{ route('admin.payments.index') }}" data-route="admin.payments.index">
      <i class="fas fa-credit-card"></i>
      <span>{{ __('titles.admin.payments') }}</span>
    </a>
  </div>
@endif

{{-- ========================================
     MOBILE BOTTOM NAVIGATION
     Only visible on mobile (<768px)
     ======================================== --}}
<div class="mobile-bottom-nav">
  @if ($isSuperadmin && !$hasOrg)
    <a class="menuitem {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}" 
       href="{{ route('superadmin.dashboard') }}">
      <i class="fa fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
    <a class="menuitem {{ request()->routeIs('superadmin.competency.index') ? 'active' : '' }}" 
       href="{{ route('superadmin.competency.index') }}">
      <i class="fa fa-medal"></i>
      <span>{{ __('titles.superadmin.global-competencies') }}</span>
    </a>
    <a class="menuitem {{ request()->routeIs('org.select') ? 'active' : '' }}" 
       href="{{ route('org.select') }}">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>{{ __('global.login-to-org') }}</span>
    </a>
  @else
    <a class="menuitem {{ request()->routeIs('home', 'admin.home') ? 'active' : '' }}" 
       href="{{ route('home-redirect') }}">
      <i class="fa fa-house"></i>
      <span>{{ __('titles.home') }}</span>
    </a>

    @if ($isAdmin)  {{-- ✅ CHANGED: Use $isAdmin instead of MyAuth --}}
      <a class="menuitem {{ request()->routeIs('admin.results.index') ? 'active' : '' }}" 
         href="{{ route('admin.results.index') }}">
        <i class="fa fa-chart-line"></i>
        <span>{{ __('titles.admin.results') }}</span>
      </a>

      @if($showBonuses)
        <a class="menuitem {{ request()->routeIs('admin.bonuses.index') ? 'active' : '' }}" 
           href="{{ route('admin.bonuses.index') }}">
          <i class="fa fa-money-bill-wave"></i>
          <span>{{ __('titles.admin.bonuses') }}</span>
        </a>
      @endif

      @if (!AssessmentService::isAssessmentRunning())
        <div class="menuitem {{ $isOnConfigChild ? 'active' : '' }}" id="config-dropdown-toggle-mobile">
          <i class="fa fa-gears"></i>
          <span>{{ __('global.navbar-configuration') }}</span>
        </div>
      @endif

      @if (AssessmentService::isAssessmentRunning())
        <a class="menuitem {{ request()->routeIs('admin.payments.index') ? 'active' : '' }}"
           href="{{ route('admin.payments.index') }}">
          <i class="fas fa-credit-card"></i>
          <span>{{ __('titles.admin.payments') }}</span>
        </a>
      @endif
    @else
      @if (Route::currentRouteName() == "assessment.index")
        <a class="menuitem disabled" href="#">
          <i class="fa fa-file-pen"></i>
          <span>{{ __('titles.assessment') }}</span>
        </a>
      @endif
      @if (Route::currentRouteName() == "ceorank.index")
        <a class="menuitem disabled" href="#">
          <i class="fa fa-ranking-star"></i>
          <span>{{ __('titles.ceorank') }}</span>
        </a>
      @endif
      <a class="menuitem {{ request()->routeIs('results.index') ? 'active' : '' }}" 
         href="{{ route('results.index') }}">
        <i class="fa fa-chart-line"></i>
        <span>{{ __('titles.results') }}</span>
      </a>
    @endif

    @if ($isSuperadmin && $hasOrg)
      <a class="menuitem text-danger" href="{{ route('superadmin.exit-company') }}">
        <i class="fa fa-right-from-bracket"></i>
        <span>{{ __('global.logout-org') }}</span>
      </a>
    @endif
  @endif
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const toggleDesktop = document.getElementById('config-dropdown-toggle');
    const toggleMobile = document.getElementById('config-dropdown-toggle-mobile');
    const dropdown = document.getElementById('config-dropdown');
    const mainContainer = document.querySelector('.main-container');

    if (dropdown) {
      const isInitiallyOpen = dropdown.style.display === 'flex';

      // Function to toggle config bar
      function toggleDropdown(e) {
        if (e) e.stopPropagation();
        
        if (!isInitiallyOpen) {
          const isOpen = dropdown.style.display === 'flex';
          dropdown.style.display = isOpen ? 'none' : 'flex';
          
          // Update active state on mobile toggle
          if (toggleMobile) {
            if (isOpen) {
              toggleMobile.classList.remove('active');
              if (mainContainer) mainContainer.classList.remove('config-open');
            } else {
              toggleMobile.classList.add('active');
              if (mainContainer) mainContainer.classList.add('config-open');
            }
          }
        }
      }

      // Desktop toggle
      if (toggleDesktop) {
        toggleDesktop.addEventListener('click', toggleDropdown);
      }

      // Mobile toggle
      if (toggleMobile) {
        toggleMobile.addEventListener('click', toggleDropdown);
        
        // Set initial state for mobile
        if (isInitiallyOpen && mainContainer) {
          toggleMobile.classList.add('active');
          mainContainer.classList.add('config-open');
        }
      }

      // Close config bar when clicking outside (mobile only)
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
          const isClickInside = dropdown.contains(event.target) || 
                               (toggleMobile && toggleMobile.contains(event.target)) ||
                               (toggleDesktop && toggleDesktop.contains(event.target));
          
          if (!isClickInside && dropdown.style.display === 'flex' && !isInitiallyOpen) {
            dropdown.style.display = 'none';
            if (toggleMobile) {
              toggleMobile.classList.remove('active');
            }
            if (mainContainer) {
              mainContainer.classList.remove('config-open');
            }
          }
        }
      });
    }
  });
</script>