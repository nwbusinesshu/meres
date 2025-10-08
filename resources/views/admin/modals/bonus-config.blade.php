{{-- resources/views/admin/modals/bonus-config.blade.php --}}
<div class="modal fade modal-drawer" id="bonus-config-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin/bonuses.configure-multipliers') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    {{ __('admin/bonuses.multiplier-help-text') }}
                </p>
                
                <div class="multiplier-list" id="multiplier-list">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetMultipliersToDefault()">
                    <i class="fa fa-undo"></i> {{ __('admin/bonuses.reset-defaults') }}
                </button>
                <button type="button" class="btn btn-primary" onclick="saveMultiplierConfig()">
                    <i class="fa fa-save"></i> {{ __('global.save') }}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Bonus Config Modal Specific Styles */
#bonus-config-modal .modal-body {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    padding: 1.5rem;
}

#bonus-config-modal .modal-footer {
    position: sticky;
    bottom: 0;
    background: white;
    border-top: 1px solid #dee2e6;
    z-index: 10;
}

.multiplier-item {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
}

.multiplier-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0,123,255,0.1);
}

.multiplier-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.level-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.875rem;
}

.level-badge.malus {
    background-color: #ffc107;
    color: #000;
}

.level-badge.neutral {
    background-color: #6c757d;
    color: #fff;
}

.level-badge.bonus {
    background-color: #28a745;
    color: #fff;
}

.level-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #495057;
}

.multiplier-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}

.multiplier-slider-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.multiplier-slider {
    flex: 1;
    -webkit-appearance: none;
    appearance: none;
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(to right, #dc3545 0%, #ffc107 25%, #28a745 50%, #17a2b8 100%);
    outline: none;
    cursor: pointer;
}

.multiplier-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #007bff;
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.2s ease;
}

.multiplier-slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
    background: #0056b3;
}

.multiplier-slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #007bff;
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.2s ease;
}

.multiplier-slider::-moz-range-thumb:hover {
    transform: scale(1.2);
    background: #0056b3;
}

.slider-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.quick-buttons {
    display: flex;
    gap: 0.25rem;
}

.quick-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-btn:hover {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

/* Category separators */
.category-separator {
    display: flex;
    align-items: center;
    margin: 1.5rem 0 1rem 0;
    font-weight: 600;
    color: #495057;
}

.category-separator::before,
.category-separator::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #dee2e6;
}

.category-separator::before {
    margin-right: 1rem;
}

.category-separator::after {
    margin-left: 1rem;
}
</style>

<script>
// Level names and categories
const levelData = {
    1: { name: 'M04', category: 'malus', label: 'Malus 4' },
    2: { name: 'M03', category: 'malus', label: 'Malus 3' },
    3: { name: 'M02', category: 'malus', label: 'Malus 2' },
    4: { name: 'M01', category: 'malus', label: 'Malus 1' },
    5: { name: 'A00', category: 'neutral', label: 'Alapszint' },
    6: { name: 'B01', category: 'bonus', label: 'Bónusz 1' },
    7: { name: 'B02', category: 'bonus', label: 'Bónusz 2' },
    8: { name: 'B03', category: 'bonus', label: 'Bónusz 3' },
    9: { name: 'B04', category: 'bonus', label: 'Bónusz 4' },
    10: { name: 'B05', category: 'bonus', label: 'Bónusz 5' },
    11: { name: 'B06', category: 'bonus', label: 'Bónusz 6' },
    12: { name: 'B07', category: 'bonus', label: 'Bónusz 7' },
    13: { name: 'B08', category: 'bonus', label: 'Bónusz 8' },
    14: { name: 'B09', category: 'bonus', label: 'Bónusz 9' },
    15: { name: 'B10', category: 'bonus', label: 'Bónusz 10' }
};

// Default Hungarian multipliers
const defaultMultipliers = {
    1: 0.00, 2: 0.40, 3: 0.70, 4: 0.90, 5: 1.00,
    6: 1.50, 7: 2.00, 8: 2.75, 9: 3.50, 10: 4.25,
    11: 5.25, 12: 6.25, 13: 7.25, 14: 8.25, 15: 9.25
};

