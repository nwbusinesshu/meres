<script>
// === TRANSLATIONS ===
const translations = {
    confirmDeleteUser: @json($_('confirm-delete-user')),
    actionIrreversible: @json($_('action-irreversible')),
    deleted: @json($_('deleted')),
    userDeletedSuccess: @json($_('user-deleted-success')),
    error: @json($_('error')),
    userDeleteFailed: @json($_('user-delete-failed')),
    passwordResetConfirm: @json($_('password-reset-confirm')),
    passwordResetEmailInfo: @json($_('password-reset-email-info')),
    sent: @json($_('sent')),
    passwordResetEmailSent: @json($_('password-reset-email-sent')),
    passwordResetEmailFailed: @json($_('password-reset-email-failed')),
    confirmDeleteDepartment: @json($_('confirm-delete-department')),
    departmentMembersUnassignedInfo: @json($_('department-members-unassigned-info')),
    departmentDeletedSuccess: @json($_('department-deleted-success')),
    departmentDeleteFailed: @json($_('department-delete-failed')),
    selectEmployee: @json($_('select-employee')),
    noSelectableEmployee: @json($_('no-selectable-employee')),
    confirmSaveDeptMembers: @json($_('confirm-save-dept-members')),
    saved: @json($_('saved')),
    deptMembersUpdated: @json($_('dept-members-updated')),
    saveChangesFailed: @json($_('save-changes-failed')),
    nothingToRemove: @json($_('nothing-to-remove')),
    deptNoMembersCurrently: @json($_('dept-no-members-currently')),
    areYouSure: @json($_('are-you-sure')),
    removeAllMembersWarning: @json($_('remove-all-members-warning')),
    yesRemoveEveryoneNow: @json($_('yes-remove-everyone-now')),
    cancel: @json($_('cancel')),
    allMembersRemovedSuccess: @json($_('all-members-removed-success')),
    errorDuringRemoval: @json($_('error-during-removal')),
    successful: @json($_('successful')),
    remove: @json($_('remove')),
    deptMembersLoadFailed: @json($_('dept-members-load-failed'))
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

    // ========== SEARCH (legacy table only) ==========
    $(document).on('keyup', '.search-input', function(e){
        if (e.key !== 'Enter') return;
        if (!hasLegacyTable()) return;

        if (typeof swal_loader !== 'undefined' && swal_loader.fire) swal_loader.fire();

        const url = new URL(window.location.href);
        const search = (this.value || '').toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(tr => tr.classList.add('hidden'));
        document.querySelectorAll('tbody tr:not(.no-employee)').forEach(tr => {
            const firstTd = tr.querySelector('td');
            const text = (firstTd?.innerHTML || '').toLowerCase();
            if (text.includes(search)) tr.classList.remove('hidden');
        });

        url.searchParams.delete('search');
        if (search.length) url.searchParams.set('search', search);
        window.history.replaceState(null, null, url);

        const anyVisible = document.querySelectorAll('tbody tr:not(.no-employee):not(.hidden)').length > 0;
        const noEmp = document.querySelector('tr.no-employee');
        if (noEmp) noEmp.classList.toggle('hidden', anyVisible);

        if (typeof swal_loader !== 'undefined' && swal_loader.close) swal_loader.close();
    });

    $(document).on('click', '.clear-search', function(){
        const input = document.querySelector('.search-input');
        if (!input) return;
        input.value = '';
        input.dispatchEvent(new KeyboardEvent('keyup', { key:'Enter' }));
    });

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