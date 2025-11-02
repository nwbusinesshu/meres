<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="user-scalable=0, initial-scale=1.0">
  
  <meta name="mobile-web-app-capable" content="yes">
	<meta name="theme-color" content="#2B7A78" />
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="app-view-key" content="{{ $view_key ?? $currentViewName ?? '' }}">
	<meta name="app-user-role" content="{{ session('org_role') ?? session('utype') ?? 'guest' }}">
	<meta name="app-locale" content="{{ app()->getLocale() }}">
	<meta name="app-first-login" content="{{ session('first_login', false) ? 'true' : 'false' }}">
	<meta name="needs-privacy-acknowledgment" content="{{ MyAuth::isAuthorized(UserType::NORMAL) && Auth::user() && is_null(Auth::user()->privacy_policy_accepted_at) ? 'true' : 'false' }}">
 
  <!-- Favicon -->
	<link rel="icon" type="image/png" href="{{ assets('favicon.png') }}">

  <!-- JQuery -->
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="crossorigin="anonymous"></script>
  
  <!-- JQuery cookie -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>

  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

  <!-- Fontawesome -->
	<script src="https://kit.fontawesome.com/05d4c449bb.js" crossorigin="anonymous"></script>
    
  <!-- hammerjs, momentjs + hu locale, chartjs + zoom plugin, popper.js, tippy.js, scrollTo jquery -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.7/hammer.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/hu.min.js" charset="UTF-8"></script>
  <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
	<script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>
	<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/shift-away-extreme.css">
	<script src="https://cdn.jsdelivr.net/npm/jquery.scrollto@2.1.3/jquery.scrollTo.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
	<script src="https://cdn.jsdelivr.net/gh/tomik23/circular-progress-bar@1.1.9/dist/circularProgressBar.min.js"></script>
	<script src="{{ asset('assets/js/navbar-scroll.js') }}"></script>

	<!-- Microsoft Clarity-->
	<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "tucyg99bee");
</script>

	
	<!-- SweetAlert-->
	<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css">
	<script src="//cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.js"></script>

	<!-- Global css -->
	<link rel="stylesheet" href="{{ asset('assets/css/global.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/global_media.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/pages/help-modal.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/components/profile-settings.css') }}">


	<!-- Custom css per page -->
	<link rel="stylesheet" href="{{ assets("css/pages/{$currentViewName}.css") }}">

	<!-- Global custom js -->
	<script>
  /* --- Globális toast helper --- */
  window.toast = function(type, message, opts = {}) {
    Swal.fire(Object.assign({
      toast: true,
      position: 'bottom', // vagy 'bottom-end'
      icon: type,
      title: message,
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
      didOpen: (toastEl) => {
        toastEl.addEventListener('mouseenter', Swal.stopTimer);
        toastEl.addEventListener('mouseleave', Swal.resumeTimer);
      }
    }, opts));
  };
