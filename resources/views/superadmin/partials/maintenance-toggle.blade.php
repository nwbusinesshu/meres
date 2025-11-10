{{-- resources/views/superadmin/partials/maintenance-toggle.blade.php --}}
<div class="tile maintenance-toggle-tile">
    <div class="tile-header">
        <h3>
            <i class="fa fa-wrench"></i>
            {{ __('maintenance.toggle-title') }}
        </h3>
    </div>
    <div class="tile-body">
        <p class="toggle-description">{{ __('maintenance.toggle-description') }}</p>
        
        <div class="maintenance-status-container">
            <div class="status-indicator">
                <span class="status-label">{{ __('global.status') }}:</span>
                <span id="maintenance-status-text" class="status-text">
                    <i class="fa fa-spinner fa-spin"></i> {{ __('global.loading') }}...
                </span>
            </div>
            
            <button type="button" 
                    id="btn-toggle-maintenance" 
                    class="btn btn-lg maintenance-toggle-btn"
                    disabled>
                <i class="fa fa-spinner fa-spin"></i>
                <span class="btn-text">{{ __('global.loading') }}...</span>
            </button>
        </div>
    </div>
</div>

<style>
.maintenance-toggle-tile {
    margin-bottom: 2rem;
    background: var(--white);
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.maintenance-toggle-tile .tile-header {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--silver_chalice_light);
}

.maintenance-toggle-tile .tile-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--mine_shaft);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-toggle-tile .toggle-description {
    color: var(--silver_chalice);
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.maintenance-status-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-label {
    font-weight: 600;
    color: var(--mine_shaft);
}

.status-text {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.status-text.status-enabled {
    background: #fef3c7;
    color: #92400e;
}

.status-text.status-disabled {
    background: #d1fae5;
    color: #065f46;
}

.maintenance-toggle-btn {
    min-width: 150px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.maintenance-toggle-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.maintenance-toggle-btn.btn-enable {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    border: none;
}

.maintenance-toggle-btn.btn-enable:hover:not(:disabled) {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
}

.maintenance-toggle-btn.btn-disable {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}

.maintenance-toggle-btn.btn-disable:hover:not(:disabled) {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

@media (max-width: 768px) {
    .maintenance-status-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .maintenance-toggle-btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusText = document.getElementById('maintenance-status-text');
    const toggleBtn = document.getElementById('btn-toggle-maintenance');
    
    if (!statusText || !toggleBtn) {
        return; // Elements not found, probably not on superadmin dashboard
    }

    const T = {
        loading: @json(__('global.loading')),
        enabled: @json(__('maintenance.enabled-success')),
        disabled: @json(__('maintenance.disabled-success')),
        error: @json(__('maintenance.toggle-error')),
        confirm_enable_title: @json(__('maintenance.confirm-enable-title')),
        confirm_enable_text: @json(__('maintenance.confirm-enable-text')),
        confirm_disable_title: @json(__('maintenance.confirm-disable-title')),
        confirm_disable_text: @json(__('maintenance.confirm-disable-text')),
        enable_button: @json(__('maintenance.enable-button')),
        disable_button: @json(__('maintenance.disable-button')),
        yes: @json(__('global.swal-confirm')),
        no: @json(__('global.swal-cancel')),
        status_enabled: @json(__('maintenance.banner-title')),
        status_disabled: @json(__('global.subscription-free')) // Using "Free" as "Active"
    };

    let currentStatus = null;

    // Fetch current status
    function fetchStatus() {
        toggleBtn.disabled = true;
        statusText.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + T.loading + '...';

        fetch('{{ route("superadmin.maintenance.status") }}', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentStatus = data.is_down;
                updateUI(data.is_down);
            }
        })
        .catch(error => {
            console.error('Error fetching maintenance status:', error);
            statusText.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            toggleBtn.disabled = false;
        });
    }

    // Update UI based on status
    function updateUI(isDown) {
        // Update status text
        statusText.className = 'status-text ' + (isDown ? 'status-enabled' : 'status-disabled');
        statusText.innerHTML = isDown ? 
            '<i class="fa fa-wrench"></i> ' + T.status_enabled : 
            '<i class="fa fa-check-circle"></i> ' + T.status_disabled;

        // Update button
        toggleBtn.disabled = false;
        toggleBtn.className = 'btn btn-lg maintenance-toggle-btn ' + (isDown ? 'btn-disable' : 'btn-enable');
        toggleBtn.innerHTML = '<i class="fa fa-power-off"></i> <span class="btn-text">' + 
            (isDown ? T.disable_button : T.enable_button) + '</span>';
    }

    // Toggle maintenance mode
    function toggleMaintenance() {
        const isDown = currentStatus;
        
        swal({
            title: isDown ? T.confirm_disable_title : T.confirm_enable_title,
            text: isDown ? T.confirm_disable_text : T.confirm_enable_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: T.yes,
            cancelButtonText: T.no,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                performToggle();
            }
        });
    }

    // Perform the actual toggle
    function performToggle() {
        toggleBtn.disabled = true;
        toggleBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <span class="btn-text">' + T.loading + '...</span>';

        fetch('{{ route("superadmin.maintenance.toggle") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentStatus = data.status === 'enabled';
                updateUI(currentStatus);
                
                toast('success', data.message);
                
                // Refresh page after a short delay to show updated banner
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                toast('error', data.message || T.error);
                toggleBtn.disabled = false;
                updateUI(currentStatus);
            }
        })
        .catch(error => {
            console.error('Error toggling maintenance mode:', error);
            toast('error', T.error);
            toggleBtn.disabled = false;
            updateUI(currentStatus);
        });
    }

    // Attach event listener
    toggleBtn.addEventListener('click', toggleMaintenance);

    // Initial status fetch
    fetchStatus();
});
</script>