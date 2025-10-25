{{-- Profile Settings Modal --}}
<div id="profile-settings-modal" class="modal fade modal-drawer" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content profile-settings-content">
      <div class="modal-header">
        <h5 class="modal-title">Profil Beállítások</h5>
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

        {{-- Profile Picture Carousel Section --}}
        <div class="profile-pic-section">
          <h6 class="section-title">Profilkép</h6>
          
          {{-- Large Preview Circle --}}
          <div class="profile-preview-container">
            <div class="profile-preview-circle">
              <img id="profile-preview-img" src="" alt="Profile Picture">
            </div>
          </div>

          {{-- Carousel Container --}}
          <div class="profile-carousel-container">
            <button class="carousel-nav carousel-nav-left" id="carousel-prev">
              <i class="fa fa-chevron-left"></i>
            </button>
            
            <div class="profile-carousel-wrapper">
              <div class="profile-carousel-track" id="carousel-track">
                {{-- Dynamically populated by JS --}}
              </div>
            </div>
            
            <button class="carousel-nav carousel-nav-right" id="carousel-next">
              <i class="fa fa-chevron-right"></i>
            </button>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Mégse</button>
        <button type="button" class="btn btn-primary" id="save-profile-pic">Mentés</button>
      </div>
    </div>
  </div>
</div>

<script>
/**
 * Profile Settings Modal JavaScript
 * Handles carousel navigation and profile picture selection
 */

(function() {
    'use strict';

    let availableOptions = [];
    let currentIndex = 0;

    // Initialize when DOM is ready
    $(document).ready(function() {
        initProfileSettingsModal();
    });

    function initProfileSettingsModal() {
        // Make avatar and username clickable in navbar
        $('.navbar .userinfo img.avatar, .navbar .userinfo .userinfo-name').on('click', function(e) {
            e.preventDefault();
            openProfileSettingsModal();
        });

        // Carousel navigation
        $('#carousel-prev').on('click', function() {
            navigateCarousel('prev');
        });

        $('#carousel-next').on('click', function() {
            navigateCarousel('next');
        });

        // Save button
        $('#save-profile-pic').on('click', function() {
            saveProfilePicture();
        });

        // Handle carousel item clicks (event delegation)
        $('#carousel-track').on('click', '.carousel-item', function() {
            const index = $(this).data('index');
            selectCarouselItem(index);
        });
    }

    function openProfileSettingsModal() {
        // Show loading state
        Swal.fire({
            title: 'Betöltés...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Load profile data
        $.ajax({
            url: '/profile-settings/data',
            method: 'GET',
            success: function(response) {
                Swal.close();
                
                if (response.success) {
                    availableOptions = response.available_options;
                    currentIndex = response.current_index;
                    
                    // Populate modal
                    $('#profile-user-name').text(response.user.name);
                    $('#profile-user-email').text(response.user.email);
                    $('#profile-preview-img').attr('src', response.current_pic);
                    
                    // Build carousel
                    buildCarousel();
                    
                    // Update carousel position
                    updateCarouselPosition();
                    
                    // Show modal
                    $('#profile-settings-modal').modal('show');
                }
            },
            error: function(xhr) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Hiba',
                    text: 'Nem sikerült betölteni a profil adatokat.'
                });
            }
        });
    }

    function buildCarousel() {
        const $track = $('#carousel-track');
        $track.empty();

        availableOptions.forEach((option, index) => {
            const $item = $('<div>')
                .addClass('carousel-item')
                .attr('data-index', index)
                .attr('data-type', option.type)
                .attr('data-color', option.color || '')
                .html(`<img src="${option.url}" alt="Profile Picture Option">`);

            if (index === currentIndex) {
                $item.addClass('active');
            }

            $track.append($item);
        });

        updateNavigationButtons();
    }

    function navigateCarousel(direction) {
        if (direction === 'prev' && currentIndex > 0) {
            currentIndex--;
        } else if (direction === 'next' && currentIndex < availableOptions.length - 1) {
            currentIndex++;
        }

        selectCarouselItem(currentIndex);
    }

    function selectCarouselItem(index) {
        currentIndex = index;
        
        // Update preview image
        const selectedOption = availableOptions[currentIndex];
        $('#profile-preview-img').attr('src', selectedOption.url);
        
        // Update active state
        $('.carousel-item').removeClass('active');
        $(`.carousel-item[data-index="${currentIndex}"]`).addClass('active');
        
        // Update carousel position
        updateCarouselPosition();
        
        // Update navigation buttons
        updateNavigationButtons();
    }

    function updateCarouselPosition() {
        const $track = $('#carousel-track');
        const $wrapper = $('.profile-carousel-wrapper');
        const itemWidth = 70; // carousel-item width
        const gap = 16; // gap between items (1rem = 16px)
        const totalItemWidth = itemWidth + gap;
        
        // Calculate the offset to center the selected item
        const wrapperWidth = $wrapper.width();
        const centerOffset = (wrapperWidth / 2) - (itemWidth / 2);
        const translateX = centerOffset - (currentIndex * totalItemWidth);
        
        $track.css('transform', `translateX(${translateX}px)`);
    }

    function updateNavigationButtons() {
        $('#carousel-prev').prop('disabled', currentIndex === 0);
        $('#carousel-next').prop('disabled', currentIndex === availableOptions.length - 1);
    }

    function saveProfilePicture() {
        const selectedOption = availableOptions[currentIndex];
        
        // Show loading
        $('#save-profile-pic').prop('disabled', true).text('Mentés...');
        
        $.ajax({
            url: '/profile-settings/update-picture',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                type: selectedOption.type,
                color: selectedOption.color
            },
            success: function(response) {
                $('#save-profile-pic').prop('disabled', false).text('Mentés');
                
                if (response.success) {
                    // Update navbar avatar
                    $('.navbar .userinfo img.avatar').attr('src', response.new_avatar_url);
                    
                    // Close modal
                    $('#profile-settings-modal').modal('hide');
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Siker!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Hiba',
                        text: response.error || 'Nem sikerült frissíteni a profilképet.'
                    });
                }
            },
            error: function(xhr) {
                $('#save-profile-pic').prop('disabled', false).text('Mentés');
                
                Swal.fire({
                    icon: 'error',
                    title: 'Hiba',
                    text: 'Nem sikerült frissíteni a profilképet.'
                });
            }
        });
    }

    // Handle window resize
    $(window).on('resize', function() {
        if ($('#profile-settings-modal').hasClass('show')) {
            updateCarouselPosition();
        }
    });

})();

</script>