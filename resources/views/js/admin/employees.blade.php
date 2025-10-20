<script>
// === TRANSLATIONS ===
const translations = {
    confirmDeleteUser: @json(__('admin/employees.confirm-delete-user')),
    actionIrreversible: @json(__('admin/employees.action-irreversible')),
    deleted: @json(__('admin/employees.deleted')),
    userDeletedSuccess: @json(__('admin/employees.user-deleted-success')),
    error: @json(__('admin/employees.error')),
    userDeleteFailed: @json(__('admin/employees.user-delete-failed')),
    passwordResetConfirm: @json(__('admin/employees.password-reset-confirm')),
    passwordResetEmailInfo: @json(__('admin/employees.password-reset-email-info')),
    sent: @json(__('admin/employees.sent')),
    passwordResetEmailSent: @json(__('admin/employees.password-reset-email-sent')),
    passwordResetEmailFailed: @json(__('admin/employees.password-reset-email-failed')),
    unlockAccountConfirm: @json(__('admin/employees.unlock-account-confirm')),
    unlockAccountInfo: @json(__('admin/employees.unlock-account-info')),
    unlocked: @json(__('admin/employees.unlocked')),
    accountUnlockedSuccess: @json(__('admin/employees.account-unlocked-success')),
    accountUnlockFailed: @json(__('admin/employees.account-unlock-failed')),
    confirmDeleteDepartment: @json(__('admin/employees.confirm-delete-department')),
    departmentMembersUnassignedInfo: @json(__('admin/employees.department-members-unassigned-info')),
    departmentDeletedSuccess: @json(__('admin/employees.department-deleted-success')),
    departmentDeleteFailed: @json(__('admin/employees.department-delete-failed')),
    selectEmployee: @json(__('admin/employees.select-employee')),
    noSelectableEmployee: @json(__('admin/employees.no-selectable-employee')),
    confirmSaveDeptMembers: @json(__('admin/employees.confirm-save-dept-members')),
    saved: @json(__('admin/employees.saved')),
    deptMembersUpdated: @json(__('admin/employees.dept-members-updated')),
    saveChangesFailed: @json(__('admin/employees.save-changes-failed')),
    nothingToRemove: @json(__('admin/employees.nothing-to-remove')),
    deptNoMembersCurrently: @json(__('admin/employees.dept-no-members-currently')),
    areYouSure: @json(__('admin/employees.are-you-sure')),
    removeAllMembersWarning: @json(__('admin/employees.remove-all-members-warning')),
    yesRemoveEveryoneNow: @json(__('admin/employees.yes-remove-everyone-now')),
    cancel: @json(__('admin/employees.cancel')),
    allMembersRemovedSuccess: @json(__('admin/employees.all-members-removed-success')),
    errorDuringRemoval: @json(__('admin/employees.error-during-removal')),
    successful: @json(__('admin/employees.successful')),
    remove: @json(__('admin/employees.remove')),
    deptMembersLoadFailed: @json(__('admin/employees.dept-members-load-failed'))
};

// === COMPLETE EMPLOYEES.BLADE.PHP JAVASCRIPT FIXES ===

// Helper functions (keep existing)
function getDeptIdFromAny(element) {
    const $el = $(element);
    let deptId = $el.attr('data-dept-id') || $el.data('dept-id');
    if (deptId) return deptId;
    
    const $deptBlock = $el.closest('.dept-block');
    deptId = $deptBlock.attr('data-dept-id') || $deptBlock.data('dept-id');
    if (deptId) return deptId;
    
    const $deptHeader = $el.closest('.dept-header');
    deptId = $deptHeader.attr('data-dept-id') || $deptHeader.data('dept-id');
    return deptId || null;
}

function getUserIdFromAny(element) {
    const $el = $(element);
    let userId = $el.attr('data-id') || $el.data('id');
    if (userId) return userId;
    
    const $userRow = $el.closest('.user-row');
    userId = $userRow.attr('data-id') || $userRow.data('id');
    if (userId) return userId;
    
    const $tr = $el.closest('tr.user-row');
    userId = $tr.attr('data-id') || $tr.data('id');
    return userId || null;
}

function hasLegacyTable() {
    return document.querySelector('.tile.userlist table') !== null;
}

