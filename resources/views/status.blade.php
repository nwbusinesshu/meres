@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-1">System Status</h1>
            <p class="text-muted mb-0">
                Real-time monitoring • Auto-refresh every 5 minutes • 
                <span id="last-updated">{{ now()->format('Y-m-d H:i:s') }}</span>
            </p>
        </div>
    </div>

    <!-- Status Tiles -->
    <div class="row">
        <div class="col-12">
            @foreach(['openai', 'barion', 'billingo', 'app_api', 'application'] as $serviceName)
                @php
                    $check = $latest[$serviceName] ?? null;
                    $status = $check->status ?? 'unknown';
                    $displayName = match($serviceName) {
                        'openai' => 'NWB AI connection',
                        'barion' => 'Barion Payment API',
                        'billingo' => 'Billingo Invoice API',
                        'app_api' => 'Application API',
                        'application' => 'Application Core',
                        default => ucfirst($serviceName)
                    };
                    $tileClass = match($status) {
                        'ok' => 'tile-success',
                        'slow' => 'tile-warning',
                        'very_slow' => 'tile-warning',
                        'down' => 'tile-danger',
                        default => 'tile-info'
                    };
                    $icon = match($serviceName) {
                        'openai' => 'bi-cpu',
                        'barion' => 'bi-credit-card',
                        'billingo' => 'bi-receipt',
                        'app_api' => 'bi-server',
                        'application' => 'bi-gear',
                        default => 'bi-circle'
                    };
                @endphp
                
                <div class="tile {{ $tileClass }} mb-3 status-tile" data-service="{{ $serviceName }}">
                    <div class="status-tile-header">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi {{ $icon }} fs-2"></i>
                            <div>
                                <h4 class="mb-1">{{ $displayName }}</h4>
                                <div class="d-flex gap-4 align-items-center">
                                    <div class="service-status">
                                        <span class="text-muted small">Status:</span>
                                        <strong class="ms-1 status-text">{{ ucfirst(str_replace('_', ' ', $status)) }}</strong>
                                    </div>
                                    @if($check && $check->response_time_ms)
                                        <div class="service-time">
                                            <span class="text-muted small">Response Time:</span>
                                            <strong class="ms-1 response-time-value">{{ $check->response_time_ms }}ms</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 24h History Bar -->
                    <div class="history-bar-container">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">24 Hour History</small>
                            <small class="text-muted history-count" data-service="{{ $serviceName }}">-</small>
                        </div>
                        <div class="history-bar" data-service="{{ $serviceName }}">
                            <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let refreshInterval = null;

// Refresh status
function refreshStatus() {
    fetch('{{ route('status.data') }}')
        .then(response => response.json())
        .then(data => {
            updateTiles(data.services);
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
        })
        .catch(error => console.error('Error:', error));
}

// Update status tiles
function updateTiles(services) {
    services.forEach(service => {
        const tile = document.querySelector(`[data-service="${service.name}"]`);
        if (!tile) return;
        
        // Remove old tile classes
        tile.classList.remove('tile-success', 'tile-warning', 'tile-danger', 'tile-info');
        
        // Add new class based on status
        const tileClass = getTileClass(service.status);
        tile.classList.add(tileClass);
        
        // Update status text
        const statusSpan = tile.querySelector('.service-status');
        if (statusSpan) {
            statusSpan.innerHTML = '<strong>Status:</strong> ' + 
                service.status.charAt(0).toUpperCase() + service.status.slice(1).replace('_', ' ');
        }
        
        // Update response time
        const timeSpan = tile.querySelector('.response-time-value');
        if (timeSpan && service.response_time) {
            timeSpan.textContent = service.response_time + 'ms';
        }
    });
}

function getTileClass(status) {
    switch(status) {
        case 'ok': return 'tile-success';
        case 'slow':
        case 'very_slow': return 'tile-warning';
        case 'down': return 'tile-danger';
        default: return 'tile-info';
    }
}

// Load history bars
function loadHistoryBars() {
    fetch('{{ route('status.history') }}')
        .then(response => response.json())
        .then(data => {
            renderHistoryBars(data.timeline);
        })
        .catch(error => {
            console.error('Error:', error);
            // Show error in bars
            document.querySelectorAll('.history-bar').forEach(bar => {
                bar.innerHTML = '<small class="text-danger">Error loading history</small>';
            });
        });
}

// Render history bars
function renderHistoryBars(timeline) {
    Object.keys(timeline).forEach(serviceName => {
        const history = timeline[serviceName];
        const barContainer = document.querySelector(`.history-bar[data-service="${serviceName}"]`);
        const countContainer = document.querySelector(`.history-count[data-service="${serviceName}"]`);
        
        if (!barContainer) return;
        
        // Clear loading spinner
        barContainer.innerHTML = '';
        
        if (history.length === 0) {
            barContainer.innerHTML = '<small class="text-muted">No history data</small>';
            if (countContainer) countContainer.textContent = '0 checks';
            return;
        }
        
        // Update check count
        if (countContainer) {
            countContainer.textContent = `${history.length} checks`;
        }
        
        // Sort by checked_at ascending (oldest first) - so newest is on the right
        const sortedHistory = [...history].sort((a, b) => {
            return new Date(a.checked_at) - new Date(b.checked_at);
        });
        
        // Create bars for each check
        sortedHistory.forEach(check => {
            const bar = document.createElement('div');
            bar.className = `history-bar-item status-${check.status}`;
            
            const date = new Date(check.checked_at);
            const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            const statusLabel = check.status.charAt(0).toUpperCase() + check.status.slice(1).replace('_', ' ');
            
            bar.title = `${statusLabel} - ${check.response_time_ms}ms\n${timeStr}`;
            barContainer.appendChild(bar);
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadHistoryBars();
    
    // Auto-refresh every 5 minutes
    refreshInterval = setInterval(() => {
        refreshStatus();
        loadHistoryBars();
    }, 300000);
});
</script>
@endsection