</script>

	<script>
		/* Constants */
		const LANG_CODE = navigator.language.split("-")[0];

		const COLOR_SUCCESS = '#28a745';
		const COLOR_INFO = '#17a2b8';
		const COLOR_DANGER = '#dc3545';
		const COLOR_SECONDARY = '#6c757d';
		const COLOR_DARK = '#343a40';
		const COLOR_WARNING = '#ffc107';
		const COLOR_PRIMARY = '#007bff';

		/* SweetAlert 2 defaults */
		const swal = Swal.mixin({
			confirmButtonText: '{{ __('global.swal-confirm') }}',
			cancelButtonText: '{{ __('global.swal-cancel') }}',
			denyButtonText: '{{ __('global.swal-deny') }}',
			confirmButtonColor: COLOR_SUCCESS,
			cancelButtonColor: COLOR_SECONDARY,
			denyButtonColor: COLOR_DANGER,
			reverseButtons: true,
			inputAttributes: {
				required: true
			},
			validationMessage: '{{ __('global.swal-validation-message') }}',
			showCloseButton: false,
			scrollbarPadding: false,
			customClass: {
				input: 'form-control',
				loader: 'swal2-custom-loader',
			},
			loaderHtml: '<img src="{{ asset('assets/loader/loader.svg') }}" width="50"/>',
			heightAuto: false,
			allowEnterKey: true,
			keydownListenerCapture: true,
  	});

		const swal_locked = swal.mixin({
			showCloseButton: false,
			allowOutsideClick: false,
			allowEscapeKey: false,
			allowEnterKey: false,
			showCancelButton: true,
		});

		const swal_success = swal.mixin({
			showCancelButton: false,
			showDenyButton: false,
			confirmButtonColor: COLOR_SUCCESS,
			icon: 'success',
			title: '{{ __('global.swal-success') }}',
			confirmButtonText: '{{ __('global.swal-ok') }}',
		});

		const swal_info = swal.mixin({
			showCancelButton: false,
			showDenyButton: false,
			confirmButtonColor: COLOR_INFO,
			icon: 'info',
			title: '{{ __('global.swal-info') }}',
			confirmButtonText: '{{ __('global.swal-ok') }}',
		});

		const swal_error = swal.mixin({
			showCancelButton: false,
			showDenyButton: false,
			confirmButtonColor: COLOR_DANGER,
			icon: 'error',
			title: '{{ __('global.swal-error') }}',
			confirmButtonText: '{{ __('global.swal-ok') }}',
		});

		const swal_warning = swal.mixin({
			showCancelButton: false,
			showDenyButton: false,
			confirmButtonColor: COLOR_WARNING,
			icon: 'warning',
			title: '{{ __('global.swal-warning') }}',
			confirmButtonText: '{{ __('global.swal-ok') }}',
		});

		/* SweetAlert 2 session handler */
		$(document).ready(function(){
		  // Toast stílus csak success és info üzenetekhez
		  const toast = (type, message) => {
		    Swal.fire({
		      toast: true,
		      position: 'bottom',
		      icon: type,
		      title: message,
		      showConfirmButton: false,
		      timer: 4000,
		      timerProgressBar: true,
		      didOpen: (toast) => {
		        toast.addEventListener('mouseenter', Swal.stopTimer);
		        toast.addEventListener('mouseleave', Swal.resumeTimer);
		      }
		    });
		  };

		  @if(session('success'))
		    toast('success', `{!! session('success') !!}`);
		  @endif

		  @if(session('info'))
		    toast('info', `{!! session('info') !!}`);
		  @endif

		  @if(session('warning'))
		    swal_warning.fire({html: `{!! session('warning') !!}`});
		  @endif

		  @if(session('error'))
		    swal_error.fire({html: `{!! session('error') !!}`});
		  @endif
		});


		const swal_loader = Swal.mixin({
			allowOutsideClick: false,
			allowEscapeKey: false,
			allowEnterKey: false,
			showConfirmButton: false,
			imageUrl: '{{ asset("assets/loader/loader.svg") }}',
			imageWidth: 125,
			imageHeight: 125,
			imageAlt: 'loader',
			background: 'transparent',
			customClass: {
				'popup' : 'swal2-loader-popup',
				'title' : 'swal2-loader-title',
				'htmlContainer' : 'swal2-loader-html',
			},
			heightAuto: false,
			scrollbarPadding: false
		});

		const swal_confirm = swal_locked.mixin({
			showConfirmButton: true,
			showCancelButton: true,
			showDenyButton: false,
			icon: 'question',
			confirmButtonText: '{{ __('global.swal-confirm') }}',
			cancelButtonText: '{{ __('global.swal-cancel') }}',
			confirmButtonColor: COLOR_SUCCESS,
			cancelButtonColor: COLOR_SECONDARY,
		});

		/* Moment js locale */
		moment().locale(LANG_CODE);

	/* Ajax setup */
