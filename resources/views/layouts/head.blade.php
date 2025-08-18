<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="user-scalable=0, initial-scale=1.0">
  
  <meta name="mobile-web-app-capable" content="yes">
	<meta name="theme-color" content="#2B7A78" />
	<meta name="csrf-token" content="{{ csrf_token() }}">
 
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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
	<script src="https://cdn.jsdelivr.net/gh/tomik23/circular-progress-bar@1.1.9/dist/circularProgressBar.min.js"></script>

	
	<!-- SweetAlert-->
	<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css">
	<script src="//cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.js"></script>

	<!-- Global css -->
	<link rel="stylesheet" href="{{ asset('assets/css/global.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/global_media.css') }}">

	<!-- Custom css per page -->
	<link rel="stylesheet" href="{{ assets("css/pages/{$currentViewName}.css") }}">

	<!-- Global custom js -->
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
			loaderHtml: '<img src="{{ assets('loader/loader.svg') }}" width="50"/>',
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
			@if(session('error'))
				swal_error.fire({html: '{!! session('error') !!}'});
			@endif

			@if(session('warning'))
				swal_warning.fire({html: '{!! session('warning') !!}'});
			@endif

			@if(session('success'))
				swal_success.fire({html: '{!! session('success') !!}'});
			@endif

			@if(session('info'))
				swal_info.fire({html: '{!! session('info') !!}'});
			@endif
		});

		const swal_loader = Swal.mixin({
			allowOutsideClick: false,
			allowEscapeKey: false,
			allowEnterKey: false,
			showConfirmButton: false,
			imageUrl: '{{ assets("loader/loader.svg") }}',
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
			beforeSend: function(xhr, settings) {
				if(settings.url.includes('{{ url('/') }}')){
					xhr.setRequestHeader("X-CSRF-TOKEN", $('meta[name="csrf-token"]').attr('content'));
				}
			},
			accepts: {
        text: "application/json"
    	},
			method: "POST",
			complete: function(xhr, status){
				if(status != 'success'){
					if(xhr.status == 419){
						swal_loader.fire();
						location.reload();
						return;
					}
					if(xhr.status == 422){
						var errors = Object.values(xhr.responseJSON.errors);
						swal_error.fire({html: [].concat.apply([], errors).join('<br>')});
						return;
					}
					swal_error.fire({text: '{{ __('global.connection-fail') }}'});
					@if(config('app.debug'))
					console.log(xhr.responseJSON);
					@endif
				}else if(typeof this.successMessage != 'undefined'){
					swal_success.fire({text: this.successMessage}).then((result) => {
						swal_loader.fire();
						if(typeof this.successUrl != 'undefined'){
							location.href = this.successUrl;
						}else{
							location.reload();
						}
					});
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
  </script>

	@yield('head-extra')

	<title>{{ __("titles.$currentViewName") }}</title>
</head>