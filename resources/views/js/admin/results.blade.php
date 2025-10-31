<script>
$(document).ready(function(){
  new CircularProgressBar('pie').initial();
});
</script>
<script>
(function() {
    'use strict';

    // Get thresholds from blade template
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

    // DOM elements
    const $searchInput = $('#results-search-input');
    const $clearBtn = $('#clear-search-btn');
    const $filterToggle = $('#filter-toggle');
    const $filtersDropdown = $('#filters-dropdown');

    // Initialize bonus/malus filters if enabled
    if (showBonusMalus) {
        initializeBonusMalusFilters();
    }

    // ========================================
    // INITIALIZE - SET DEFAULT STATE
    // ========================================
    function initializeFilterVisibility() {
        const isMobile = window.innerWidth < 960;
        
        if (isMobile) {
            // Mobile: filters hidden by default
            $filterToggle.removeClass('active');
            $filtersDropdown.removeClass('open');
        } else {
            // Desktop: filters always visible (toggle hidden via CSS)
            $filterToggle.addClass('active');
            $filtersDropdown.addClass('open');
        }
    }

    // ========================================
    // FILTER TOGGLE (MOBILE ONLY)
    // ========================================
    $filterToggle.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $toggle = $(this);
        const $dropdown = $filtersDropdown;
        
        $toggle.toggleClass('active');
        $dropdown.toggleClass('open');
        
        console.log('🔽 Filter toggle clicked - Open:', $dropdown.hasClass('open'));
    });

    // ========================================
    // WINDOW RESIZE - HANDLE RESPONSIVE BEHAVIOR
    // ========================================
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initializeFilterVisibility();
        }, 250);
    });

    // ========================================
    // SEARCH INPUT HANDLERS
    // ========================================
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

    // ========================================
    // FILTER CHIP CLICK HANDLERS
    // ========================================
    $(document).on('click', '.filter-chip', function() {
        const $chip = $(this);
        const filterType = $chip.data('filter');
        const filterValue = $chip.data('value');

        // Toggle active state
        if ($chip.hasClass('active')) {
            $chip.removeClass('active');
            activeFilters[filterType] = null;
        } else {
            // Remove active from siblings of same filter type
            $chip.siblings(`[data-filter="${filterType}"]`).removeClass('active');
            $chip.addClass('active');
            activeFilters[filterType] = filterValue;
        }

        applyFilters();
    });

    // ========================================
    // INITIALIZE BONUS/MALUS FILTERS
    // ========================================
    function initializeBonusMalusFilters() {
        const bonusMalusLevels = new Set();
        
        // Collect all unique bonus/malus levels from tiles (using Set to avoid duplicates)
        $('.user-tile-link').each(function() {
            const level = $(this).data('bonusmalus');
            if (level && level !== '') {
                bonusMalusLevels.add(String(level).trim());
            }
        });

        // Convert Set to sorted array
        const sortedLevels = Array.from(bonusMalusLevels).sort();

        // Create filter chips
        const $container = $('#bonusmalus-filters');
        $container.empty(); // Clear any existing chips

        sortedLevels.forEach(function(level) {
            const $chip = $('<div>')
                .addClass('filter-chip')
                .attr('data-filter', 'bonusmalus')
                .attr('data-value', level)
                .html(`<span>${level}</span>`);
            
            $container.append($chip);
        });

        console.log('✅ Bonus/Malus filters initialized:', sortedLevels);
    }

    // ========================================
    // APPLY ALL ACTIVE FILTERS
    // ========================================
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

                // Filter users in this department
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
                    
                    // Update badge count
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

        console.log('🔍 Filters applied - Visible:', visibleCount, 'Search:', search, 'Filters:', activeFilters);
    }

    // ========================================
    // CHECK IF TILE MATCHES ALL ACTIVE FILTERS
    // ========================================
    function matchesFilters($tile, search, deptMatches) {
        // Search filter
        if (search !== '') {
            const userName = ($tile.find('.user-tile-name').text() || '').toLowerCase();
            const userEmail = ($tile.find('.user-tile-email').text() || '').toLowerCase();
            const userPosition = ($tile.find('.user-tile-position').text() || '').toLowerCase();
            
            const textMatches = userName.includes(search) || 
                              userEmail.includes(search) || 
                              userPosition.includes(search);
            
            if (!textMatches && !deptMatches) {
                return false;
            }
        }

        // Threshold filter
        if (activeFilters.threshold) {
            const scoreAttr = $tile.attr('data-score') || $tile.data('score');
            const score = parseFloat(scoreAttr);
            
            if (isNaN(score)) {
                console.warn('Invalid score for tile:', $tile, 'score:', scoreAttr);
                return false;
            }
            
            // 'above' = score > upperThreshold
            // 'between' = lowerThreshold <= score <= upperThreshold  
            // 'below' = score < lowerThreshold
            if (activeFilters.threshold === 'above' && score <= upperThreshold) {
                return false;
            }
            if (activeFilters.threshold === 'between' && (score < lowerThreshold || score > upperThreshold)) {
                return false;
            }
            if (activeFilters.threshold === 'below' && score >= lowerThreshold) {
                return false;
            }
        }

        // Trend filter
        if (activeFilters.trend) {
            const trendAttr = $tile.attr('data-trend') || $tile.data('trend');
            const trend = String(trendAttr || '').trim();
            
            if (trend !== activeFilters.trend) {
                return false;
            }
        }

        // Bonus/Malus filter
        if (activeFilters.bonusmalus) {
            const bonusMalusAttr = $tile.attr('data-bonusmalus') || $tile.data('bonusmalus');
            const bonusMalus = String(bonusMalusAttr || '').trim();
            
            if (bonusMalus !== activeFilters.bonusmalus) {
                return false;
            }
        }

        return true;
    }

    // ========================================
    // DEPARTMENT TOGGLE
    // ========================================
    window.toggleDepartment = function(header) {
        const $header = $(header);
        const $body = $header.next('.dept-body');
        
        $header.toggleClass('collapsed');
        $body.toggleClass('collapsed');
    };

    // ========================================
    // INITIALIZE ON DOCUMENT READY
    // ========================================
    $(document).ready(function() {
        // Set initial filter visibility based on screen size
        initializeFilterVisibility();
        
        console.log('🔍 Results search and filter system initialized');
        console.log('📊 Thresholds - Upper:', upperThreshold, 'Lower:', lowerThreshold);
        console.log('💰 Show Bonus/Malus:', showBonusMalus);
        console.log('🏢 Multi-level:', enableMultiLevel);
        console.log('📱 Screen width:', window.innerWidth, 'Mobile:', window.innerWidth < 960);
    });
})();
</script>