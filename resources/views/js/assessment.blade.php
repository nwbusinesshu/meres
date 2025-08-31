<script>
$(document).ready(function(){
  $('.value').click(function(){
    $(this).parents('.values').find('.value').removeClass('selected');
    $(this).addClass('selected');
    var counts = [];
    $(this).parents('.competency').find('.value.selected').each(function(){
      counts[$(this).attr('data-value')*1] = (counts[$(this).attr('data-value')*1] || 0) + 1;
    });
    counts.some(function(count){
      if(count > 3){
        swal_info.fire({title: '{{ __('assessment.warning-3') }}'});
        return true;
      }
      return false;
    });
  });

  $('.send-in').click(function(){
    swal_loader.fire();

    // checking if all of the questions are answered
    if($('.question').length != $('.value.selected').length){
      swal_warning.fire({title: '{{ __('assessment.warning-2') }}'});
      return;
    }

    // checking selected values?

    swal_confirm.fire({
      title: '{{ __('assessment.send-in-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        @if (session('uid') == $target->id)

        var questionsCount = $('.value.selected').length;
        var counts = [];
        $('.value.selected').each(function(){
          counts[$(this).attr('data-value')*1] = (counts[$(this).attr('data-value')*1] || 0) + 1;
        });
        if((0 in counts && counts[0] == questionsCount) || (7 in counts && counts[7] == questionsCount)){
          swal_warning.fire({title: '{{ __('assessment.warning-4') }}', didClose: function(){  window.scrollTo(0, 0); }});
          $('.value').removeClass('selected');
          return;
        }
        @endif

        var answers = [];
        $('.value.selected').each(function(){
          answers.push({
            value: $(this).attr('data-value'),
            questionId: $(this).parents('.question').attr('data-id')
          });
        });

        $.ajax({
          url: "{{ route('assessment.submit') }}",
          type: "POST", // <-- KÖTELEZŐ: nagy payload (telemetry_raw) miatt POST
          data: {
            target: $('.values').attr('data-target-id'),
            answers: answers
          },
          successUrl: "{{ route('home') }}",
          successMessage: "{{ __('assessment.send-in-success') }}",
        });
      }
    });
  });
});
</script>

<script>
function showTelemetryToast(){
  // SweetAlert2
  if (window.Swal && typeof Swal.fire === 'function') {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: 'Az oldalon telemetria fut',
      showConfirmButton: false,
      timer: 1800,
      timerProgressBar: true
    });
    return;
  }
  // Régi SweetAlert (swal v1)
  if (typeof window.swal === 'function') {
    swal({
      text: 'Az oldalon telemetria fut',
      icon: 'info',
      buttons: false,
      timer: 1800
    });
    return;
  }
  // Fallback
  // alert('Az oldalon telemetria fut');
}

