<script>
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
            title: 'Felhasználó törlése?',
            text: 'Ez a művelet nem vonható vissza!'
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
                            title: 'Törölve', 
                            text: 'A felhasználó sikeresen törölve.' 
                        }).then(() => window.location.reload());
                    },
                    error: function() {
                        swal_loader.close();
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Hiba', 
                            text: 'Nem sikerült törölni a felhasználót.' 
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
            title: 'Jelszó visszaállítás?',
            text: 'A felhasználó kap egy e-mailt az új jelszó beállításához.'
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
                        title: 'Elküldve', 
                        text: data.message || 'Jelszó visszaállító levél elküldve.' 
                    });
                } else {
                    alert('Jelszó visszaállító levél elküldve.');
                }
            } else {
                throw new Error('HTTP ' + response.status);
            }
        } catch (err) {
            console.error(err);
            if (hasSwal) {
                await Swal.fire({ 
                    icon: 'error', 
                    title: 'Hiba', 
                    text: 'Nem sikerült elküldeni a visszaállító levelet.' 
                });
            } else {
                alert('Nem sikerült elküldeni a visszaállító levelet.');
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
            title: 'Részleg törlése?',
            text: 'A részleg minden tagja átkerül a nem besorolt felhasználókhoz.',
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
                        title: 'Törölve', 
                        text: 'Részleg sikeresen törölve.' 
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(err => {
                    swal_loader.close();
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Hiba', 
                        text: 'Nem sikerült törölni a részleget.' 
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
            title: "Dolgozó kiválasztása",
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
            emptyMessage: 'Nincs választható dolgozó'
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
            title: 'Részleg tagjainak mentése?' 
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
                        title: 'Mentve',
                        text: 'Részleg tagjai frissítve.'
                    }).then(() => window.location.reload());
                },
                error: function() {
                    swal_loader.close();
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Hiba', 
                        text: 'Nem sikerült menteni a változtatásokat.' 
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
                title: 'Nincs mit eltávolítani',
                text: 'A részlegben jelenleg nincsenek tagok.'
            });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Biztos vagy benne?',
            text: `Minden tag (${memberCount} fő) azonnal eltávolításra kerül a részlegből. A felhasználók megmaradnak a rendszerben, csak nem lesznek részleg tagjai.`,
            showCancelButton: true,
            confirmButtonText: 'Igen, mindenkit eltávolít most',
            cancelButtonText: 'Mégse',
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
                        sessionStorage.setItem('dept_empty_success_toast', 'Minden tag eltávolításra került a részlegből.');
                        
                        // Reload page immediately
                        window.location.reload();
                    },
                    error: function(xhr) {
                        swal_loader.close();
                        const errorMsg = xhr.responseJSON?.message || 'Hiba történt az eltávolítás során.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Hiba',
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
            title: 'Sikeres',
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
                '<i class="fa fa-trash-alt" data-tippy-content="Eltávolítás"></i>' +
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
                    title: 'Hiba', 
                    text: 'Nem sikerült betölteni a részleg tagjait.' 
                });
            }
        });
    }

    function initBonusMalusModal(uid){
    swal_loader.fire();
    $('#bonusmalus-modal').attr('data-id', uid);
    
    $.ajax({
        url: "{{ route('admin.employee.bonusmalus.get') }}",
        method: 'POST',
        data: { 
            id: uid,
            _token: "{{ csrf_token() }}"
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
    .done(function(response){
        $('.bonusmalus-history').html('');

        if(response.length > 1){
            for(let i = 1; i < response.length; i++){
                $('.bonusmalus-history').prepend('<p>'+response[i].month.substring(0, 7).replace('-','.')+'.: <span>'+bonusMalus[response[i].level]+'</span></p>');
            }
        }

        if(response.length > 0) {
            $('.bonusmalus-select').val(response[0].level);
        }

        swal_loader.close();
        $('#bonusmalus-modal').modal();
    })
    .fail(function(xhr) {
        swal_loader.close();
        const errorMsg = xhr.responseJSON?.message || 'Nem sikerült betölteni a bonus/malus adatokat.';
        Swal.fire({ 
            icon: 'error', 
            title: 'Hiba', 
            text: errorMsg
        });
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