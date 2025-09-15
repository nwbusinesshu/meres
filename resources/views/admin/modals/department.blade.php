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

                {{-- NEW: Multiple Managers Selection --}}
                <div class="form-group">
                    <label for="dept-managers">Vezetők kiválasztása <span class="text-danger">*</span></label>
                    <div class="managers-selection">
                        {{-- Selected managers display --}}
                        <div class="selected-managers mb-3">
                            <div class="selected-managers-header d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Kiválasztott vezetők:</small>
                                <button type="button" class="btn btn-sm btn-outline-secondary clear-all-managers">
                                    <i class="fa fa-times"></i> Mindet töröl
                                </button>
                            </div>
                            <div class="selected-managers-list" id="selected-managers-list">
                                <div class="no-managers-selected text-muted small">Még nincs kiválasztott vezető</div>
                            </div>
                        </div>

                        {{-- Available managers selection --}}
                        <div class="available-managers">
                            <label class="small text-muted">Elérhető vezetők:</label>
                            <div class="available-managers-list" id="available-managers-list">
                                {{-- This will be populated by JavaScript --}}
                            </div>
                        </div>
                    </div>
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
    <style>
.managers-selection {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    background-color: #f8f9fa;
}

.selected-managers-list {
    min-height: 60px;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.5rem;
    background-color: white;
}

.available-managers-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.5rem;
    background-color: white;
}

.manager-item {
    padding: 0.5rem;
    margin-bottom: 0.25rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    background-color: white;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.manager-item:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.manager-item.selected {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.selected-manager-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    margin-bottom: 0.25rem;
    border: 1px solid #d1ecf1;
    border-radius: 0.25rem;
    background-color: #d1ecf1;
    color: #0c5460;
}

.selected-manager-item .remove-manager {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 0.25rem;
}

.selected-manager-item .remove-manager:hover {
    color: #c82333;
}
</style>
@endif
@if(!empty($enableMultiLevel) && $enableMultiLevel)
<script>
  // Replace the department-related JavaScript in your employees.blade.php with this updated version

// Global variables for manager selection
let selectedManagerIds = [];
let availableManagers = [];

// Initialize manager selection functionality
function initManagerSelection() {
    selectedManagerIds = [];
    renderSelectedManagers();
    renderAvailableManagers();
}

// Render selected managers list
function renderSelectedManagers() {
    const container = $('#selected-managers-list');
    
    if (selectedManagerIds.length === 0) {
        container.html('<div class="no-managers-selected text-muted small">Még nincs kiválasztott vezető</div>');
        return;
    }

    const selectedManagers = availableManagers.filter(m => selectedManagerIds.includes(m.id));
    const html = selectedManagers.map(manager => `
        <div class="selected-manager-item" data-manager-id="${manager.id}">
            <div>
                <strong>${manager.name}</strong>
                ${manager.email ? `<small class="text-muted d-block">${manager.email}</small>` : ''}
            </div>
            <button type="button" class="remove-manager" data-manager-id="${manager.id}">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `).join('');
    
    container.html(html);
}

// Render available managers list
function renderAvailableManagers() {
    const container = $('#available-managers-list');
    
    if (availableManagers.length === 0) {
        container.html('<div class="text-muted small">Nincs elérhető vezető</div>');
        return;
    }

    const html = availableManagers.map(manager => {
        const isSelected = selectedManagerIds.includes(manager.id);
        return `
            <div class="manager-item ${isSelected ? 'selected' : ''}" data-manager-id="${manager.id}">
                <strong>${manager.name}</strong>
                ${manager.email ? `<small class="text-muted d-block">${manager.email}</small>` : ''}
                ${isSelected ? '<small class="text-success"><i class="fa fa-check"></i> Kiválasztva</small>' : ''}
            </div>
        `;
    }).join('');
    
    container.html(html);
}

// ---------- RÉSZLEG: ÚJ LÉTREHOZÁSA (CREATE MODAL) ----------
$(document).on('click', '.trigger-new-dept', function(){
    $('#dept-error').addClass('d-none').text('');
    $('#department-modal').attr('data-id', ''); // üres = CREATE
    $('#department-modal .modal-title').text('Új részleg létrehozása');
    $('#department-modal .trigger-submit-dept').text('Létrehozás');
    $('#department-modal .dept-name').val('');
    
    // Load available managers
    swal_loader.fire();
    fetch("{{ route('admin.employee.get-eligible-managers') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(async r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        availableManagers = data.managers || [];
        if (availableManagers.length === 0) {
            swal_loader.close();
            Swal.fire({ 
                icon:'info', 
                title:'Nincs választható vezető', 
                text:'Előbb hozz létre legalább egy manager felhasználót.' 
            });
            return;
        }
        
        initManagerSelection();
        swal_loader.close();
        $('#department-modal').modal();
    })
    .catch(err => {
        swal_loader.close();
        Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült betölteni a vezetők listáját.' });
        console.error(err);
    });
});

