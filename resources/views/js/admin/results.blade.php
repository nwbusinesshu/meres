<script>
$(document).ready(function(){
  new CircularProgressBar('pie').initial();
});
</script>
<script>
(function() {
    'use strict';

    const enableMultiLevel = @json($enableMultiLevel);
    const upperThreshold = {{ (int) $assessment->normal_level_up ?? 0 }};
    const lowerThreshold = {{ (int) $assessment->normal_level_down ?? 0 }};
    
    let activeFilters = {
        search: '',
        threshold: null,
        trend: null,
        bonusmalus: null
    };

    // Search functionality
    const $searchInput = $('#results-search-input');
    const $clearBtn = $('#clear-search-btn');

    $searchInput.on('input', function() {
        const value = $(this).val().trim();
        activeFilters.search = value.toLowerCase();
        
        if (value) {
            $clearBtn.addClass('visible');
        } else {
            $clearBtn.removeClass('visible');
        }
        
        applyFilters();
    });

    $clearBtn.on('click', function() {
        $searchInput.val('');
        activeFilters.search = '';
        $clearBtn.removeClass('visible');
        applyFilters();
    });

    // Filter chips
    $('.filter-chip').on('click', function() {
        const $chip = $(this);
        const filterType = $chip.data('filter');
        const filterValue = $chip.data('value');

        // Toggle active state
        if ($chip.hasClass('active')) {
            $chip.removeClass('active');
            activeFilters[filterType] = null;
        } else {
            // Deactivate siblings
            $chip.siblings(`[data-filter="${filterType}"]`).removeClass('active');
            $chip.addClass('active');
            activeFilters[filterType] = filterValue;
        }

        applyFilters();
    });

    // Populate bonus/malus filters dynamically
    @if(!empty($showBonusMalus))
        const bonusMalusLevels = @json(collect($users->merge($departments->flatMap->users ?? collect()))->pluck('bonusMalus')->unique()->filter()->sort()->values());
        const $bonusMalusContainer = $('#bonusmalus-filters');
        
        bonusMalusLevels.forEach(function(level) {
            const levelText = @json(__("global.bonus-malus.*"))[level] || level;
            $bonusMalusContainer.append(`
                <div class="filter-chip" data-filter="bonusmalus" data-value="${level}">
                    ${levelText}
                </div>
            `);
        });

        // Attach event handlers to dynamically created chips
        $bonusMalusContainer.find('.filter-chip').on('click', function() {
            const $chip = $(this);
            const filterValue = $chip.data('value');

            if ($chip.hasClass('active')) {
                $chip.removeClass('active');
                activeFilters.bonusmalus = null;
            } else {
                $chip.siblings().removeClass('active');
                $chip.addClass('active');
                activeFilters.bonusmalus = filterValue;
            }

            applyFilters();
        });
    @endif

    function applyFilters() {
        let visibleCount = 0;
        const search = activeFilters.search;

        if (enableMultiLevel) {
            // Multi-level filtering
            $('.dept-block').each(function() {
                const $deptBlock = $(this);
                const deptName = $deptBlock.data('dept-name') || '';
                const deptNameMatches = search === '' || deptName.includes(search);
                
                let deptVisibleCount = 0;

                $deptBlock.find('.user-tile-link').each(function() {
                    const $tile = $(this);
                    
                    if (matchesFilters($tile, search, deptNameMatches)) {
                        $tile.show();
                        deptVisibleCount++;
                        visibleCount++;
                    } else {
                        $tile.hide();
                    }
                });

                // Show/hide department
                if (deptVisibleCount > 0 || (search !== '' && deptNameMatches)) {
                    $deptBlock.show();
                    
                    // Update badge count
                    $deptBlock.find('.badge').text(deptVisibleCount);
                    
                    // Auto-expand if search found matches
                    if (search !== '' && deptVisibleCount > 0) {
                        $deptBlock.find('.dept-header').removeClass('collapsed');
                        $deptBlock.find('.dept-body').removeClass('collapsed');
                    }
                } else {
                    $deptBlock.hide();
                }
            });
        } else {
            // Legacy flat list filtering
            $('.employee-list .user-tile-link').each(function() {
                const $tile = $(this);
                
                if (matchesFilters($tile, search, false)) {
                    $tile.show();
                    visibleCount++;
                } else {
                    $tile.hide();
                }
            });
        }

        // Show/hide no results message
        if (visibleCount === 0 && (search !== '' || activeFilters.threshold || activeFilters.trend || activeFilters.bonusmalus)) {
            $('#no-results-message').removeClass('hidden');
            $('.results-container').addClass('hidden');
        } else {
            $('#no-results-message').addClass('hidden');
            $('.results-container').removeClass('hidden');
        }
    }

    function matchesFilters($tile, search, deptMatches) {
        // Search filter
        if (search !== '') {
            const userName = $tile.find('.user-tile-name').text().toLowerCase();
            const userEmail = $tile.find('.user-tile-email').text().toLowerCase();
            const userPosition = $tile.find('.user-tile-position').text().toLowerCase();
            
            const textMatches = userName.includes(search) || 
                              userEmail.includes(search) || 
                              userPosition.includes(search);
            
            if (!textMatches && !deptMatches) {
                return false;
            }
        }

        // Threshold filter
        if (activeFilters.threshold) {
            const score = parseFloat($tile.data('score') || 0);
            
            if (activeFilters.threshold === 'above' && score <= upperThreshold) return false;
            if (activeFilters.threshold === 'between' && (score > upperThreshold || score < lowerThreshold)) return false;
            if (activeFilters.threshold === 'below' && score >= lowerThreshold) return false;
        }

        // Trend filter
        if (activeFilters.trend) {
            const trend = $tile.data('trend');
            if (trend !== activeFilters.trend) return false;
        }

        // Bonus/Malus filter
        if (activeFilters.bonusmalus) {
            const bonusMalus = $tile.data('bonusmalus');
            if (bonusMalus !== activeFilters.bonusmalus) return false;
        }

        return true;
    }

    // Department toggle
    window.toggleDepartment = function(header) {
        const $header = $(header);
        const $body = $header.next('.dept-body');
        
        $header.toggleClass('collapsed');
        $body.toggleClass('collapsed');
    };

    // Initialize
    $(document).ready(function() {
        console.log('🔍 Results search and filter system initialized');
    });
})();
</script>