<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-text">© {{ date('Y') }} NW Business</span>
    <div class="footer-links">
      <a href="#">{{ __('global.footer-imprint') }}</a>
      <a href="#">{{ __('global.footer-data-handling') }}</a>
      <a href="#">{{ __('global.footer-contact') }}</a>
      <a href="{{ route('status.index') }}" title="System Status"><i class="bi bi-activity"></i>{{ __('global.footer-system-status') }}</a>
      <a href="#" id="footer-cookie-settings" title="{{ __('global.footer-cookie-settings') }}">
        <i class="fa fa-cookie-bite"></i> {{ __('global.footer-cookie-settings') }}
      </a>
    </div>

    {{-- THEME TOGGLE BUTTON - NEEDS REFINEMENT  --}}
    {{-- <button id="theme-toggle-btn" class="footer-theme-toggle" title="{{ __('global.theme-toggle-title') }}" aria-label="{{ __('global.theme-toggle-aria') }}">
      <i class="fa fa-sun theme-icon-light"></i>
      <i class="fa fa-moon theme-icon-dark"></i>
    </button> --}}

    {{-- NYELVVÁLASZTÓ - REMOVED FOR TEST RUN --}}
    <form method="POST" action="{{ route('locale.set') }}" id="footer-locale-form" class="footer-lang">
        @csrf
        <input type="hidden" name="redirect" value="{{ url()->current() }}">
        <select name="locale" id="footer-locale" class="footer-lang__select">
            @foreach(config('app.available_locales') as $code => $label)
                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </form>
  </div>

  {{-- LANGUAGE SELECTOR JS --}}
  <script>
    (function(){
      var sel = document.getElementById('footer-locale');
      if(!sel) return;
      sel.addEventListener('change', function(){
        var f = document.getElementById('footer-locale-form');
        if (f) f.submit();
      });
    })();
  </script>

  {{-- THEME TOGGLE JS --}}
  {{--<script>
    (function() {
      const themeToggleBtn = document.getElementById('theme-toggle-btn');
      const htmlElement = document.documentElement;
      
      // Function to set theme
      function setTheme(theme) {
        if (theme === 'dark') {
          htmlElement.setAttribute('data-theme', 'dark');
          themeToggleBtn.classList.add('dark-mode');
        } else {
          htmlElement.removeAttribute('data-theme');
          themeToggleBtn.classList.remove('dark-mode');
        }
        // Save preference to localStorage
        localStorage.setItem('theme', theme);
      }
      
      // Load saved theme on page load
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme) {
        setTheme(savedTheme);
      }
      
      // Toggle theme on button click
      if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function(e) {
          e.preventDefault();
          const currentTheme = htmlElement.getAttribute('data-theme');
          const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
          setTheme(newTheme);
        });
      }
    })();
  </script>--}}
</footer>