// ---------- RÉSZLEG: SZERKESZTÉS (PREFILL) ----------
$(document).on('click', '.dept-edit', function(){
    const id = getDeptIdFromAny(this);
    if (!id) return;

    swal_loader.fire();
    fetch("{{ route('admin.employee.department.get') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ id })
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

        // Set up manager selection
        availableManagers = data.eligibleManagers || [];
        selectedManagerIds = (data.currentManagers || []).map(m => m.id);
        
        initManagerSelection();
        
        $('#dept-error').addClass('d-none').text('');
        swal_loader.close();
        $('#department-modal').modal();
    })
    .catch(err => {
        swal_loader.close();
        Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült betölteni a részleg adatait.' });
        console.error(err);
    });
});

// ---------- MANAGER SELECTION EVENTS ----------

// Add manager to selection
$(document).on('click', '.manager-item:not(.selected)', function(){
    const managerId = parseInt($(this).data('manager-id'));
    if (!selectedManagerIds.includes(managerId)) {
        selectedManagerIds.push(managerId);
        renderSelectedManagers();
        renderAvailableManagers();
    }
});

// Remove manager from selection
$(document).on('click', '.remove-manager', function(e){
    e.stopPropagation();
    const managerId = parseInt($(this).data('manager-id'));
    selectedManagerIds = selectedManagerIds.filter(id => id !== managerId);
    renderSelectedManagers();
    renderAvailableManagers();
});

// Clear all managers
$(document).on('click', '.clear-all-managers', function(){
    selectedManagerIds = [];
    renderSelectedManagers();
    renderAvailableManagers();
});

// ---------- RÉSZLEG: CREATE/UPDATE SUBMIT ----------
$(document).on('click', '.trigger-submit-dept', function(){
    const id   = $('#department-modal').attr('data-id');
    const name = $('#department-modal .dept-name').val().trim();

    if (!name) {
        $('#dept-error').removeClass('d-none').text('Add meg a részleg nevét.');
        return;
    }

    if (selectedManagerIds.length === 0) {
        $('#dept-error').removeClass('d-none').text('Válassz ki legalább egy vezetőt.');
        return;
    }

    const isEdit = !!id;
    const url    = isEdit ? "{{ route('admin.employee.department.update') }}" : "{{ route('admin.employee.department.store') }}";
    const title  = isEdit ? 'Változtatások mentése?' : 'Részleg létrehozása?';
    const okText = isEdit ? 'Részleg frissítve.' : 'Részleg létrehozva.';

    swal_confirm.fire({
        title: title,
        text: `${selectedManagerIds.length} vezető lesz hozzárendelve a részleghez.`
    }).then((res) => {
        if(!res.isConfirmed) return;

        swal_loader.fire();
        const payload = isEdit
            ? { id: Number(id), department_name: name, manager_ids: selectedManagerIds }
            : { department_name: name, manager_ids: selectedManagerIds };

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
                const j = await r.json().catch(()=>({}));
                throw new Error(j?.message || ('HTTP ' + r.status));
            }
            return r.json();
        })
        .then(() => {
            Swal.fire({ icon:'success', title:'OK', text: okText }).then(() => window.location.reload());
        })
        .catch(err => {
            swal_loader.close();
            $('#dept-error').removeClass('d-none').text(String(err.message || err));
        });
    });
});

// Helper function to get department ID (keep existing)
function getDeptIdFromAny(element) {
    const $el = $(element);
    return $el.data('dept-id') || $el.closest('[data-dept-id]').data('dept-id') || null;
}
</script>

@endif