(function($){
  // --- Beállítások / konstansok ---
  const SUBMIT_URL = "{{ route('assessment.submit') }}"; // az értékelés beküldés végpontja
  const submitPath = (()=>{
    try { return new URL(SUBMIT_URL, location.origin).pathname; } catch(e){ return SUBMIT_URL; }
  })();

  const STORAGE_PREFIX = "q360:telemetry:";
  const nowISO = () => new Date().toISOString();
  const tzOffsetMin = -new Date().getTimezoneOffset(); // pl. Budapest: -120 -> 120 nyáron
  const perfNow = () => (window.performance && performance.now) ? performance.now() : Date.now();

  // Egyszerű UUID (gyűjtés izolálásához)
  const uuid = () => 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c=>{
    const r = Math.random()*16|0, v = c==='x'?r:(r&0x3|0x8);
    return v.toString(16);
  });

  // Eszköz detekt (minimális, PII nélkül)
  const deviceType = ()=>{
    const ua = navigator.userAgent.toLowerCase();
    if (/mobile|iphone|android(?!.*tablet)/.test(ua)) return 'mobile';
    if (/ipad|tablet/.test(ua)) return 'tablet';
    return 'desktop';
  };

  // --- Állapot: minden oldalnyitásnál új mérés indul ---
  const measurementUUID = uuid();
  const SESSION_KEY = STORAGE_PREFIX + measurementUUID;

  // Kezdő időpontok
  const pageStartHR = perfNow();
  const wallStartISO = nowISO();

  // Láthatóság / fókusz mérők
  let visibleSinceHR = (document.visibilityState === 'visible') ? perfNow() : null;
  let activeSinceHR  = (document.hasFocus && document.hasFocus()) ? perfNow() : null;
  let totals = { total_ms: 0, visible_ms: 0, active_ms: 0 };

  // Interakció számlálók
  const interactions = { clicks:0, keydowns:0, scrolls:0, contextmenus:0, pastes:0 };
  const visibilityEvents = { hidden_count:0, visible_count: (document.visibilityState==='visible')?1:0 };
  const focusEvents = { focus_count: (document.hasFocus && document.hasFocus())?1:0, blur_count:0 };

  // Kérdések feltérképezése
  const $questions = $('.tile.tile-secondary.question[data-id]');
  const itemsCount = $questions.length;

  // display_order és item state előkészítés
  const displayOrder = [];
  const itemState = {}; // question_id -> state

  // Skála detekt és item kezdeti állapot
  $questions.each(function(i){
    const $q = $(this);
    const qid = parseInt($q.attr('data-id'), 10);
    displayOrder.push(qid);

    // Skála: a gyerek .value elemeken data-value attribútum
    const values = $q.find('.values .value[data-value]').map(function(){ return parseFloat($(this).attr('data-value')); }).get();
    const min = Math.min.apply(null, values);
    const max = Math.max.apply(null, values);
    // step becslés (legkisebb pozitív különbség)
    let step = 1;
    if (values.length > 1) {
      const sorted = [...new Set(values)].sort((a,b)=>a-b);
      const diffs = [];
      for (let j=1;j<sorted.length;j++) {
        const d = sorted[j]-sorted[j-1];
        if (d>0) diffs.push(d);
      }
      step = diffs.length? Math.min.apply(null, diffs) : 1;
    }

    itemState[qid] = {
      question_id: qid,
      index: i+1,
      scale: { min, max, step },
      first_seen_ms: null,
      first_interaction_ms: null,
      value_path: [],
      last_value: null,
      changes_count: 0,
      focus_ms: 0,
      attention_check: { present: false } // ha lesz ilyen logika, itt megjelenik
    };
  });

  // Görgetési lefedettség (mely indexig láttuk)
  const scrollSeen = { min_index: itemsCount?1:0, max_index: 0 };

  // IntersectionObserver: first_seen_ms és scrollSectionsSeen
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries)=>{
      const tNow = perfNow();
      entries.forEach(entry=>{
        if (!entry.isIntersecting) return;
        const $q = $(entry.target);
        const qid = parseInt($q.attr('data-id'),10);
        const st = itemState[qid];
        if (st && st.first_seen_ms === null) {
          st.first_seen_ms = Math.round(tNow - pageStartHR);
        }
        // max index frissítés
        if (st && typeof st.index === 'number') {
          if (scrollSeen.max_index < st.index) scrollSeen.max_index = st.index;
        }
      });
    }, { root: null, threshold: 0.4 });

    $questions.each(function(){ io.observe(this); });
  }

  // Egérmutató “fókusz” mérése kérdésblokkra
  $questions.on('mouseenter', function(){
    const qid = parseInt($(this).attr('data-id'),10);
    const st = itemState[qid];
    if (!st) return;
    $(this).data('hoverStartHR', perfNow());
  });
  $questions.on('mouseleave', function(){
    const qid = parseInt($(this).attr('data-id'),10);
    const st = itemState[qid];
    if (!st) return;
    const start = $(this).data('hoverStartHR');
    if (typeof start === 'number') {
      st.focus_ms += Math.max(0, Math.round(perfNow() - start));
      $(this).removeData('hoverStartHR');
    }
  });

  // Érték kiválasztás naplózása (delegálva)
  $(document).on('click', '.tile.tile-secondary.question .values .value[data-value]', function(){
    interactions.clicks++;
    const $v = $(this);
    const $q = $v.closest('.tile.tile-secondary.question');
    const qid = parseInt($q.attr('data-id'),10);
    const st = itemState[qid];
    if (!st) return;

    const tRel = Math.round(perfNow() - pageStartHR);
    const v = parseFloat($v.attr('data-value'));

    if (st.first_interaction_ms === null) st.first_interaction_ms = tRel;

    // változás számlálás
    if (st.last_value === null || st.last_value !== v) {
      if (st.last_value !== null) st.changes_count++;
      st.last_value = v;
    }
    // value_path gyűjtés (limit 20 / kérdés)
    if (st.value_path.length === 0 || st.value_path[st.value_path.length-1].v !== v) {
      if (st.value_path.length < 20) st.value_path.push({ ms: tRel, v: v });
    }
    save();
  });

  // Globális interakció számlálók (összesítve, tartalom NÉLKÜL)
  $(document).on('keydown', function(){ interactions.keydowns++; });
  // jQuery helyett passzív scroll listener:
  window.addEventListener('scroll', function(){ interactions.scrolls++; }, { passive: true });
  $(document).on('contextmenu', function(){ interactions.contextmenus++; });
  $(document).on('paste', function(){ interactions.pastes++; });

  // Page visibility / focus
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') {
      visibilityEvents.hidden_count++;
      if (visibleSinceHR !== null) {
        totals.visible_ms += Math.max(0, Math.round(perfNow() - visibleSinceHR));
        visibleSinceHR = null;
      }
    } else {
      visibilityEvents.visible_count++;
      if (visibleSinceHR === null) visibleSinceHR = perfNow();
    }
    save();
  });

  window.addEventListener('blur', function(){
    focusEvents.blur_count++;
    if (activeSinceHR !== null) {
      totals.active_ms += Math.max(0, Math.round(perfNow() - activeSinceHR));
      activeSinceHR = null;
    }
    save();
  });

  window.addEventListener('focus', function(){
    focusEvents.focus_count++;
    if (activeSinceHR === null) activeSinceHR = perfNow();
    save();
  });

  // BFCache visszatérés: kezeljük “új oldalnyitásként”
  window.addEventListener('pageshow', function(e){
    if (e.persisted) {
      // új session-t kezdünk (új UUID), a régi itt szándékosan nem kerül folytatásra
      sessionStorage.setItem(STORAGE_PREFIX + 'bfcache-note', nowISO());
    }
  });

  // --- Telemetria objektum összeállítása (nyers) ---
  function buildPayload(finalize=false){
    // időzítések lezárása
    const nowHR = perfNow();
    const total_ms = Math.max(0, Math.round(nowHR - pageStartHR));

    let visible_ms = totals.visible_ms;
    if (document.visibilityState === 'visible' && visibleSinceHR !== null) {
      visible_ms += Math.max(0, Math.round(nowHR - visibleSinceHR));
    }

    let active_ms = totals.active_ms;
    if ((document.hasFocus && document.hasFocus()) && activeSinceHR !== null) {
      active_ms += Math.max(0, Math.round(nowHR - activeSinceHR));
    }

    // items → tömb
    const itemsArr = displayOrder.map((qid)=> itemState[qid]);

    // device
    const device = {
      type: deviceType(),
      dpr: (window.devicePixelRatio || 1),
      viewport_w: Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0),
      viewport_h: Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0),
    };

    const payload = {
      measurement_uuid: measurementUUID,
      started_at: wallStartISO,
      finished_at: finalize ? nowISO() : null,
      tz_offset_min: tzOffsetMin,
      device: device,
      items_count: itemsCount,
      display_order: displayOrder,
      total_ms: total_ms,
      visible_ms: visible_ms,
      active_ms: active_ms,
      visibility_events: visibilityEvents,
      focus_events: focusEvents,
      interactions: interactions,
      scroll_sections_seen: { min_index: itemsCount?1:0, max_index: scrollSeen.max_index },
      items: itemsArr
      // server_context + features szerver oldalon kerülnek hozzá
    };

    return payload;
  }

  function save(finalize=false){
    const payload = buildPayload(finalize);
    try {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(payload));
    } catch(e){
      // ha betelt a storage, a telemetria nélkül folytatunk
      console.warn('Telemetry sessionStorage full/blocked', e);
    }
    return payload;
  }

  // Első mentés (indulás)
  const _initPayload = save(false);

  // Ellenőrizzük, hogy tényleg bekerült-e a sessionStorage-ba,
  // és csak akkor dobjuk fel a toastot. (1x per page load, per-mérés)
  let storageOK = false;
  try { storageOK = sessionStorage.getItem(SESSION_KEY) !== null; } catch(e){ storageOK = false; }

  const TOAST_SHOWN_KEY = SESSION_KEY + ':toast'; // <- per-mérésre kötve
  const toastAlreadyShown = sessionStorage.getItem(TOAST_SHOWN_KEY) === '1';

  // opcionális környezet kapcsoló — csak ha szeretnéd pl. stagingen:
  const toastEnabled = (window.Q360_TELEMETRY_TOAST !== false); // default: true, ha nincs beállítva

  if (toastEnabled && storageOK && !toastAlreadyShown) {
    showTelemetryToast();
    try { sessionStorage.setItem(TOAST_SHOWN_KEY, '1'); } catch(e){}
  }

  // --- AJAX előszűrő: a beküldéshez automatikusan hozzátesszük a telemetriát ---
  $.ajaxPrefilter(function(options, originalOptions, jqXHR){
    try {
      // Robusztus útvonal-ellenőrzés (abszolút/relatív URL-ekhez is jó)
      let currentPath;
      try { currentPath = new URL(options.url, location.origin).pathname; }
      catch(e) { currentPath = options.url; }

      const isSubmit = (submitPath === currentPath);
      if (!isSubmit) return;

      const payload = save(true); // lezárás a beküldés pillanatában

      // options.data lehet object vagy querystring – egységesítsük
      if (typeof options.data === 'string') {
        // querystring-hez fűzzük (POST body-nál is a jQuery majd a body-ba teszi)
        const sep = options.data.length ? '&' : '';
        options.data = options.data + sep + 'telemetry_raw=' + encodeURIComponent(JSON.stringify(payload));
      } else {
        // objektumként kezelhető
        options.data = $.extend(true, {}, options.data || {}, { telemetry_raw: JSON.stringify(payload) });
      }

      // Opcionális: fejléccímke a backend loghoz
      const origBeforeSend = options.beforeSend;
      options.beforeSend = function(xhr, settings){
        xhr.setRequestHeader('X-Q360-Telemetry', '1');
        if (typeof origBeforeSend === 'function') return origBeforeSend.call(this, xhr, settings);
      };

      // Siker után töröljük a sessionStorage-ból ezt a mérést
      const origSuccess = options.success;
      options.success = function(data, textStatus, jq){
        try { sessionStorage.removeItem(SESSION_KEY); } catch(e){}
        if (typeof origSuccess === 'function') return origSuccess.call(this, data, textStatus, jq);
      };
    } catch(e){
      console.warn('Telemetry inject error', e);
    }
  });

})(jQuery);
</script>