{{-- resources/views/admin/modals/employee-import.blade.php --}}

<div class="modal fade modal-drawer" id="employee-import-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      
      <!-- ============================================ -->
      <!-- STEP 1: UPLOAD -->
      <!-- ============================================ -->
      <div class="import-step import-step-upload">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa fa-file-upload"></i>
            {{ __('admin/employees.import-employees') }}
          </h5>
          <button class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <div class="modal-body">
          <!-- Template Downloads -->
           <!-- Template Downloads -->
          <div class="template-downloads mb-4">
            <h6>{{ __('admin/employees.step-1-download-template') }}</h6>
            
            @if($enableMultiLevel)
              {{-- Show Multilevel Template --}}
              <a href="{{ route('admin.employee.import.template', 'multilevel') }}" 
                 class="btn btn-success btn-lg template-download-link" download>
                <i class="fa fa-download"></i> 
                {{ __('admin/employees.download-template') }}
              </a>
              <small class="form-text text-muted mt-2">
                {{ __('admin/employees.template-help-text-multilevel') }}
              </small>
            @else
              {{-- Show Legacy Template --}}
              <a href="{{ route('admin.employee.import.template', 'legacy') }}" 
                 class="btn btn-primary btn-lg template-download-link" download>
                <i class="fa fa-download"></i> 
                {{ __('admin/employees.download-template') }}
              </a>
              <small class="form-text text-muted mt-2">
                {{ __('admin/employees.template-help-text-legacy') }}
              </small>
            @endif
          </div>
          
          <!-- Drag & Drop Upload Zone -->
          <div class="upload-area" id="upload-dropzone">
            <i class="fa fa-cloud-upload fa-3x text-muted mb-3"></i>
            <p class="lead">{{ __('admin/employees.drag-drop-file') }}</p>
            <p class="text-muted">{{ __('admin/employees.or') }}</p>
            <input type="file" id="import-file-input" accept=".xlsx" hidden>
            <button type="button" class="btn btn-secondary browse-files-btn">
              <i class="fa fa-folder-open"></i>
              {{ __('admin/employees.browse-files') }}
            </button>
            <small class="form-text text-muted mt-2">
              {{ __('admin/employees.max-file-size') }}
            </small>
          </div>
          
          <!-- Instructions Panel -->
          <div class="import-instructions mt-4">
            <h6>{{ __('admin/employees.instructions-title') }}</h6>
            <ol>
              <li>{{ __('admin/employees.instruction-1') }}</li>
              <li>{{ __('admin/employees.instruction-2') }}</li>
              <li>{{ __('admin/employees.instruction-3') }}</li>
              <li>{{ __('admin/employees.instruction-4') }}</li>
              <li>{{ __('admin/employees.instruction-5') }}</li>
            </ol>
          </div>
        </div>
      </div>
      
      <!-- ============================================ -->
      <!-- STEP 2: VALIDATION PREVIEW (FULLSCREEN) -->
      <!-- ============================================ -->
      <div class="import-step import-step-preview hidden">
        <div class="modal-header bg-light">
          <h5 class="modal-title">
            <i class="fa fa-check-circle"></i>
            {{ __('admin/employees.import-preview') }}
          </h5>
          <button class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <div class="modal-body">
          <!-- Summary Statistics -->
          <div class="validation-summary mb-4">
            <div class="row">
              <div class="col-md-3">
                <div class="card border-primary">
                  <div class="card-body text-center">
                    <h3 class="mb-0" id="summary-total">0</h3>
                    <small class="text-muted">{{ __('admin/employees.total-rows') }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card border-success">
                  <div class="card-body text-center">
                    <h3 class="mb-0 text-success" id="summary-valid">0</h3>
                    <small class="text-muted">{{ __('admin/employees.valid-rows') }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card border-warning">
                  <div class="card-body text-center">
                    <h3 class="mb-0 text-warning" id="summary-warning">0</h3>
                    <small class="text-muted">{{ __('admin/employees.warning-rows') }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card border-danger">
                  <div class="card-body text-center">
                    <h3 class="mb-0 text-danger" id="summary-error">0</h3>
                    <small class="text-muted">{{ __('admin/employees.error-rows') }}</small>
                  </div>
                </div>
              </div>
            </div>
            
            <div id="limit-warning-container" class="alert alert-warning mt-3 hidden">
              <i class="fa fa-exclamation-triangle"></i>
              <strong>{{ __('admin/employees.warning') }}:</strong>
              <span id="limit-warning-text"></span>
            </div>
            
            <div id="new-departments-container" class="alert alert-info mt-3 hidden">
              <i class="fa fa-info-circle"></i>
              <strong>{{ __('admin/employees.new-departments-created') }}:</strong>
              <span id="new-departments-list"></span>
            </div>
          </div>
          
          <div class="preview-table-container" style="max-height: 60vh; overflow-y: auto;">
            <table class="table table-sm table-hover" id="import-preview-table">
              <thead class="thead-light sticky-top">
                <tr>
                  <th style="width: 40px;">#</th>
                  <th>{{ __('admin/employees.name') }}</th>
                  <th>{{ __('admin/employees.email') }}</th>
                  <th>{{ __('admin/employees.type') }}</th>
                  <th>{{ __('admin/employees.position') }}</th>
                  @if($enableMultiLevel)
                  <th>{{ __('admin/employees.department') }}</th>
                  @endif
                  <th>{{ __('admin/employees.wage') }}</th>
                  <th style="width: 15%;">{{ __('admin/employees.status') }}</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn btn-secondary back-to-upload">
            <i class="fa fa-arrow-left"></i>
            {{ __('admin/employees.fix-and-reupload') }}
          </button>
          
          <div class="form-check mr-auto ml-3">
            <input type="checkbox" class="form-check-input" id="send-emails-checkbox" checked>
            <label class="form-check-label" for="send-emails-checkbox">
              {{ __('admin/employees.send-password-emails') }}
            </label>
          </div>
          
          <button class="btn btn-success start-import" disabled>
            <i class="fa fa-play"></i>
            {{ __('admin/employees.start-import') }}
          </button>
        </div>
      </div>
      
      <!-- ============================================ -->
      <!-- STEP 3: PROGRESS -->
      <!-- ============================================ -->
      <div class="import-step import-step-progress hidden">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">
            <i class="fa fa-spinner fa-spin"></i>
            {{ __('admin/employees.import-in-progress') }}
          </h5>
          <small class="text-white-50">{{ __('admin/employees.please-wait') }}</small>
        </div>
        
        <div class="modal-body">
          <div class="progress-container">
            <div class="progress mb-3" style="height: 30px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" 
                   role="progressbar" 
                   style="width: 0%"
                   id="import-progress-bar">
                <span id="progress-percentage">0%</span>
              </div>
            </div>
            
            <div class="progress-stats text-center">
              <p class="lead mb-2">
                {{ __('admin/employees.processing') }}: 
                <strong>
                  <span class="progress-current">0</span> / 
                  <span class="progress-total">0</span>
                </strong>
              </p>
              <div class="row mt-3">
                <div class="col-4">
                  <span class="badge badge-success badge-lg">
                    <i class="fa fa-check"></i>
                    {{ __('admin/employees.successful') }}: 
                    <span class="progress-successful">0</span>
                  </span>
                </div>
                <div class="col-4">
                  <span class="badge badge-danger badge-lg">
                    <i class="fa fa-times"></i>
                    {{ __('admin/employees.failed') }}: 
                    <span class="progress-failed">0</span>
                  </span>
                </div>
                <div class="col-4">
                  <span class="badge badge-info badge-lg">
                    <i class="fa fa-folder"></i>
                    {{ __('admin/employees.departments-created') }}: 
                    <span class="progress-departments">0</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- ============================================ -->
      <!-- STEP 4: COMPLETE -->
      <!-- ============================================ -->
      <div class="import-step import-step-complete hidden">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">
            <i class="fa fa-check-circle"></i>
            {{ __('admin/employees.import-completed') }}
          </h5>
          <button class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        
        <div class="modal-body">
          <div class="import-summary-final text-center">
            <div class="row mb-4">
              <div class="col-md-4">
                <div class="card border-success">
                  <div class="card-body">
                    <h2 class="text-success mb-0" id="final-successful">0</h2>
                    <small>{{ __('admin/employees.successfully-imported') }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card border-danger">
                  <div class="card-body">
                    <h2 class="text-danger mb-0" id="final-failed">0</h2>
                    <small>{{ __('admin/employees.failed-imports') }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card border-info">
                  <div class="card-body">
                    <h2 class="text-info mb-0" id="final-departments">0</h2>
                    <small>{{ __('admin/employees.new-departments-created') }}</small>
                  </div>
                </div>
              </div>
            </div>
            
            <button class="btn btn-primary download-report">
              <i class="fa fa-download"></i>
              {{ __('admin/employees.download-report') }}
            </button>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn btn-secondary" data-dismiss="modal" onclick="location.reload()">
            {{ __('admin/employees.close-and-refresh') }}
          </button>
        </div>
      </div>
      
    </div>
  </div>
</div>

<style>

  employee-import-modal.modal-drawer .modal-dialog {
    width: min(520px, 100vw) !important;  /* Start: 520px drawer */
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

#employee-import-modal.modal-drawer .modal-dialog.expanded {
    width: min(95vw, 1600px) !important;  /* Preview: expand to ~95% screen or max 1600px */
}

/* Scale-in animation for preview content */
.import-step-preview {
    animation: scaleIn 0.4s ease-out;
    transform-origin: center top;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Mobile: stay full width always */
@media (max-width: 768px) {
    #employee-import-modal.modal-drawer .modal-dialog,
    #employee-import-modal.modal-drawer .modal-dialog.expanded {
        width: 100vw !important;
    }
}

.hidden { display: none !important; }

/* Mobile: don't expand too much */
@media (max-width: 768px) {
    #employee-import-modal .modal-dialog,
    #employee-import-modal .modal-dialog.expanded {
        max-width: 95vw !important;
    }
}
.upload-area {
    border: 2px dashed #ccc;
    padding: 60px 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #f8f9fa;
}
.upload-area:hover {
    border-color: #007bff;
    background: #e7f3ff;
}
.upload-area.drag-over {
    border-color: #28a745;
    background: #d4edda;
}
.row-valid { background-color: #d4edda !important; }
.row-warning { background-color: #fff3cd !important; }
.row-error { background-color: #f8d7da !important; }
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8f9fa;
}
.badge-lg {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}
/* Modal animation - smooth expansion */
#employee-import-modal .modal-dialog {
    transition: max-width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: 600px; /* Start width for upload step */
}

#employee-import-modal .modal-dialog.expanded {
    max-width: 95vw !important; /* Full width for preview */
}

/* Scale-in animation for preview content */
.import-step-preview {
    animation: scaleIn 0.4s ease-out;
    transform-origin: center top;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Mobile: don't expand too much */
@media (max-width: 768px) {
    #employee-import-modal .modal-dialog,
    #employee-import-modal .modal-dialog.expanded {
        max-width: 95vw !important;
    }
}
.hidden { display: none !important; }
</style>

<script>
(function() {
    'use strict';
    
    let validatedData = null;
    let currentJobId = null;
    let pollInterval = null;
    const enableMultiLevel = @json($enableMultiLevel);
    
    // Swal toast helper
    const showToast = function(icon, title) {
        Swal.fire({
            icon: icon,
            title: title,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    };
    
    // Template download - close loaders
    $(document).on('click', '.template-download-link', function(e) {
        setTimeout(function() {
            Swal.close();
            $('.swal2-container, .swal2-backdrop-show').remove();
            $('body').removeClass('swal2-shown swal2-height-auto');
        }, 500);
    });
    
    // File input change
    $('#import-file-input').on('change', function(e) {
        const file = e.target.files[0];
        if (file) handleFileUpload(file);
    });
    
   // Browse button
    $(document).on('click', '.browse-files-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#import-file-input').trigger('click');
        return false;
    });
    
    // Drag & Drop
    const dropzone = $('#upload-dropzone');
    
    dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        const file = e.originalEvent.dataTransfer.files[0];
        if (file) handleFileUpload(file);
    });
    
    function handleFileUpload(file) {
        // Validate file
        if (!file.name.endsWith('.xlsx')) {
            showToast('error', 'Please upload an Excel file (.xlsx)');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            showToast('error', 'File size exceeds 5MB limit');
            return;
        }
        
        // Show loading state
        dropzone.html('<i class="fa fa-spinner fa-spin fa-3x text-primary mb-3"></i><p class="lead">' + @json(__('admin/employees.reading-file')) + '</p>');
        
        const reader = new FileReader();
        
        reader.onerror = function() {
            showToast('error', 'Failed to read file');
            resetUploadZone();
        };
        
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                
                console.log('📊 Raw Excel data:', jsonData);
                
                if (jsonData.length < 2) {
                    showToast('error', 'Excel file is empty or has no data rows');
                    resetUploadZone();
                    return;
                }
                
                // Extract headers (first row) - normalize for flexible matching
                const rawHeaders = jsonData[0].map(h => h ? h.toString().trim() : '');
                const headers = rawHeaders.map(h => h.toLowerCase().replace(/[\s_-]/g, ''));
                
                console.log('📋 Raw Headers:', rawHeaders);
                console.log('📋 Normalized Headers:', headers);
                
                // Flexible header matching function
                const findColumn = function(possibleNames) {
                    for (let name of possibleNames) {
                        const normalizedName = name.toLowerCase().replace(/[\s_-]/g, '');
                        const idx = headers.indexOf(normalizedName);
                        if (idx !== -1) return idx;
                    }
                    return -1;
                };
                
                // Map column indices with multiple possible names
                const columnMap = {
    name: findColumn(['név', 'name', 'nev', 'fullname', 'teljes név', 'teljesnev', 'employee name', 'employeename']),
    email: findColumn(['email', 'e-mail', 'e mail', 'emailcím', 'emailcim', 'cím', 'cim', 'employee email', 'employeeemail']),
    type: findColumn(['típus', 'type', 'tipus', 'role', 'szerep', 'pozíció típusa', 'poziciotipusa', 'employee type', 'employeetype']),
    position: findColumn(['beosztás', 'position', 'beosztas', 'title', 'munkakör', 'munkakor', 'positition']),
    department_name: findColumn(['részleg', 'department', 'reszleg', 'dept', 'osztály', 'osztaly', 'departmentname', 'részleg neve', 'reszlegneve']),
    wage: findColumn(['bér', 'wage', 'ber', 'salary', 'fizetés', 'fizetes', 'mothly net wage', 'mothlynetwage', 'monthly net wage', 'monthlynetwage', 'net wage', 'netwage']),
    currency: findColumn(['valuta', 'currency', 'pénznem', 'penznem', 'curr'])
};
                
                console.log('🗺️ Column mapping:', columnMap);
                
                // Check for required columns
                const missingColumns = [];
                if (columnMap.name === -1) missingColumns.push('Név/Name');
                if (columnMap.email === -1) missingColumns.push('E-mail/Email');
                if (columnMap.type === -1) missingColumns.push('Típus/Type');
                
                if (missingColumns.length > 0) {
                    console.error('❌ Missing required columns:', missingColumns);
                    showToast('error', 'Missing required columns: ' + missingColumns.join(', ') + '<br>Found headers: ' + rawHeaders.join(', '));
                    resetUploadZone();
                    return;
                }
                
                // Normalize data rows (skip header)
                const normalizedData = jsonData.slice(1).map(row => {
                    return {
                        name: columnMap.name !== -1 ? (row[columnMap.name] || '').toString().trim() : '',
                        email: columnMap.email !== -1 ? (row[columnMap.email] || '').toString().trim() : '',
                        type: columnMap.type !== -1 ? (row[columnMap.type] || '').toString().trim().toLowerCase() : '',
                        position: columnMap.position !== -1 ? (row[columnMap.position] || '').toString().trim() : '',
                        department_name: columnMap.department_name !== -1 ? (row[columnMap.department_name] || '').toString().trim() : '',
                        wage: columnMap.wage !== -1 ? (row[columnMap.wage] || '').toString().trim() : '',
                        currency: columnMap.currency !== -1 ? (row[columnMap.currency] || 'HUF').toString().trim().toUpperCase() : 'HUF'
                    };
                });
                
                console.log('✅ Normalized data (first 3 rows):', normalizedData.slice(0, 3));
                
                // Filter out empty rows
                const filteredData = normalizedData.filter(row => {
                    const hasName = row.name && row.name.trim() !== '';
                    const hasEmail = row.email && row.email.trim() !== '';
                    const hasType = row.type && row.type.trim() !== '';
                    
                    return hasName || hasEmail || hasType;
                });
                
                console.log(`🧹 Filtered: ${normalizedData.length} rows → ${filteredData.length} rows`);
                
                if (filteredData.length === 0) {
                    showToast('error', 'No valid data rows found in the Excel file!');
                    resetUploadZone();
                    return;
                }
                
                validateData(filteredData);
                
            } catch (error) {
                console.error('❌ File parsing error:', error);
                showToast('error', @json(__('admin/employees.error-parsing-file')) + '<br>' + error.message);
                resetUploadZone();
            }
        };
        
        reader.readAsArrayBuffer(file);
    }
    
    function resetUploadZone() {
        dropzone.html(
            '<i class="fa fa-cloud-upload fa-3x text-muted mb-3"></i>' +
            '<p class="lead">' + @json(__('admin/employees.drag-drop-file')) + '</p>' +
            '<p class="text-muted">' + @json(__('admin/employees.or')) + '</p>' +
            '<button type="button" class="btn btn-secondary browse-files-btn">' +
            '<i class="fa fa-folder-open"></i> ' + @json(__('admin/employees.browse-files')) +
            '</button>' +
            '<small class="form-text text-muted mt-2">' + @json(__('admin/employees.max-file-size')) + '</small>'
        );
    }
    
    function validateData(fileData) {
        $.ajax({
            url: @json(route('admin.employee.import.validate')),
            method: 'POST',
            data: {
                _token: @json(csrf_token()),
                file_data: fileData
            },
            success: function(response) {
                validatedData = response;
                showPreviewView(response);
            },
            error: function(xhr) {
                showToast('error', @json(__('admin/employees.error-validation-failed')));
                resetUploadZone();
            }
        });
    }
    
   // Find this section in employee-import.blade.php (around line 626)
// REPLACE the entire showPreviewView function with this corrected version:

function showPreviewView(data) {
    // Expand modal to full width
    $('#employee-import-modal .modal-dialog').addClass('expanded');
    
    // Wait for animation, then switch views
    setTimeout(function() {
        $('.import-step-upload').addClass('hidden');
        $('.import-step-preview').removeClass('hidden');
        
        // Populate summary
        $('#summary-total').text(data.summary.total_rows);
        $('#summary-valid').text(data.summary.valid_rows);
        $('#summary-warning').text(data.summary.warning_rows);
        $('#summary-error').text(data.summary.error_rows);
        
        // Show limit warning if needed
        if (data.show_limit_warning) {
            $('#limit-warning-container').removeClass('hidden');
            
            // ✅ FIXED: Build the warning text properly
            const warningText = 'Az import után túllépi a munkavállalói limitet (' + 
                data.limit_info.limit + '). Jelenlegi: ' + 
                data.limit_info.current + ', Import után: ' + 
                data.limit_info.total_after;
            
            $('#limit-warning-text').text(warningText);
        }
        
        // Show new departments if any
        if (data.new_departments_list && data.new_departments_list.length > 0) {
            $('#new-departments-container').removeClass('hidden');
            $('#new-departments-list').text(data.new_departments_list.join(', '));
        }
        
        // Populate table
        populatePreviewTable(data.rows);
    }, 200);
}
    
    function populatePreviewTable(rows) {
        const tbody = $('#import-preview-table tbody');
        tbody.empty();
        
        rows.forEach(function(row) {
            const rowClass = row.status === 'valid' ? 'row-valid' : 
                           row.status === 'warning' ? 'row-warning' : 'row-error';
            
            const messages = row.messages.map(msg => 
                '<span class="badge badge-' + (row.status === 'error' ? 'danger' : 'warning') + '">' + msg + '</span>'
            ).join(' ');
            
            const tr = $('<tr>').addClass(rowClass);
            tr.append('<td>' + row.row_number + '</td>');
            tr.append('<td>' + (row.data.name || '-') + '</td>');
            tr.append('<td>' + (row.data.email || '-') + '</td>');
            tr.append('<td><span class="badge badge-secondary">' + (row.data.type || '-') + '</span></td>');
            tr.append('<td>' + (row.data.position || '-') + '</td>');
            
            if (enableMultiLevel) {
                tr.append('<td>' + (row.data.department_name || '-') + '</td>');
            }
            
            const wageDisplay = row.data.wage ? row.data.wage + ' ' + (row.data.currency || 'HUF') : '-';
            tr.append('<td>' + wageDisplay + '</td>');
            tr.append('<td>' + (messages || '<span class="badge badge-success">✓ OK</span>') + '</td>');
            
            tbody.append(tr);
        });
        
        $('.start-import').prop('disabled', !validatedData.valid);
    }
    
    $('.back-to-upload').on('click', function() {
        $('#employee-import-modal .modal-dialog').removeClass('expanded');
        
        setTimeout(function() {
            $('.import-step-preview').addClass('hidden');
            $('.import-step-upload').removeClass('hidden');
        }, 200);
        
        resetUploadZone();
        validatedData = null;
        $('#import-file-input').val('');
    });
    
    $('.start-import').on('click', function() {
        if (!validatedData || !validatedData.valid) {
            showToast('error', @json(__('admin/employees.error-cannot-start-import')));
            return;
        }
        
        const sendEmails = $('#send-emails-checkbox').is(':checked') ? 1 : 0;
        const dataToImport = validatedData.rows.filter(row => 
            row.status === 'valid' || row.status === 'warning'
        );
        
        $.ajax({
            url: @json(route('admin.employee.import.start')),
            method: 'POST',
            data: {
                _token: @json(csrf_token()),
                validated_data: dataToImport,
                send_emails: sendEmails
            },
            success: function(response) {
                currentJobId = response.job_id;
                showProgressView();
                startPolling();
            },
            error: function(xhr) {
                showToast('error', @json(__('admin/employees.error-start-failed')));
            }
        });
    });
    
    function showProgressView() {
        $('.import-step-preview').addClass('hidden');
        $('.import-step-progress').removeClass('hidden');
        
        $('.progress-total').text(validatedData.summary.total_rows);
        $('.progress-current').text(0);
        $('.progress-successful').text(0);
        $('.progress-failed').text(0);
        $('.progress-departments').text(0);
        $('#import-progress-bar').css('width', '0%');
        $('#progress-percentage').text('0%');
    }
    
    function startPolling() {
        pollInterval = setInterval(function() {
            $.ajax({
                url: @json(url('admin/employee/import')) + '/' + currentJobId + '/status',
                method: 'GET',
                success: function(response) {
                    updateProgress(response);
                    
                    if (response.status === 'completed' || response.status === 'failed') {
                        clearInterval(pollInterval);
                        showCompleteView(response);
                    }
                },
                error: function(xhr) {
                    clearInterval(pollInterval);
                    showToast('error', @json(__('admin/employees.error-polling-failed')));
                }
            });
        }, 3000);
    }
    
    function updateProgress(data) {
        const percentage = data.progress.percentage;
        
        $('.progress-current').text(data.progress.processed);
        $('.progress-successful').text(data.progress.successful);
        $('.progress-failed').text(data.progress.failed);
        $('.progress-departments').text(data.departments_created || 0);
        
        $('#import-progress-bar').css('width', percentage + '%');
        $('#progress-percentage').text(Math.round(percentage) + '%');
    }
    
    // ✅ FIX BUG #2: Export updateProgress as window.updateProgressDisplay
    window.updateProgressDisplay = updateProgress;
    
    function showCompleteView(data) {
        $('.import-step-progress').addClass('hidden');
        $('.import-step-complete').removeClass('hidden');
        
        $('#final-successful').text(data.progress.successful);
        $('#final-failed').text(data.progress.failed);
        $('#final-departments').text(data.departments_created || 0);
        
        if (data.progress.failed > 0) {
            showToast('warning', @json(__('admin/employees.import-completed-with-errors')));
        } else {
            showToast('success', @json(__('admin/employees.import-completed-success')));
        }
    }
    
    $('.download-report').on('click', function() {
        window.location.href = @json(url('admin/employee/import')) + '/' + currentJobId + '/report';
    });
    
    $('#employee-import-modal').on('hidden.bs.modal', function() {
        if (pollInterval) clearInterval(pollInterval);
        
        $('#employee-import-modal .modal-dialog').removeClass('expanded');
        
        $('.import-step').addClass('hidden');
        $('.import-step-upload').removeClass('hidden');
        validatedData = null;
        currentJobId = null;
        resetUploadZone();
        $('#import-file-input').val('');
    });
    
    // ✅ FIX BUG #1: Add window.reopenImportModal function
    window.reopenImportModal = function(jobId) {
        console.log('🔄 Reopening import modal for job:', jobId);
        
        // Set the current job ID
        currentJobId = jobId;
        
        // Load job status
        $.ajax({
            url: @json(url('admin/employee/import')) + '/' + jobId + '/status',
            method: 'GET',
            success: function(response) {
                // Open the modal
                $('#employee-import-modal').modal('show');
                $('#employee-import-modal .modal-dialog').addClass('expanded');

                
                // Hide all steps first
                $('.import-step').addClass('hidden');
                
                // Show appropriate step based on status
                if (response.status === 'completed' || response.status === 'failed') {
                    // Show complete view
                    $('.import-step-complete').removeClass('hidden');
                    $('#final-successful').text(response.progress.successful);
                    $('#final-failed').text(response.progress.failed);
                    $('#final-departments').text(response.departments_created || 0);
                    
                    if (response.progress.failed > 0) {
                        showToast('warning', @json(__('admin/employees.import-completed-with-errors')));
                    } else {
                        showToast('success', @json(__('admin/employees.import-completed-success')));
                    }
                } else {
                    // Show progress view and start polling
                    $('.import-step-progress').removeClass('hidden');
                    
                    // Update initial progress
                    updateProgress(response);
                    
                    // Start polling for updates
                    if (pollInterval) clearInterval(pollInterval);
                    startPolling();
                }
            },
            error: function(xhr) {
                console.error('Failed to load import status:', xhr);
                showToast('error', 'Failed to load import status');
            }
        });
    };
    
})();
</script>