$(document).ready(function(){


    // ========== CHECK FOR ACTIVE IMPORT ON PAGE LOAD ==========
    let activeImportJobId = null;
    let importCheckInterval = null;
    
    function checkForActiveImport() {
        $.ajax({
            url: '{{ route("admin.employee.import.check-active") }}',
            method: 'GET',
            success: function(response) {
                if (response.has_active_import) {
                    activeImportJobId = response.job_id;
                    showImportInProgress(response);
                    
                    // Start polling for updates if not already polling
                    if (!importCheckInterval) {
                        importCheckInterval = setInterval(pollImportStatus, 5000);
                    }
                } else {
                    // No active import - restore normal state
                    restoreNormalState();
                    if (importCheckInterval) {
                        clearInterval(importCheckInterval);
                        importCheckInterval = null;
                    }
                }
            },
            error: function(xhr) {
                console.error('Failed to check for active import:', xhr);
            }
        });
    }
    
    function pollImportStatus() {
        if (!activeImportJobId) return;
        
        $.ajax({
            url: `{{ url('admin/employee/import') }}/${activeImportJobId}/status`,
            method: 'GET',
            success: function(response) {
                if (response.status === 'completed' || response.status === 'failed') {
                    // Import finished - reload page
                    clearInterval(importCheckInterval);
                    importCheckInterval = null;
                    location.reload();
                } else {
                    // Update progress display if modal is open
                    if ($('#employee-import-modal').is(':visible') && window.updateProgressDisplay) {
                        window.updateProgressDisplay(response);
                    }
                }
            }
        });
    }
    
    function showImportInProgress(data) {
        const $trigger = $('.trigger-new');
        
        // Transform the "Add Employee" tile
        $trigger.addClass('import-in-progress')
                .attr('data-import-job-id', data.job_id)
                .html('<span><i class="fa fa-spinner fa-pulse"></i> {{ __("admin/employees.import-in-progress-tile") }}</span>');
        
        // Block all action buttons on user rows
        $('.user-row .btn').not('.import-in-progress').each(function() {
            $(this).addClass('disabled btn-import-blocked')
                   .attr('data-tippy-content', '{{ __("admin/employees.import-blocking-actions") }}')
                   .css('opacity', '0.5');
        });
        
        // Refresh Tippy tooltips
        if (window.tippy) {
            tippy('.btn-import-blocked', {
                content: '{{ __("admin/employees.import-blocking-actions") }}',
                placement: 'top'
            });
        }
    }
    
    function restoreNormalState() {
        const $trigger = $('.trigger-new');
        
        // Restore original tile
        $trigger.removeClass('import-in-progress')
                .removeAttr('data-import-job-id')
                .html('<span><i class="fa fa-user-plus"></i>{{ __("admin/employees.new-employee") }}</span>');
        
        // Unblock action buttons
        $('.btn-import-blocked').removeClass('disabled btn-import-blocked')
                                .removeAttr('data-tippy-content')
                                .css('opacity', '1');
    }
    
    // Click handler for import-in-progress tile
    $(document).on('click', '.trigger-new.import-in-progress', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const jobId = $(this).attr('data-import-job-id');
        if (jobId && window.reopenImportModal) {
            window.reopenImportModal(jobId);
        }
    });
    
    // Run check on page load
    checkForActiveImport();

    
    // ========== USER ACTIONS (UNIVERSAL) ==========
    
    // User edit (datas button) - FIXED
    $(document).on('click', '.datas', function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) {
            console.warn('No user ID found for datas button');
            return;
        }
        openEmployeeModal(userId);
    });

    // New employee button
     $(document).on('click', '.trigger-new', function(){
        // Check if limit is reached
        if (window.isEmployeeLimitReached) {
            Swal.fire({
                icon: 'warning',
                title: '{{ __("admin/employees.employee-limit-reached-title") }}',
                text: '{{ __("admin/employees.employee-limit-reached-modal-text") }}',
                confirmButtonText: '{{ __("global.ok") }}'
            });
            return;
        }
        openEmployeeModal();
    });

    // Competencies
    $(document).on('click', '.competencies', function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) return;
        initCompetenciesModal(userId);
    });

    // Relations
    $(document).on('click', '.relations', function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) return;
        initRelationsModal(userId);
    });

    // Bonus/Malus
    $(document).on('click', '.bonusmalus', function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) return;
        initBonusMalusModal(userId);
    });

    // Remove user
    $(document).on('click', '.remove:not(.disabled)', function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) return;
        
        swal_confirm.fire({
            title: translations.confirmDeleteUser,
            text: translations.actionIrreversible
        }).then((result) => {
            if (result.isConfirmed) {
                swal_loader.fire();
                $.ajax({
                    url: "{{ route('admin.employee.remove') }}",
                    method: 'POST',
                    data: { id: userId, _token: "{{ csrf_token() }}" },
                    success: function() {
                        swal_loader.close();
                        Swal.fire({ 
                            icon: 'success', 
                            title: translations.deleted, 
                            text: translations.userDeletedSuccess
                        }).then(() => window.location.reload());
                    },
                    error: function() {
                        swal_loader.close();
                        Swal.fire({ 
                            icon: 'error', 
                            title: translations.error, 
                            text: translations.userDeleteFailed
                        });
                    }
                });
            }
        });
    });

    // Password reset
    $(document).on('click', '.password-reset', async function(e){
        e.preventDefault();
        e.stopPropagation();
        const userId = getUserIdFromAny(this);
        if (!userId) return;

        const result = await swal_confirm.fire({
            title: translations.passwordResetConfirm,
            text: translations.passwordResetEmailInfo
        });

        if (!result.isConfirmed) return;

        const hasSwal = typeof swal_loader !== 'undefined' && swal_loader.fire;
        if (hasSwal) swal_loader.fire();

        try {
            const response = await fetch("{{ route('admin.employee.password-reset') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ id: userId })
            });

            if (hasSwal) swal_loader.close();

            if (response.ok) {
                const data = await response.json();
                if (hasSwal) {
                    await Swal.fire({ 
                        icon: 'success', 
                        title: translations.sent, 
                        text: data.message || translations.passwordResetEmailSent
                    });
                } else {
                    alert(translations.passwordResetEmailSent);
                }
            } else {
                throw new Error('HTTP ' + response.status);
            }
        } catch (err) {
            console.error(err);
            if (hasSwal) {
                await Swal.fire({ 
                    icon: 'error', 
                    title: translations.error, 
                    text: translations.passwordResetEmailFailed
                });
            } else {
                alert(translations.passwordResetEmailFailed);
            }
        }
    });

    $(document).on('click', '.unlock-account', async function(e){
    e.preventDefault();
    e.stopPropagation();
    const userId = getUserIdFromAny(this);
    if (!userId) return;

    const result = await swal_confirm.fire({
        title: translations.unlockAccountConfirm,
        text: translations.unlockAccountInfo,
        icon: 'warning'
    });

    if (!result.isConfirmed) return;

    const hasSwal = typeof swal_loader !== 'undefined' && swal_loader.fire;
    if (hasSwal) swal_loader.fire();

    try {
        const response = await fetch("{{ route('admin.employee.unlock-account') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ user_id: userId })
        });

        const data = await response.json();

        if (hasSwal) swal_loader.close();

        if (data.ok) {
            // Replace unlock button with password reset button
            const $button = $(`.unlock-account[data-user-id="${userId}"]`);
            if ($button.length) {
                $button.removeClass('btn-outline-danger unlock-account')
                       .addClass('btn-outline-secondary password-reset')
                       .attr('data-tippy-content', '{{ $_('password-reset-tooltip') }}')
                       .html('<i class="fa fa-key"></i>');
                
                // Reinitialize tippy for this button
                if (typeof tippy !== 'undefined') {
                    tippy($button[0], {
                        content: '{{ $_('password-reset-tooltip') }}'
                    });
                }
            }

            Swal.fire({ 
                icon: 'success', 
                title: translations.unlocked, 
                text: translations.accountUnlockedSuccess
            });
        } else {
            Swal.fire({ 
                icon: 'error', 
                title: translations.error, 
                text: data.message || translations.accountUnlockFailed
            });
        }
    } catch (error) {
        if (hasSwal) swal_loader.close();
        Swal.fire({ 
            icon: 'error', 
            title: translations.error, 
            text: translations.accountUnlockFailed
        });
    }
});

    (function() {
    'use strict';
    
    const orgId = "{{ (int)session('org_id') }}";
    const isMultiLevel = {{ !empty($enableMultiLevel) ? 'true' : 'false' }};
    
    // ========== UNIFIED SEARCH ==========
    
    /**
     * Main search function - works for both legacy and multi-level views
     */
    function performSearch(searchTerm) {
        const search = (searchTerm || '').toLowerCase().trim();
        const url = new URL(window.location.href);
        
        // Update URL without reload
        url.searchParams.delete('search');
        if (search.length > 0) {
            url.searchParams.set('search', search);
        }
        window.history.replaceState(null, null, url);
        
        if (isMultiLevel) {
            searchMultiLevel(search);
        } else {
            searchLegacy(search);
        }
    }
    
    /**
     * Search in multi-level view (departments + users)
     */
    function searchMultiLevel(search) {
        let totalVisibleUsers = 0;
        
        // Search in CEO + Unassigned section
        const $topLevelUserlist = $('.employees-ml > .userlist').first();
        const $topLevelUsers = $topLevelUserlist.find('.user-row');
        let topLevelVisible = 0;
        
        $topLevelUsers.each(function() {
            const userName = $(this).find('strong').first().text().toLowerCase();
            const userEmail = $(this).find('small.text-muted').first().text().toLowerCase();
            const matches = userName.includes(search) || userEmail.includes(search);
            
            $(this).toggle(search === '' || matches);
            if (matches || search === '') {
                topLevelVisible++;
            }
        });
        
        totalVisibleUsers += topLevelVisible;
        
        // Toggle "no users" message for top level
        const $topLevelNoUsers = $topLevelUserlist.find('.tile-info');
        $topLevelNoUsers.toggle(topLevelVisible === 0);
        
        // Search in departments
        $('.dept-block').each(function() {
            const $deptBlock = $(this);
            const deptName = $deptBlock.find('.dept-title').text().toLowerCase();
            const deptNameMatches = search === '' || deptName.includes(search);
            
            // Search users within department (managers + members)
            const $allUsers = $deptBlock.find('.user-row');
            let deptVisibleUsers = 0;
            
            $allUsers.each(function() {
                const userName = $(this).find('strong').first().text().toLowerCase();
                const userEmail = $(this).find('small.text-muted').first().text().toLowerCase();
                const userMatches = userName.includes(search) || userEmail.includes(search);
                
                const shouldShow = search === '' || deptNameMatches || userMatches;
                $(this).toggle(shouldShow);
                
                if (shouldShow) {
                    deptVisibleUsers++;
                }
            });
            
            // Show department if:
            // 1. No search (empty)
            // 2. Department name matches
            // 3. Any user in department matches
            const shouldShowDept = search === '' || deptNameMatches || deptVisibleUsers > 0;
            $deptBlock.toggle(shouldShowDept);
            
            if (shouldShowDept) {
                totalVisibleUsers += deptVisibleUsers;
                
                // Expand department if search found matches inside
                if (search !== '' && deptVisibleUsers > 0) {
                    const $body = $deptBlock.find('.dept-body');
                    const $caret = $deptBlock.find('.caret');
                    $body.show();
                    $caret.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                }
            }
            
            // Highlight department name if it matches
            const $deptTitle = $deptBlock.find('.dept-title');
            if (search !== '' && deptNameMatches && !deptName.includes(search) === false) {
                $deptTitle.addClass('search-highlight');
            } else {
                $deptTitle.removeClass('search-highlight');
            }
        });
        
        // Show "no results" message if nothing visible
        showNoResultsMessage(totalVisibleUsers === 0 && search !== '');
    }
    
    /**
     * Search in legacy table view
     */
    function searchLegacy(search) {
        const $rows = $('tbody tr:not(.no-employee)');
        let visibleCount = 0;
        
        $rows.each(function() {
            const $row = $(this);
            // Search in name (first td) and email
            const $firstTd = $row.find('td').first();
            const name = $firstTd.find('strong').text().toLowerCase();
            const email = $firstTd.find('small.text-muted').text().toLowerCase();
            
            const matches = search === '' || name.includes(search) || email.includes(search);
            $row.toggle(matches);
            
            if (matches) {
                visibleCount++;
            }
        });
        
        // Toggle "no employee" row
        const $noEmpRow = $('tr.no-employee');
        $noEmpRow.toggle(visibleCount === 0);
    }
    
    /**
     * Show/hide global "no results" message
     */
    function showNoResultsMessage(show) {
        let $noResults = $('.search-no-results');
        
        if (show && $noResults.length === 0) {
            // Create message if it doesn't exist
            $noResults = $('<div class="tile tile-warning search-no-results" style="margin-top: 1rem;">' +
                '<i class="fa fa-search"></i> ' +
                '<strong>{{ __("global.no-results-found") }}</strong>' +
                '</div>');
            $('.employees-ml').prepend($noResults);
        }
        
        if ($noResults.length > 0) {
            $noResults.toggle(show);
        }
    }
    
    /**
     * Debounce function to limit search frequency
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Create debounced search (300ms delay for better performance)
    const debouncedSearch = debounce(function(searchTerm) {
        performSearch(searchTerm);
    }, 300);
    
    // ========== EVENT LISTENERS ==========
    
    // Real-time search on input
    $(document).on('input', '.search-input', function() {
        const searchTerm = $(this).val();
        debouncedSearch(searchTerm);
    });
    
    // Also support Enter key for immediate search
    $(document).on('keyup', '.search-input', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = $(this).val();
            performSearch(searchTerm);
        }
    });
    
    // Clear search button
    $(document).on('click', '.clear-search', function() {
        const $input = $('.search-input');
        $input.val('');
        performSearch('');
        $input.focus();
    });
    
    // ========== INITIALIZATION ==========
    
    $(document).ready(function() {
        // Check if there's a search param in URL on page load
        const url = new URL(window.location.href);
        const searchParam = url.searchParams.get('search');
        
        if (searchParam) {
            $('.search-input').val(searchParam);
            performSearch(searchParam);
        }
        
           console.log('🔍 Search system initialized (Multi-level: ' + isMultiLevel + ')');
});

// ========== EXPOSE GLOBALLY ==========

// Make performSearch available globally for state management
window.employeesSearch = {
    perform: performSearch,
    isMultiLevel: isMultiLevel
};

})();

// ============================================================================
// STEP 3: PAGE STATE MANAGEMENT
// Add this to resources/views/js/admin/employees.blade.php
// Place it AFTER the search system code from Step 2
// ============================================================================

(function() {
    'use strict';
    
    const orgId = "{{ (int)session('org_id') }}";
    const STATE_KEY = 'employees_page_state_org_' + orgId;
    const STATE_EXPIRATION = 30000; // 30 seconds
    
    // ========== STATE MANAGEMENT ==========
    
    /**
     * Save current page state to sessionStorage
     */
    function savePageState() {
        try {
            const state = {
                searchValue: $('.search-input').val() || '',
                scrollY: window.scrollY || 0,
                timestamp: Date.now(),
                url: window.location.href
            };
            
            sessionStorage.setItem(STATE_KEY, JSON.stringify(state));
            console.log('💾 Page state saved:', state);
        } catch(e) {
            console.warn('⚠️ Could not save page state:', e);
        }
    }
    
    /**
     * Restore page state from sessionStorage
     */
    function restorePageState() {
        try {
            const stateJson = sessionStorage.getItem(STATE_KEY);
            if (!stateJson) {
                console.log('📭 No saved state found');
                return;
            }
            
            const state = JSON.parse(stateJson);
            
            // Check if state is expired
            const age = Date.now() - (state.timestamp || 0);
            if (age > STATE_EXPIRATION) {
                console.log('⏰ State expired (age: ' + age + 'ms)');
                sessionStorage.removeItem(STATE_KEY);
                return;
            }
            
            // Check if we're on the same URL (to avoid restoring on wrong page)
            if (state.url && !window.location.href.includes('/admin/employees')) {
                console.log('🚫 Wrong page, not restoring state');
                sessionStorage.removeItem(STATE_KEY);
                return;
            }
            
            console.log('📂 Restoring page state:', state);
            
            // Restore search value
            if (state.searchValue && $('.search-input').length) {
                $('.search-input').val(state.searchValue);
                
                // Trigger search after a short delay to ensure DOM is ready
                setTimeout(function() {
                    if (window.employeesSearch && typeof window.employeesSearch.perform === 'function') {
                        window.employeesSearch.perform(state.searchValue);
                        console.log('🔍 Search restored:', state.searchValue);
                    } else {
                        // Fallback: trigger input event
                        $('.search-input').trigger('input');
                        console.log('🔍 Search restored via fallback');
                    }
                }, 100);
            }
            
            // Restore scroll position after content is rendered
            if (state.scrollY > 0) {
                setTimeout(function() {
                    window.scrollTo({
                        top: state.scrollY,
                        behavior: 'smooth'
                    });
                    console.log('📜 Scroll restored to:', state.scrollY);
                }, 500); // Wait longer for scroll to ensure search results are rendered
            }
            
            // Clean up state after successful restore
            sessionStorage.removeItem(STATE_KEY);
            
        } catch(e) {
            console.warn('⚠️ Could not restore page state:', e);
            // Clean up potentially corrupted state
            sessionStorage.removeItem(STATE_KEY);
        }
    }
    
    /**
     * Clear page state
     */
    function clearPageState() {
        try {
            sessionStorage.removeItem(STATE_KEY);
            console.log('🗑️ Page state cleared');
        } catch(e) {
            console.warn('⚠️ Could not clear page state:', e);
        }
    }
    
    // ========== HOOK INTO RELOAD TRIGGERS ==========
    
    /**
     * Intercept sessionStorage.setItem for toast messages
     * This catches all modal success patterns that set toast then reload
     */
    const originalSetItem = sessionStorage.setItem.bind(sessionStorage);
    sessionStorage.setItem = function(key, value) {
        // Save state when any toast message is set
        // All modals use: sessionStorage.setItem('xxx_toast', msg); window.location.reload();
        if (key.includes('_toast') || key.includes('toast')) {
            console.log('🍞 Toast message detected:', key, '- Saving state before reload');
            savePageState();
        }
        return originalSetItem(key, value);
    };
    
    /**
     * Also watch for jQuery AJAX calls that trigger reload via successMessage
     * This is a backup for any AJAX that uses the successMessage pattern
     */
    $(document).ajaxComplete(function(event, xhr, settings) {
        // If AJAX has successMessage, it will reload the page
        if (settings.successMessage && xhr.status === 200) {
            console.log('✅ AJAX success with message - Saving state');
            savePageState();
        }
    });
    
    // ========== PAGE LIFECYCLE ==========
    
    /**
     * Initialize state management on page load
     */
    $(document).ready(function() {
        // Restore state on page load
        restorePageState();
        
        console.log('✅ Page state management initialized');
    });
    
    /**
     * Save state before page unload (navigation away)
     */
    $(window).on('beforeunload', function() {
        // Only save if user is navigating away intentionally
        // Don't save on refresh (handled by reload intercept)
        const searchValue = $('.search-input').val();
        if (searchValue) {
            console.log('👋 Navigating away, saving state...');
            savePageState();
        }
    });
    
    /**
     * Clear state on explicit user actions that should reset the page
     */
    $(document).on('click', '.trigger-new', function() {
        // User is creating new employee - clear state
        clearPageState();
    });
    
    $(document).on('click', '.trigger-new-dept', function() {
        // User is creating new department - clear state
        clearPageState();
    });
    
    // ========== EXPOSE FUNCTIONS GLOBALLY ==========
    
    // Make functions available globally if needed
    window.employeesPageState = {
        save: savePageState,
        restore: restorePageState,
        clear: clearPageState
    };
    
})();



    // ========== NETWORK MODAL - FIXED ==========
    $(document).on('click', '.network', function() {
        initNetworkModal();
    });

    // ========== DEPARTMENT MANAGEMENT - COMPLETELY FIXED ==========
    
    // New department button
    $(document).on('click', '.trigger-new-dept', function(){
        initNewDepartmentModal();
    });

    // Edit department button  
    $(document).on('click', '.dept-edit', function(){
        const id = getDeptIdFromAny(this);
        if (!id) return;
        initEditDepartmentModal(id);
    });

    // Department members management
    $(document).on('click', '.dept-members', function(){
        const deptId = getDeptIdFromAny(this);
        if (!deptId) return;
        initDeptMembersModal(deptId);
    });

    // Department removal
    $(document).on('click', '.dept-remove', function(){
        const deptId = getDeptIdFromAny(this);
        if (!deptId) return;
        
        swal_confirm.fire({
            title: translations.confirmDeleteDepartment,
            text: translations.departmentMembersUnassignedInfo,
            icon: 'warning'
        }).then((result) => {
            if (result.isConfirmed) {
                swal_loader.fire();
                
                fetch("{{ route('admin.employee.department.delete') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ id: deptId })
                })
                .then(async r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    swal_loader.close();
                    Swal.fire({ 
                        icon: 'success', 
                        title: translations.deleted, 
                        text: translations.departmentDeletedSuccess
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(err => {
                    swal_loader.close();
                    Swal.fire({ 
                        icon: 'error', 
                        title: translations.error, 
                        text: translations.departmentDeleteFailed
                    });
                    console.error(err);
                });
            }
        });
    });

    // ========== DEPARTMENT TOGGLE - COMPLETELY FIXED ==========
    $(document).on('click', '.js-dept-toggle', function(e){
        // Only trigger if clicking on the toggle area, not action buttons
        if ($(e.target).closest('.actions').length > 0) {
            return;
        }
        
        const $deptBlock = $(this).closest('.dept-block');
        const $caret = $deptBlock.find('.caret');
        const $body = $deptBlock.find('.dept-body');
        const deptId = $deptBlock.attr('data-dept-id');
        
        if (!$body.length) return;
        
        // Toggle the state
        const isCurrentlyVisible = $body.is(':visible');
        const willHide = isCurrentlyVisible;
        
        // Animate the toggle
        $body.slideToggle(300, function() {
            // Update caret rotation after animation
            $caret.toggleClass('fa-chevron-down fa-chevron-up');
        });
        
        // Save state to localStorage
        const orgId = "{{ (int)session('org_id') }}";
        const KEY = 'dept_collapse_state_org_' + orgId;
        try {
            const state = JSON.parse(localStorage.getItem(KEY) || '{}');
            state[deptId] = willHide;
            localStorage.setItem(KEY, JSON.stringify(state));
        } catch(e) {
            console.warn('Could not save department state:', e);
        }
    });

    // Restore department toggle states on page load
    (function restoreDepartmentStates(){
        const orgId = "{{ (int)session('org_id') }}";
        const KEY = 'dept_collapse_state_org_' + orgId;
        
        try {
            const state = JSON.parse(localStorage.getItem(KEY) || '{}');
            
            document.querySelectorAll('.dept-block[data-dept-id]').forEach(block => {
                const deptId = block.getAttribute('data-dept-id');
                const shouldBeHidden = state[deptId] === true;
                const $block = $(block);
                const $body = $block.find('.dept-body');
                const $caret = $block.find('.caret');
                
                if (shouldBeHidden) {
                    $body.hide();
                    $caret.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                } else {
                    $body.show();
                    $caret.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                }
            });
        } catch(e) {
            console.warn('Could not restore department states:', e);
        }
    })();

    // ========== DEPARTMENT MEMBERS MODAL - FIXED ==========
    
    // Add member to department
    $(document).on('click', '.trigger-add-member', function(){
        const deptId = $('#dept-members-modal').attr('data-id');
        
        // Get currently selected members to exclude them
        var except = [];
        $('#dept-members-modal .dept-member-item').each(function(){
            except.push($(this).data('id')*1);
        });
        
        // Use the same select modal pattern as relations/competencies
        openSelectModal({
            title: translations.selectEmployee,
            parentSelector: '#dept-members-modal',
            ajaxRoute: "{{ route('admin.employee.department.eligible') }}?department_id="+deptId,
            itemData: function(item){ 
                return { 
                    id: item.id, 
                    name: item.name, 
                    top: null, 
                    bottom: item.email || null 
                }; 
            },
            selectFunction: function(){
                const uid = $(this).attr('data-id');
                const name = $(this).attr('data-name');
                const email = $(this).find('.item-content span').text() || '';
                
                // Check if not already added
                if ($('#dept-members-modal .dept-member-item[data-id="'+uid+'"]').length === 0) {
                    addMemberItem(uid, name, email);
                    if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
                }
            },
            exceptArray: except,
            multiSelect: true, // Enable multi-select like competencies
            emptyMessage: translations.noSelectableEmployee
        });
    });

    // Remove individual member
    $(document).on('click', '#dept-members-modal .dept-member-item i', function(){
        $(this).closest('.dept-member-item').remove();
    });

    // Save department members
    $(document).on('click', '.save-dept-members', function(){
        const deptId = $('#dept-members-modal').attr('data-id');
        var ids = [];
        $('#dept-members-modal .dept-member-item').each(function(){
            ids.push($(this).data('id')*1);
        });
        
        swal_confirm.fire({ 
            title: translations.confirmSaveDeptMembers
        }).then(function(r){
            if (!r.isConfirmed) return;
            
            swal_loader.fire();
            $.ajax({
                url: "{{ route('admin.employee.department.members.save') }}",
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ department_id: deptId, user_ids: ids }),
                success: function() {
                    swal_loader.close();
                    $('#dept-members-modal').modal('hide');
                    Swal.fire({ 
                        icon: 'success', 
                        title: translations.saved,
                        text: translations.deptMembersUpdated
                    }).then(() => window.location.reload());
                },
                error: function() {
                    swal_loader.close();
                    Swal.fire({ 
                        icon: 'error', 
                        title: translations.error, 
                        text: translations.saveChangesFailed
                    });
                }
            });
        });
    });

    // FIXED: Empty department button
    $(document).on('click', '.trigger-empty-department', function(){
        const deptId = $('#dept-members-modal').attr('data-id');
        const memberCount = $('#dept-members-modal .dept-member-item').length;
        
        if (memberCount === 0) {
            Swal.fire({
                icon: 'info',
                title: translations.nothingToRemove,
                text: translations.deptNoMembersCurrently
            });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: translations.areYouSure,
            text: translations.removeAllMembersWarning.replace(':count', memberCount),
            showCancelButton: true,
            confirmButtonText: translations.yesRemoveEveryoneNow,
            cancelButtonText: translations.cancel,
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                swal_loader.fire();
                
                // Call server immediately with empty array
                $.ajax({
                    url: "{{ route('admin.employee.department.members.save') }}",
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ 
                        department_id: deptId, 
                        user_ids: [] // Empty array to remove everyone
                    }),
                    success: function() {
                        swal_loader.close();
                        $('#dept-members-modal').modal('hide');
                        
                        // Set toast message for after page reload
                        sessionStorage.setItem('dept_empty_success_toast', translations.allMembersRemovedSuccess);
                        
                        // Reload page immediately
                        window.location.reload();
                    },
                    error: function(xhr) {
                        swal_loader.close();
                        const errorMsg = xhr.responseJSON?.message || translations.errorDuringRemoval;
                        Swal.fire({
                            icon: 'error',
                            title: translations.error,
                            text: errorMsg
                        });
                    }
                });
            }
        });
    });

    // Show success toast after page reload
    if (sessionStorage.getItem('dept_empty_success_toast')) {
        const message = sessionStorage.getItem('dept_empty_success_toast');
        sessionStorage.removeItem('dept_empty_success_toast');
        
        Swal.fire({
            icon: 'success',
            title: translations.successful,
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }

    // ========== HELPER FUNCTIONS ==========
    
    // Add member item to list
    function addMemberItem(uid, name, email){
        const mail = email ? ' <span class="text-muted small">(' + email + ')</span>' : '';
        $('.dept-members-list').append(
            '<div class="dept-member-item" data-id="'+uid+'">' +
                '<i class="fa fa-trash-alt" data-tippy-content="' + translations.remove + '"></i>' +
                '<div>' +
                    '<p>'+name+mail+'</p>' +
                '</div>' +
            '</div>'
        );
    }

    // Initialize department members modal
    function initDeptMembersModal(deptId){
        $('#dept-members-modal').attr('data-id', deptId);

        swal_loader.fire();
        $.ajax({
            url: "{{ route('admin.employee.department.members') }}",
            method: 'POST',
            data: { department_id: deptId },
            success: function(resp){
                $('.dept-members-list').html('');
                (resp.members || []).forEach(function(m){
                    addMemberItem(m.id, m.name, m.email);
                });
                if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
                swal_loader.close();
                $('#dept-members-modal').modal();
            },
            error: function() {
                swal_loader.close();
                Swal.fire({ 
                    icon: 'error', 
                    title: translations.error, 
                    text: translations.deptMembersLoadFailed
                });
            }
        });
    }

   

// Make sure bonusMalus variable is available
if (typeof bonusMalus === 'undefined') {
    var bonusMalus = @json(__('global.bonus-malus'));
}

    // Initialize tooltips for all elements
    if (window.tippy) {
        tippy('[data-tippy-content]');
    }

    // Restore search state from URL
    (function(){
        const url = new URL(window.location.href);
        if (url.searchParams.has('search')) {
            const input = document.querySelector('.search-input');
            if (input) {
                input.value = url.searchParams.get('search');
                input.dispatchEvent(new KeyboardEvent('keyup', { key:'Enter' }));
            }
        }
    })();
});
</script>