function openMultiplierConfigModal() {
    $.ajax({
        url: '{{ route("admin.bonuses.config.get") }}',
        method: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.ok) {
                populateMultiplierModal(response.config);
                $('#bonus-config-modal').modal('show');
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: '{{ __("global.error") }}',
                text: '{{ __("admin/bonuses.config-load-failed") }}'
            });
        }
    });
}

function populateMultiplierModal(config) {
    const $list = $('#multiplier-list');
    $list.empty();
    
    let currentCategory = null;
    
    config.forEach(item => {
        const level = item.level;
        const data = levelData[level];
        
        // Add category separator
        if (data.category !== currentCategory) {
            currentCategory = data.category;
            let categoryName = '';
            if (currentCategory === 'malus') categoryName = '{{ __("admin/bonuses.malus-levels") }}';
            else if (currentCategory === 'neutral') categoryName = '{{ __("admin/bonuses.neutral-level") }}';
            else if (currentCategory === 'bonus') categoryName = '{{ __("admin/bonuses.bonus-levels") }}';
            
            $list.append(`<div class="category-separator">${categoryName}</div>`);
        }
        
        const multiplier = parseFloat(item.multiplier);
        
        $list.append(`
            <div class="multiplier-item">
                <div class="multiplier-header">
                    <div>
                        <span class="level-badge ${data.category}">${level}. ${data.name}</span>
                        <span class="level-name ms-2">${data.label}</span>
                    </div>
                    <div class="multiplier-value">
                        <span class="value-display" data-level="${level}">${multiplier.toFixed(2)}x</span>
                    </div>
                </div>
                <div class="multiplier-slider-container">
                    <input type="range" 
                           class="multiplier-slider" 
                           data-level="${level}"
                           min="0" 
                           max="15" 
                           step="0.25" 
                           value="${multiplier}"
                           oninput="updateMultiplierValue(${level}, this.value)">
                    <div class="quick-buttons">
                        <button class="quick-btn" onclick="setQuickValue(${level}, 0)" title="0x">0</button>
                        <button class="quick-btn" onclick="setQuickValue(${level}, 1)" title="1x">1</button>
                        <button class="quick-btn" onclick="setQuickValue(${level}, 5)" title="5x">5</button>
                        <button class="quick-btn" onclick="setQuickValue(${level}, 10)" title="10x">10</button>
                    </div>
                </div>
                <div class="slider-labels">
                    <span>0x</span>
                    <span>5x</span>
                    <span>10x</span>
                    <span>15x</span>
                </div>
            </div>
        `);
    });
}

function updateMultiplierValue(level, value) {
    const formattedValue = parseFloat(value).toFixed(2);
    $(`.value-display[data-level="${level}"]`).text(formattedValue + 'x');
}

function setQuickValue(level, value) {
    const $slider = $(`.multiplier-slider[data-level="${level}"]`);
    $slider.val(value);
    updateMultiplierValue(level, value);
}

function saveMultiplierConfig() {
    const multipliers = [];
    $('.multiplier-slider').each(function() {
        multipliers.push({
            level: parseInt($(this).data('level')),
            multiplier: parseFloat($(this).val())
        });
    });

    $.ajax({
        url: '{{ route("admin.bonuses.config.save") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            config: multipliers
        },
        success: function(response) {
            if (response.ok) {
                $('#bonus-config-modal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '{{ __("global.success") }}',
                    text: '{{ __("admin/bonuses.config-saved") }}',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: '{{ __("global.error") }}',
                text: '{{ __("admin/bonuses.config-save-error") }}'
            });
        }
    });
}

function resetMultipliersToDefault() {
    Swal.fire({
        title: '{{ __("admin/bonuses.reset-confirm-title") }}',
        text: '{{ __("admin/bonuses.reset-confirm-text") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("global.yes") }}',
        cancelButtonText: '{{ __("global.no") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            // Reset all sliders to default values
            Object.keys(defaultMultipliers).forEach(level => {
                const value = defaultMultipliers[level];
                const $slider = $(`.multiplier-slider[data-level="${level}"]`);
                $slider.val(value);
                updateMultiplierValue(level, value);
            });
            
            Swal.fire({
                icon: 'success',
                title: '{{ __("admin/bonuses.reset-success") }}',
                text: '{{ __("admin/bonuses.reset-success-text") }}',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}
</script>