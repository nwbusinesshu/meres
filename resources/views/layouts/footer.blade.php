<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-text">© {{ date('Y') }} NW Business</span>
    <div class="footer-links">
      <a href="#">{{ __('global.footer-imprint') }}</a>
      <a href="#">{{ __('global.footer-data-handling') }}</a>
      <a href="#">{{ __('global.footer-contact') }}</a>
      <a href="#" id="footer-cookie-settings" title="{{ __('global.footer-cookie-settings') }}">
        <i class="fa fa-cookie-bite"></i> {{ __('global.footer-cookie-settings') }}
      </a>
    </div>
{{-- NYELVVÁLASZTÓ --}}
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

{{-- KIS JS: change-re elküldi a formot --}}
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
</footer>