$.ajaxSetup({
  beforeSend: function (xhr, settings) {
    if (settings.url.includes('{{ url('/') }}')) {
      xhr.setRequestHeader(
        "X-CSRF-TOKEN",
        $('meta[name="csrf-token"]').attr('content')
      );
    }
  },
  accepts: {
    text: "application/json"
  },
  method: "POST",
  complete: function (xhr, status) {
    // Check for custom flag to skip global error handling
    if (this.skipGlobalErrorHandler === true) {
      return; // Skip global error handling for this request
    }
    
    if (status !== 'success') {
      // ---- ERROR HANDLING ----
      
      // 419: CSRF Token Expired - automatically reload
      if (xhr.status == 419) {
        swal_loader.fire();
        location.reload();
        return;
      }
      
      // 422: Validation Errors - show all validation messages
      if (xhr.status == 422) {
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          var errors = Object.values(xhr.responseJSON.errors);
          swal_error.fire({ html: [].concat.apply([], errors).join('<br>') });
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
          // Sometimes 422 returns a single message instead of errors array
          swal_error.fire({ html: xhr.responseJSON.message });
        } else {
          swal_error.fire({ text: '{{ __('global.validation-failed') }}' });
        }
        return;
      }
      
      // IMPROVED: Try to extract specific error message from server
      let errorMessage = null;
      
      // Check if server sent a specific message
      if (xhr.responseJSON && xhr.responseJSON.message) {
        errorMessage = xhr.responseJSON.message;
      } 
      // Check for error property (some APIs use this)
      else if (xhr.responseJSON && xhr.responseJSON.error) {
        errorMessage = xhr.responseJSON.error;
      }
      // Status-specific fallback messages
      else {
        switch (xhr.status) {
          case 400:
            errorMessage = '{{ __('global.bad-request') }}';
            break;
          case 401:
            errorMessage = '{{ __('global.unauthorized') }}';
            break;
          case 403:
            errorMessage = '{{ __('global.forbidden') }}';
            break;
          case 404:
            errorMessage = '{{ __('global.not-found') }}';
            break;
          case 409:
            errorMessage = '{{ __('global.conflict') }}';
            break;
          case 429:
            errorMessage = '{{ __('global.too-many-requests') }}';
            break;
          case 500:
            errorMessage = '{{ __('global.server-error') }}';
            break;
          case 503:
            errorMessage = '{{ __('global.service-unavailable') }}';
            break;
          default:
            errorMessage = '{{ __('global.connection-fail') }}';
        }
      }
      
      // Show the error message
      swal_error.fire({ html: errorMessage });
      
      // Debug logging in development mode
      @if(config('app.debug'))
        console.group('AJAX Error Details');
        console.log('Status:', xhr.status);
        console.log('Status Text:', xhr.statusText);
        console.log('Response:', xhr.responseJSON);
        console.log('Message Used:', errorMessage);
        console.groupEnd();
      @endif
      
      return;
    }

    // ---- SUCCESS HANDLING ----
    if (typeof this.successMessage !== 'undefined') {
      const wantsModal = this.useModal === true;

      if (wantsModal) {
        // Old modal with button
        swal_success.fire({ text: this.successMessage }).then(() => {
          swal_loader.fire();
          if (typeof this.successUrl !== 'undefined') {
            location.href = this.successUrl;
          } else {
            location.reload();
          }
        });
      } else {
        // NEW: redirect/reload immediately, toast appears on NEXT page (session flash)
        const go = () => {
          if (typeof this.successUrl !== 'undefined') {
            location.href = this.successUrl;
          } else {
            location.reload();
          }
        };

        $.post("{{ route('flash.success') }}", {
          message: this.successMessage,
          _token: $('meta[name="csrf-token"]').attr('content')
        }).always(go);
      }
    }
  }
});

		/* preventing double form submit */
		$(document).delegate('form', 'submit', function(){
			$(this).find(':submit').attr('disabled','disabled');
      $(this).find(':submit').fadeTo( "fast" , 0);
			swal_loader.fire();
		});

		/* show loader before links */
		$(document).delegate('a:not(.phpdebugbar > a):not(.no-loader)', 'click', function(event){
			event.preventDefault();

			var location = $(this).attr('href');
			if(location != "#"){
				swal_loader.fire();
      	window.location = location;
			}
			
		});

		/* Tippy tooltip default */
		tippy.setDefaultProps({
			animation: 'shift-away-extreme',
			trigger: "mouseenter"
		});
		$(document).ready(function(){
			tippy("[data-tippy-content]");
		});

		
		/* Print warning info to users not to interfere with console */
		@if(!config('app.debug'))
			console.log("%c{{ __('global.user-console-warning') }}", "color:red; font-size:20px");
		@endif

		/* Copy input mechanism */
		$(document).ready(function(){
			$('.copy-input').click(function(){
				copyToClipboard($(this).val());
				swal_info.fire({title: "{{ __('global.text-copied') }}"});
    	});
		});

		function canProceed(selector = null){
			var can = true;
			$(selector ?? ".form-control:not(.optional)").each(function(){
				if(isEmpty($(this))){
					$(this).css('border-color', 'var(--danger)').css('border-width', '3px');
					swal_warning.fire({title: "{{ __('global.all-fields-required') }}"});
					can = false;
				}else{
					$(this).css('border-color', '').css('border-width', '');
				}
			});
			return can;
		}

		// handling currently active menu in navbar by route name
		$(document).ready(function(){
			$('.navbar a.menuitem[data-route="{{ Route::currentRouteName() }}"]').addClass("active");
			$('.navbar a.menuitem[data-route-secondary="{{ Route::currentRouteName() }}"]').addClass("active");
		});

		window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        setTimeout(function() {
            Swal.close();
        }, 300); // Vár 300 milliszekundumot, majd bezárja a modált.
    }
});

		

  </script>


	@yield('head-extra')

	<title>{{ __("titles.$currentViewName") }}</title>
</head>