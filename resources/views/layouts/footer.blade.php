<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-text">© {{ date('Y') }} NW Business</span>
    <div class="footer-links">
      <a href="#">Impresszum</a>
      <a href="#">Adatkezelés</a>
      <a href="#">Kapcsolat</a>
    </div>
  {{-- NYELVVÁLASZTÓ --}}
    <form method="POST" action="{{ route('locale.set') }}" id="footer-locale-form" class="footer-lang">
      @csrf
      <input type="hidden" name="redirect" value="{{ url()->current() }}">
      <select name="locale" id="footer-locale" class="footer-lang__select">
        <option value="hu" @selected(app()->getLocale()==='hu')>HU</option>
        <option value="en" @selected(app()->getLocale()==='en')>EN</option>
      </select>
    </form>
  </div>
  </div>

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