{{-- Profile Settings Modal --}}
<div id="profile-settings-modal" class="modal fade modal-drawer" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content profile-settings-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('privacy.profile.settings_title') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        {{-- User Info Section --}}
        <div class="profile-info-section">
          <h6 id="profile-user-name"></h6>
          <p id="profile-user-email" class="text-muted"></p>
        </div>

        {{-- Profile Picture Modern Carousel --}}
        <div class="profile-pic-section">
          <h6 class="section-title">{{ __('privacy.profile.profile_picture') }}</h6>
          
          {{-- Modern 3D Carousel Container --}}
          <div class="modern-carousel-container">
            
            {{-- Navigation Buttons - RENAMED CLASSES TO AVOID CONFLICTS --}}
            <button class="profile-carousel-btn profile-carousel-btn-left" id="carousel-prev" aria-label="Previous">
              <i class="fa fa-chevron-left"></i>
            </button>
            
            {{-- Carousel Viewport with 3D perspective --}}
            <div class="carousel-3d-viewport" id="carousel-viewport">
              <div class="carousel-3d-track" id="carousel-track">
                {{-- Dynamically populated by JS --}}
              </div>
              
              {{-- Touch Indicator (mobile only) --}}
              <div class="swipe-indicator">
                <i class="fa fa-hand-pointer"></i>
                <span>{{ __('privacy.profile.swipe_instruction') }}</span>
              </div>
            </div>
            
            <button class="profile-carousel-btn profile-carousel-btn-right" id="carousel-next" aria-label="Next">
              <i class="fa fa-chevron-right"></i>
            </button>
            
          </div>
          
          {{-- Dot Indicators --}}
          <div class="carousel-dots-container" id="carousel-dots-container">
            {{-- Dynamically populated by JS --}}
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('privacy.profile.cancel') }}</button>
        <button type="button" class="btn btn-primary" id="save-profile-pic">
          <i class="fa fa-save"></i> {{ __('privacy.profile.save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
/**
 * Profile Settings Modal JavaScript
 * Modern 3D carousel with touch support for profile picture selection
 */

(function() {
    'use strict';

    let availableOptions = [];
    let currentIndex = 0;
    let isTransitioning = false;
    let touchStartX = 0;
    let touchEndX = 0;
    let isDragging = false;
    let dragStartX = 0;
    let dragCurrentX = 0;
    let currentTranslateX = 0;

    // Initialize when DOM is ready
    $(document).ready(function() {
        initProfileSettingsModal();
    });

    function initProfileSettingsModal() {
        // Make avatar and username clickable in navbar
        $('.navbar .userinfo img.avatar, .navbar .userinfo i.fallback-avatar, .navbar .userinfo .userinfo-name').on('click', function(e) {
            e.preventDefault();
            openProfileSettingsModal();
        });

        // Carousel navigation
        $('#carousel-prev').on('click', function() {
            if (!isTransitioning) {
                navigateCarousel('prev');
            }
        });

        $('#carousel-next').on('click', function() {
            if (!isTransitioning) {
                navigateCarousel('next');
            }
        });

        // Keyboard navigation
        $('#profile-settings-modal').on('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                navigateCarousel('prev');
            } else if (e.key === 'ArrowRight') {
                navigateCarousel('next');
            }
        });

        // Save button
        $('#save-profile-pic').on('click', function() {
            saveProfilePicture();
        });

        // Handle carousel item clicks (event delegation)
        $('#carousel-track').on('click', '.carousel-3d-item', function(e) {
            if (!isDragging && !isTransitioning) {
                const index = $(this).data('index');
                if (index !== currentIndex) {
                    animateToIndex(index);
                }
            }
        });

        // Handle dot indicator clicks
        $('#carousel-dots-container').on('click', '.indicator-dot', function() {
            if (!isTransitioning) {
                const index = $(this).data('index');
                animateToIndex(index);
            }
        });

        // Touch events for mobile
        let viewport = document.getElementById('carousel-viewport');
        if (viewport) {
            // Touch events
            viewport.addEventListener('touchstart', handleTouchStart, { passive: true });
            viewport.addEventListener('touchmove', handleTouchMove, { passive: false });
            viewport.addEventListener('touchend', handleTouchEnd);
            
            // Mouse events for desktop drag
            viewport.addEventListener('mousedown', handleMouseDown);
            viewport.addEventListener('mousemove', handleMouseMove);
            viewport.addEventListener('mouseup', handleMouseUp);
            viewport.addEventListener('mouseleave', handleMouseUp);
        }

        // Handle modal cleanup
        $('#profile-settings-modal').on('hidden.bs.modal', function() {
            // Reset carousel state
            isTransitioning = false;
            isDragging = false;
            
            // Reset body styles if no other modals are open
            setTimeout(function() {
                if ($('.modal.show').length === 0) {
                    $('body').css({
                        'padding-right': '',
                        'overflow': ''
                    });
                }
            }, 50);
        });

        // Show modal event - hide swipe indicator after delay
        $('#profile-settings-modal').on('shown.bs.modal', function() {
            if (isTouchDevice()) {
                setTimeout(function() {
                    $('.swipe-indicator').fadeOut(500);
                }, 3000);
            }
        });
    }

    function openProfileSettingsModal() {
        // Show loading state
        swal_loader.fire();
        
        // Load profile data
        $.ajax({
            url: '/profile-settings/data',
            method: 'GET',
            success: function(response) {
                swal_loader.close();
                
                if (response.success) {
                    availableOptions = response.available_options;
                    currentIndex = response.current_index;
                    
                    // Populate modal
                    $('#profile-user-name').text(response.user.name);
                    $('#profile-user-email').text(response.user.email);
                    
                    // Build carousel
                    buildCarousel();
                    
                    // Update carousel position
                    updateCarouselPosition(false);
                    
                    // Show modal
                    $('#profile-settings-modal').modal('show');
                }
            },
            error: function(xhr) {
                swal_loader.close();
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("privacy.profile.error_title") }}',
                    text: '{{ __("privacy.profile.error_load_data") }}',
                    confirmButtonText: '{{ __("privacy.profile.ok") }}'
                });
            }
        });
    }

    function buildCarousel() {
        const $track = $('#carousel-track');
        const $indicators = $('#carousel-dots-container');
        $track.empty();
        $indicators.empty();

        availableOptions.forEach((option, index) => {
            // Create carousel item
            const $item = $('<div>')
                .addClass('carousel-3d-item')
                .attr('data-index', index)
                .attr('data-type', option.type)
                .attr('data-color', option.color || '');
            
            const $imgContainer = $('<div>').addClass('carousel-item-content');
            $imgContainer.html(`<img src="${option.url}" alt="Profile Picture Option" draggable="false">`);
            $item.append($imgContainer);

            if (index === currentIndex) {
                $item.addClass('active');
            }

            $track.append($item);

            // Create dot indicator
            const $dot = $('<span>')
                .addClass('indicator-dot')
                .attr('data-index', index);
            
            if (index === currentIndex) {
                $dot.addClass('active');
            }

            $indicators.append($dot);
        });

        updateNavigationButtons();
    }

    function navigateCarousel(direction) {
        if (isTransitioning) return;

        if (direction === 'prev' && currentIndex > 0) {
            animateToIndex(currentIndex - 1);
        } else if (direction === 'next' && currentIndex < availableOptions.length - 1) {
            animateToIndex(currentIndex + 1);
        }
    }

    function animateToIndex(targetIndex) {
        if (targetIndex < 0 || targetIndex >= availableOptions.length || targetIndex === currentIndex) {
            return;
        }

        isTransitioning = true;
        currentIndex = targetIndex;
        
        // Update active classes
        $('.carousel-3d-item').removeClass('active');
        $(`.carousel-3d-item[data-index="${currentIndex}"]`).addClass('active');
        
        // Update dot indicators
        $('.indicator-dot').removeClass('active');
        $(`.indicator-dot[data-index="${currentIndex}"]`).addClass('active');
        
        // Update carousel position
        updateCarouselPosition(true);
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Allow next transition after animation completes
        setTimeout(function() {
            isTransitioning = false;
        }, 500);
    }

    function updateCarouselPosition(animate) {
        const $items = $('.carousel-3d-item');
        const totalItemWidth = 110; // Width + spacing
        
        $items.each(function() {
            const $item = $(this);
            const itemIndex = parseInt($item.data('index'));
            const offset = itemIndex - currentIndex;
            const absOffset = Math.abs(offset);
            
            // Calculate position
            const translateX = offset * totalItemWidth;
            const translateZ = -absOffset * 60;  // Reduced depth for compact design
            const rotateY = offset * -12;  // Slightly reduced rotation
            const scale = 1 - (absOffset * 0.2);
            const opacity = 1 - (absOffset * 0.3);

            // Apply transforms
            if (animate) {
                $item.css({
                    'transform': `translateX(${translateX}px) translateZ(${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`,
                    'opacity': Math.max(opacity, 0.3),
                    'transition': 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)',
                    'z-index': 100 - absOffset
                });
            } else {
                $item.css({
                    'transform': `translateX(${translateX}px) translateZ(${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`,
                    'opacity': Math.max(opacity, 0.3),
                    'z-index': 100 - absOffset
                });
            }
        });
    }

    function updateNavigationButtons() {
        $('#carousel-prev').prop('disabled', currentIndex === 0);
        $('#carousel-next').prop('disabled', currentIndex === availableOptions.length - 1);
    }

    // Touch handling functions
    function handleTouchStart(e) {
        touchStartX = e.touches[0].clientX;
        dragStartX = touchStartX;
        isDragging = true;
    }

    function handleTouchMove(e) {
        if (!isDragging) return;
        
        e.preventDefault();
        touchEndX = e.touches[0].clientX;
        dragCurrentX = touchEndX;
        
        const diffX = dragCurrentX - dragStartX;
        const threshold = 50;
        
        // Add visual feedback during drag
        if (Math.abs(diffX) > 10) {
            const $track = $('#carousel-track');
            const dragScale = Math.min(Math.abs(diffX) / threshold, 1);
            const resistance = 0.3;
            $track.css({
                'transform': `translateX(${diffX * resistance}px)`,
                'transition': 'none'
            });
        }
    }

    function handleTouchEnd(e) {
        if (!isDragging) return;
        
        const swipeThreshold = 50;
        const diff = touchEndX - touchStartX;
        
        // Reset visual feedback
        $('#carousel-track').css({
            'transform': '',
            'transition': ''
        });

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0 && currentIndex > 0) {
                navigateCarousel('prev');
            } else if (diff < 0 && currentIndex < availableOptions.length - 1) {
                navigateCarousel('next');
            }
        }
        
        isDragging = false;
        touchStartX = 0;
        touchEndX = 0;
    }

    // Mouse handling functions for desktop drag
    function handleMouseDown(e) {
        dragStartX = e.clientX;
        isDragging = true;
        e.preventDefault();
        
        // Change cursor
        $(this).css('cursor', 'grabbing');
    }

    function handleMouseMove(e) {
        if (!isDragging) return;
        
        e.preventDefault();
        dragCurrentX = e.clientX;
        
        const diffX = dragCurrentX - dragStartX;
        const threshold = 50;
        
        // Add visual feedback during drag
        if (Math.abs(diffX) > 10) {
            const $track = $('#carousel-track');
            const dragScale = Math.min(Math.abs(diffX) / threshold, 1);
            const resistance = 0.3;
            $track.css({
                'transform': `translateX(${diffX * resistance}px)`,
                'transition': 'none'
            });
        }
    }

    function handleMouseUp(e) {
        if (!isDragging) return;
        
        const swipeThreshold = 50;
        const diff = dragCurrentX - dragStartX;
        
        // Reset visual feedback
        $('#carousel-track').css({
            'transform': '',
            'transition': ''
        });
        
        // Change cursor back
        $('#carousel-viewport').css('cursor', 'grab');

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0 && currentIndex > 0) {
                navigateCarousel('prev');
            } else if (diff < 0 && currentIndex < availableOptions.length - 1) {
                navigateCarousel('next');
            }
        }
        
        isDragging = false;
        dragStartX = 0;
        dragCurrentX = 0;
    }

    function isTouchDevice() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }

    function saveProfilePicture() {
        const selectedOption = availableOptions[currentIndex];
        
        // Show loading
        const $saveBtn = $('#save-profile-pic');
        const originalHtml = $saveBtn.html();
        $saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("privacy.profile.saving") }}');
        
        $.ajax({
            url: '/profile-settings/update-picture',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                type: selectedOption.type,
                color: selectedOption.color
            },
            success: function(response) {
                $saveBtn.prop('disabled', false).html(originalHtml);
                
                if (response.success) {
                    // Update navbar avatar with cache-busting
                    const newAvatarUrl = response.new_avatar_url + '?t=' + Date.now();
                    const $navbar = $('.navbar .userinfo');
                    
                    // Remove fallback icon if exists and replace with img
                    $navbar.find('i.fallback-avatar').remove();
                    
                    if ($navbar.find('img.avatar').length > 0) {
                        // Update existing img
                        $navbar.find('img.avatar').attr('src', newAvatarUrl);
                    } else {
                        // Create new img element
                        const $newImg = $('<img>')
                            .addClass('avatar')
                            .attr('src', newAvatarUrl)
                            .attr('alt', 'avatar')
                            .css('cursor', 'pointer');
                        $navbar.prepend($newImg);
                    }
                    
                    // Close modal
                    $('#profile-settings-modal').modal('hide');
                    
                    // Show success toast at bottom-center
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'success',
                        title: '{{ __("privacy.profile.success_picture_updated") }}',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("privacy.profile.error_title") }}',
                        text: response.error || '{{ __("privacy.profile.error_update_picture") }}',
                        confirmButtonText: '{{ __("privacy.profile.ok") }}'
                    });
                }
            },
            error: function(xhr) {
                $saveBtn.prop('disabled', false).html(originalHtml);
                
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("privacy.profile.error_title") }}',
                    text: '{{ __("privacy.profile.error_update_picture") }}',
                    confirmButtonText: '{{ __("privacy.profile.ok") }}'
                });
            }
        });
    }

    // Handle window resize
    $(window).on('resize', function() {
        if ($('#profile-settings-modal').hasClass('show')) {
            updateCarouselPosition(false);
        }
    });

})();
</script>