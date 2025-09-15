@if(!empty($enableMultiLevel) && $enableMultiLevel)
<div class="modal fade modal-drawer" id="department-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Új részleg létrehozása</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="dept-error"></div>
                
                <div class="form-group">
                    <label for="dept-name">Részleg neve <span class="text-danger">*</span></label>
                    <input type="text" class="form-control dept-name" id="dept-name" placeholder="Pl. IT Részleg">
                </div>

                {{-- Managers List (like relations/competencies) --}}
                <div class="form-group">
                    <label>Vezetők <span class="text-danger">*</span></label>
                    <div class="managers-list">
                        {{-- Dynamic content will be populated here --}}
                    </div>
                    <div class="tile tile-button trigger-new-manager">Vezető hozzáadása</div>
                </div>

                <div class="form-group">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i> 
                        Egy részleghez több vezető is kijelölhető. A vezetők kezelhetik a részleg tagjait és látják az értékeléseket.
                    </small>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-primary trigger-submit-dept">Létrehozás</button>
            </div>
        </div>
    </div>
</div>

<script>
// Add new manager item to the list (like relations/competencies pattern)
function addNewManagerItem(uid, name, email = null){
    const emailDisplay = email ? ' <span class="text-muted small">(' + email + ')</span>' : '';
    $('.managers-list').append(''
        +'<div class="manager-item" data-id="'+uid+'">'
        +'<i class="fa fa-trash-alt" data-tippy-content="Vezető eltávolítása"></i>'
        +'<div>'
        +'<p>'+name+emailDisplay+'</p>'
        +'</div>'
        +'</div>');
}

// Initialize department modal (create new)
function initNewDepartmentModal(){
    $('#department-modal').attr('data-id', ''); // empty = CREATE
    $('#department-modal .modal-title').text('Új részleg létrehozása');
    $('#department-modal .trigger-submit-dept').text('Létrehozás');
    $('#department-modal .dept-name').val('');
    $('.managers-list').html('');
    $('#dept-error').addClass('d-none').text('');
    
    $('#department-modal').modal();
}

// Initialize department modal (edit existing)
function initEditDepartmentModal(deptId){
    swal_loader.fire();
    
    fetch("{{ route('admin.employee.department.get') }}", {
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
        $('#department-modal .modal-title').text('Részleg szerkesztése');
        $('#department-modal .trigger-submit-dept').text('Mentés');
        $('#department-modal').attr('data-id', String(data.department.id));
        $('#department-modal .dept-name').val(data.department.department_name);
        
        // Clear and populate managers list
        $('.managers-list').html('');
        (data.currentManagers || []).forEach(function(manager){
            addNewManagerItem(manager.id, manager.name, manager.email);
        });
        
        if (window.tippy) tippy('.managers-list [data-tippy-content]');
        
        $('#dept-error').addClass('d-none').text('');
        swal_loader.close();
        $('#department-modal').modal();
    })
    .catch(err => {
        swal_loader.close();
        Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült betölteni a részleg adatait.' });
        console.error(err);
    });
}

$(document).ready(function(){
    // Add new manager button (like relations/competencies)
    $(document).on('click', '.trigger-new-manager', function(){
        var exceptArray = [];
        $('#department-modal .manager-item').each(function(){
            exceptArray.push($(this).attr('data-id')*1);
        });
        
        // Use the select modal pattern like relations/competencies
        openSelectModal({
            title: "Vezető kiválasztása",
            parentSelector: '#department-modal',
            ajaxRoute: "{{ route('admin.employee.get-eligible-managers') }}",
            itemData: function(item){ return {
                id: item.id,
                name: item.name,
                top: null,
                bottom: item.email || null
            }; },
            selectFunction: function(){
                const selectedId = $(this).attr('data-id');
                const selectedName = $(this).attr('data-name');
                const selectedEmail = $(this).find('.item-content span').text() || null;
                
                // Check if manager already exists to prevent duplicates
                if ($('#department-modal .manager-item[data-id="'+selectedId+'"]').length === 0) {
                    addNewManagerItem(selectedId, selectedName, selectedEmail);
                    if (window.tippy) tippy('.managers-list [data-tippy-content]');
                }
            },
            exceptArray: exceptArray,
            multiSelect: true,  // Enable multi-select like competencies
            emptyMessage: 'Nincs választható vezető'
        });
    });

    // Remove manager item
    $(document).on('click', '.manager-item i', function(){
        $(this).parents('.manager-item').remove();
    });

    // Submit department (create/update)
    $(document).on('click', '.trigger-submit-dept', function(){
        const id = $('#department-modal').attr('data-id');
        const name = $('#department-modal .dept-name').val().trim();
        
        // Collect selected manager IDs
        const managerIds = [];
        $('#department-modal .manager-item').each(function(){
            managerIds.push($(this).attr('data-id')*1);
        });

        if (!name) {
            $('#dept-error').removeClass('d-none').text('Add meg a részleg nevét.');
            return;
        }

        if (managerIds.length === 0) {
            $('#dept-error').removeClass('d-none').text('Legalább egy vezetőt ki kell jelölni.');
            return;
        }

        const isEdit = !!id;
        const url = isEdit ? "{{ route('admin.employee.department.update') }}" : "{{ route('admin.employee.department.store') }}";
        const title = isEdit ? 'Változtatások mentése?' : 'Részleg létrehozása?';

        swal_confirm.fire({
            title: title,
            text: isEdit ? 'A vezetők ellenőrzésre kerülnek (nem vezethetnek másik aktív részleget).' : 'A vezetők egy időben csak egy részleget vezethetnek.'
        }).then((res) => {
            if(!res.isConfirmed) return;

            swal_loader.fire();
            const payload = isEdit
                ? { id: id, name: name, manager_ids: managerIds }
                : { name: name, manager_ids: managerIds };

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(async r => {
                if (!r.ok) {
                    const errorData = await r.json();
                    throw new Error(errorData.message || 'HTTP ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                swal_loader.close();
                $('#department-modal').modal('hide');
                
                const successText = isEdit ? 'Részleg frissítve.' : 'Részleg létrehozva.';
                Swal.fire({ icon:'success', title:'Sikeres', text: successText }).then(() => {
                    window.location.reload();
                });
            })
            .catch(err => {
                swal_loader.close();
                const errorMsg = err.message || 'Ismeretlen hiba történt.';
                $('#dept-error').removeClass('d-none').text(errorMsg);
                console.error(err);
            });
        });
    });
});
</script>

<style>
/* Manager list styling (similar to relations/competencies) */
.managers-list {
    min-height: 60px;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    background-color: #f8f9fa;
}

.manager-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-left: 3px solid #28a745;
    color: #155724;
    background-color: #d4edda;
    border-radius: 0.25rem;
    margin-bottom: 0.25rem;
    transition: all 0.3s ease;
}

.manager-item:hover {
    background-color: #c3e6cb;
}

.manager-item i {
    color: #dc3545;
    cursor: pointer;
    padding: 0.25rem;
    transition: all 0.2s ease;
}

.manager-item i:hover {
    background: #f8d7da;
    border-radius: 0.25rem;
}

.manager-item div {
    flex: 1;
}

.manager-item p {
    margin: 0;
    font-weight: 600;
}

.managers-list:empty::after {
    content: "Még nincs kiválasztott vezető. Használd a 'Vezető hozzáadása' gombot.";
    display: block;
    text-align: center;
    padding: 2rem;
    color: #6c757d;
    font-style: italic;
}

.tile.tile-button.trigger-new-manager {
    margin-top: 0.5rem;
}
</style>
@endif