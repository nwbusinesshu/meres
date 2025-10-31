<script>
$(document).ready(function(){
  new CircularProgressBar('pie').initial();
});
</script>
<script>
(function() {
    'use strict';

    // Get thresholds from blade template (passed via data attributes or inline variables)
    const upperThreshold = {{ $assessment->normal_level_up ?? 0 }};
    const lowerThreshold = {{ $assessment->normal_level_down ?? 0 }};
    const showBonusMalus = {{ !empty($showBonusMalus) ? 'true' : 'false' }};
    const enableMultiLevel = {{ $enableMultiLevel ? 'true' : 'false' }};

    // Active filters state
    const activeFilters = {
        threshold: null,
        trend: null,
        bonusmalus: null
    };

    // Search input
    const $searchInput = $('#results-search-input');
    const $clearBtn = $('#clear-search-btn');

    // Initialize bonus/malus filters if enabled
    if (showBonusMalus) {
        initializeBonusMalusFilters();
    }

    // Search input handlers
    $searchInput.on('input', function() {
        const value = $(this).val().trim();
        
        if (value.length > 0) {
            $clearBtn.addClass('visible');
        } else {
            $clearBtn.removeClass('visible');
        }
        
        applyFilters();
    });

    $clearBtn.on('click', function() {
        $searchInput.val('');
        $clearBtn.removeClass('visible');
        applyFilters();
        $searchInput.focus();
    });

    // Filter chip click handlers
    $('.filter-chip').on('click', function() {
        const $chip = $(this);
        const filterType = $chip.data('filter');
        const filterValue = $chip.data('value');

        // Toggle active state
        if ($chip.hasClass('active')) {
            $chip.removeClass('active');
            activeFilters[filterType] = null;
        } else {
            // Remove active from siblings
            $chip.siblings(`[data-filter="${filterType}"]`).removeClass('active');
            $chip.addClass('active');
            activeFilters[filterType] = filterValue;
        }

        applyFilters();
    });

    /**
     * Initialize bonus/malus filter chips dynamically
     */
    function initializeBonusMalusFilters() {
        const bonusMalusLevels = new Set();
        
        // Collect all unique bonus/malus levels from tiles
        $('.user-tile-link').each(function() {
            const level = $(this).data('bonusmalus');
            if (level) {
                bonusMalusLevels.add(level);
            }
        });

        // Create filter chips
        const $container = $('#bonusmalus-filters');
        bonusMalusLevels.forEach(function(level) {
            const $chip = $('<div>')
                .addClass('filter-chip')
                .attr('data-filter', 'bonusmalus')
                .attr('data-value', level)
                .html(`<span>${level}</span>`)
                .on('click', function() {
                    const $this = $(this);
                    if ($this.hasClass('active')) {
                        $this.removeClass('active');
                        activeFilters.bonusmalus = null;
                    } else {
                        $this.siblings().removeClass('active');
                        $this.addClass('active');
                        activeFilters.bonusmalus = level;
                    }
                    applyFilters();
                });
            
            $container.append($chip);
        });
    }

    /**
     * Apply all active filters
     */
    function applyFilters() {
        const search = $searchInput.val().toLowerCase().trim();
        let visibleCount = 0;

        if (enableMultiLevel) {
            // Multi-level filtering with departments
            $('.dept-block').each(function() {
                const $deptBlock = $(this);
                const deptName = $deptBlock.data('dept-name') || '';
                const deptNameMatches = search === '' || deptName.includes(search);

                let deptVisibleCount = 0;

                // Filter users in this department (includes both managers and regular members)
                $deptBlock.find('.user-tile-link').each(function() {
                    const $tile = $(this);
                    
                    if (matchesFilters($tile, search, deptNameMatches)) {
                        $tile.show();
                        deptVisibleCount++;
                    } else {
                        $tile.hide();
                    }
                });

                visibleCount += deptVisibleCount;

                // Show/hide department
                if (deptVisibleCount > 0 || (search !== '' && deptNameMatches)) {
                    $deptBlock.show();
                    
                    // Update badge count - ONLY the department header badge, not user badges (CEO, MANAGER, bonus/malus)
                    $deptBlock.find('.dept-header .badge').first().text(deptVisibleCount);
                    
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

    /**
     * Check if a tile matches all active filters
     */
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

    /**
     * Department toggle
     */
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