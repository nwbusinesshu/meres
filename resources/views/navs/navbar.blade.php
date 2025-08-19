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
  @php
    use Illuminate\Support\Facades\Auth;
    use App\Models\Enums\UserType;

    $user = Auth::user();
    $orgId = session('org_id');
    $organizations = collect();
    $currentOrg = null;

    if ($user) {
        if ($user->type === UserType::SUPERADMIN) {
            $organizations = \App\Models\Organization::all();
        } elseif ($user->type === UserType::ADMIN) {
            $organizations = $user->organizations()->wherePivot('role', 'admin')->get();
        } else {
            $organizations = $user->organizations;
        }

        $currentOrg = $organizations->firstWhere('id', $orgId);
    }
  @endphp

  @if ($currentOrg)
    <div class="org-label" style="margin-right: 10px;">
      <i class="fa fa-building"></i>
      <strong>{{ $currentOrg->name }}</strong>
    </div>
  @endif

  @if ($organizations->count() > 1)
    <form class="org-switch" method="POST" action="{{ route('org.switch') }}" style="margin-right: 10px;">
      @csrf
      <select name="org_id" onchange="this.form.submit()">
        @foreach ($organizations as $org)
          <option value="{{ $org->id }}" @if($org->id == $orgId) selected @endif>
            {{ $org->name }}
          </option>
        @endforeach
      </select>
    </form>
  @endif

  <span>{{ session('uname') }}</span>
  <a href="{{ route('logout') }}">
    <i class="fa fa-right-from-bracket" data-tippy-content="{{ __('titles.logout') }}"></i>
  </a>
  <img src="{{ session('uavatar') }}" alt="avatar">
</div>

</div>

<pre>
org_id: {{ session('org_id') }}
user: {{ Auth::user()->id ?? 'nincs user' }}
orgs: {{ json_encode($organizations->pluck('name', 'id')) }}
</pre>
