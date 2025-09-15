<div class="modal fade" tabindex="-1" role="dialog" id="network-modal">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-network-wired mr-2"></i>
                    Cégkapcsolati háló
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Network Controls -->
                <div class="network-controls p-3 border-bottom">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="small text-muted">Layout</label>
                            <select id="layout-select" class="form-control form-control-sm">
                                <option value="cose">Force-directed (COSE)</option>
                                <option value="circle">Circle</option>
                                <option value="grid">Grid</option>
                                <option value="breadthfirst">Hierarchical</option>
                                <option value="concentric">Concentric</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Filter by Department</label>
                            <select id="department-filter" class="form-control form-control-sm">
                                <option value="">All Departments</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Actions</label>
                            <div>
                                <button id="fit-network" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-expand-arrows-alt"></i> Fit to View
                                </button>
                                <button id="reset-network" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Visualization Container -->
                <div id="cy-container" style="height: 600px; width: 100%;"></div>

                <!-- Network Legend -->
                <div class="network-legend p-3 border-top bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-2">Node Types</h6>
                            <div class="legend-items">
                                <div class="legend-item">
                                    <span class="legend-node ceo"></span>
                                    <span>CEO</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-node manager"></span>
                                    <span>Manager</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-node normal"></span>
                                    <span>Employee</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Relationship Types</h6>
                            <div class="legend-items">
                                <div class="legend-item">
                                    <span class="legend-edge superior"></span>
                                    <span>Superior</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-edge colleague"></span>
                                    <span>Colleague</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-edge subordinate"></span>
                                    <span>Subordinate</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.21.1/cytoscape.min.js"></script>
<script>
// COMPLETE FIXED NETWORK MODAL INITIALIZATION

window.initNetworkModal = function() {
    console.log('Initializing network modal...');
    
    // Show the modal first
    $('#network-modal').modal('show');
    
    // Wait for modal to be shown before initializing cytoscape
    $('#network-modal').on('shown.bs.modal', function() {
        initCytoscapeNetwork();
    });
};

function initCytoscapeNetwork() {
    // FIXED: Use correct container ID from the modal HTML
    const container = document.getElementById('cy-container');
    
    if (!container) {
        console.error('Network container #cy-container not found!');
        handleNetworkError('A hálózati megjelenítő konténer nem található.');
        return;
    }

    // Initialize cytoscape if not already done
    if (!window.cy) {
        console.log('Initializing Cytoscape...');
        
        try {
            window.cy = cytoscape({
                container: container, // Use the correct container
                style: [
                    // Department containers
                    {
                        selector: 'node[type = "department"]',
                        style: {
                            'shape': 'rectangle',
                            'background-color': '#f8fafc',
                            'background-opacity': 0.7,
                            'border-width': 2,
                            'border-color': '#4f46e5',
                            'border-style': 'dashed',
                            'label': 'data(name)',
                            'text-valign': 'top',
                            'text-halign': 'center',
                            'font-size': '16px',
                            'font-weight': '700',
                            'color': '#4f46e5',
                            'text-margin-y': -15,
                            'min-width': '320px',
                            'min-height': '240px',
                            'padding': '10px'
                        }
                    },
                    // User nodes
                    {
                        selector: 'node[type != "department"]',
                        style: {
                            'background-color': '#fff',
                            'border-color': '#666',
                            'border-width': 2,
                            'label': 'data(name)',
                            'text-valign': 'center',
                            'text-halign': 'center',
                            'font-size': '12px',
                            'width': 80,
                            'height': 80,
                            'shape': 'ellipse'
                        }
                    },
                    // Manager styling
                    {
                        selector: 'node[is_manager = true]',
                        style: {
                            'border-color': '#28a745',
                            'border-width': 3,
                            'background-color': '#d4edda'
                        }
                    },
                    // CEO styling
                    {
                        selector: 'node[type = "ceo"]',
                        style: {
                            'border-color': '#dc3545',
                            'border-width': 3,
                            'background-color': '#f8d7da'
                        }
                    },
                    // Edges
                    {
                        selector: 'edge',
                        style: {
                            'width': 2,
                            'line-color': '#999',
                            'target-arrow-color': '#999',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier'
                        }
                    },
                    {
                        selector: 'edge[type = "superior"]',
                        style: {
                            'line-color': '#007bff',
                            'target-arrow-color': '#007bff',
                            'width': 3
                        }
                    },
                    {
                        selector: 'edge[type = "subordinate"]',
                        style: {
                            'line-color': '#fd7e14',
                            'target-arrow-color': '#fd7e14',
                            'width': 3
                        }
                    },
                    {
                        selector: 'edge[type = "colleague"]',
                        style: {
                            'line-color': '#20c997',
                            'target-arrow-color': '#20c997',
                            'width': 2,
                            'line-style': 'dashed'
                        }
                    }
                ],
                layout: {
                    name: 'cose',
                    animate: false,
                    fit: true,
                    padding: 50
                }
            });

            // Add event listeners
            $('#layout-select').off('change').on('change', function() {
                applyLayout($(this).val());
            });

            $('#department-filter').off('change').on('change', function() {
                filterByDepartment($(this).val());
            });

            $('#fit-network').off('click').on('click', function() {
                if (window.cy) window.cy.fit();
            });

            $('#reset-network').off('click').on('click', function() {
                if (window.cy) {
                    loadNetworkData(); // Reload data
                }
            });

            console.log('Cytoscape initialized successfully');
        } catch (error) {
            console.error('Failed to initialize Cytoscape:', error);
            handleNetworkError('Nem sikerült inicializálni a hálózati megjelenítőt: ' + error.message);
            return;
        }
    }
    
    // Load the network data
    loadNetworkData();
}

function loadNetworkData() {
    console.log('Loading network data...');
    
    if (typeof swal_loader !== 'undefined' && swal_loader.fire) {
        swal_loader.fire();
    }
    
    $.ajax({
        url: "{{ route('admin.employee.network') }}",
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        timeout: 30000 // 30 second timeout
    })
    .done(function(response) {
        console.log('Network data loaded successfully:', response);
        
        try {
            // Simple transformation for the fixed structure
            const elements = transformNetworkData(response);
            
            // Update department filter
            updateDepartmentFilter(response.departments || []);
            
            // Clear existing elements and add new ones
            if (window.cy) {
                window.cy.elements().remove();
                window.cy.add(elements);
                
                // Apply initial layout
                applyLayout('cose');
            }
            
            if (typeof swal_loader !== 'undefined' && swal_loader.close) {
                swal_loader.close();
            }
            
        } catch (error) {
            console.error('Error transforming network data:', error);
            handleNetworkError('Hiba a hálózati adatok feldolgozásakor: ' + error.message);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('Failed to load network data:', {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error
        });
        
        let errorMessage = 'Nem sikerült betölteni a hálózati adatokat.';
        
        if (xhr.status === 500) {
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage += ' Szerverhiba: ' + (response.message || 'Ismeretlen hiba');
            } catch (e) {
                errorMessage += ' Szerverhiba történt.';
            }
        } else if (xhr.status === 404) {
            errorMessage += ' Az endpoint nem található.';
        } else if (xhr.status === 0) {
            errorMessage += ' Hálózati kapcsolat megszakadt.';
        } else if (status === 'timeout') {
            errorMessage += ' A kérés túllépte az időkorlátot.';
        }
        
        handleNetworkError(errorMessage);
    });
}

function handleNetworkError(message) {
    if (typeof swal_loader !== 'undefined' && swal_loader.close) {
        swal_loader.close();
    }
    
    console.error('Network error:', message);
    
    if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire({
            title: 'Hiba',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } else {
        alert(message);
    }
}

function transformNetworkData(response) {
    console.log('Transforming network data...');
    
    const elements = [];
    
    // Add departments
    if (response.departments) {
        response.departments.forEach(dept => {
            elements.push({
                data: {
                    id: `dept_${dept.id}`,
                    name: dept.department_name,
                    type: 'department'
                }
            });
        });
    }
    
    // Add users (nodes)
    if (response.nodes) {
        response.nodes.forEach(node => {
            elements.push({
                data: {
                    id: node.id,
                    name: node.label,
                    email: node.email,
                    type: node.type,
                    department_id: node.department_id,
                    managed_dept_id: node.managed_dept_id,
                    is_manager: node.is_manager || false,
                    rater_count: node.rater_count || 0,
                    parent: node.department_id ? `dept_${node.department_id}` : undefined
                }
            });
        });
    }
    
    // Add relationships (edges)
    if (response.edges) {
        response.edges.forEach(edge => {
            elements.push({
                data: {
                    id: edge.id,
                    source: edge.source,
                    target: edge.target,
                    type: edge.type || 'colleague',
                    bidirectional: edge.bidirectional || false
                }
            });
        });
    }
    
    console.log('Transformed elements:', elements);
    return elements;
}

function updateDepartmentFilter(departments) {
    const $filter = $('#department-filter');
    $filter.empty().append('<option value="">Minden részleg</option>');
    
    departments.forEach(dept => {
        $filter.append(`<option value="${dept.id}">${dept.department_name}</option>`);
    });
}

function filterByDepartment(departmentId) {
    if (!window.cy) return;
    
    if (departmentId === '') {
        // Show all elements
        window.cy.elements().show();
    } else {
        // Hide all elements first
        window.cy.elements().hide();
        
        // Show the selected department
        window.cy.nodes(`[id = "dept_${departmentId}"]`).show();
        
        // Show users in this department
        window.cy.nodes(`[department_id = "${departmentId}"]`).show();
        window.cy.nodes(`[managed_dept_id = "${departmentId}"]`).show();
        
        // Show relevant edges
        window.cy.edges().show();
    }
    
    window.cy.fit();
}

function applyLayout(layoutName) {
    if (!window.cy) return;
    
    console.log('Applying layout:', layoutName);
    
    let layoutOptions = {
        name: layoutName,
        animate: true,
        animationDuration: 500,
        fit: true,
        padding: 50
    };
    
    switch (layoutName) {
        case 'cose':
            layoutOptions = {
                ...layoutOptions,
                nodeRepulsion: 100000,
                idealEdgeLength: 100,
                edgeElasticity: 100,
                nestingFactor: 5,
                gravity: 80,
                numIter: 1000,
                initialTemp: 200,
                coolingFactor: 0.95,
                minTemp: 1.0
            };
            break;
        case 'circle':
            layoutOptions.radius = 200;
            break;
        case 'grid':
            layoutOptions.spacingFactor = 2;
            break;
        case 'breadthfirst':
            layoutOptions.directed = true;
            layoutOptions.spacingFactor = 1.5;
            break;
        case 'concentric':
            layoutOptions.concentric = function(node) {
                return node.degree();
            };
            layoutOptions.levelWidth = function(nodes) {
                return 2;
            };
            break;
    }
    
    const layout = window.cy.layout(layoutOptions);
    layout.run();
}
</script>

<style>
/* Improved Network Modal Styles - Cleaner, More Modern Design */
#network-modal .modal-dialog {
    max-width: 1500px;
    margin: 1rem auto;
}

#network-modal .modal-content {
    border-left: 4px solid var(--pelorous);
    border-radius: 0;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    background: #ffffff;
    overflow: hidden;
}

#network-modal .modal-header {
    color: var(--silver_chalice);
    border-radius: 0;
    border-bottom: none;
    padding: 2rem;
}

#network-modal .modal-header .modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

#network-modal .modal-header .close {
    color: grey;
    opacity: 0.9;
    text-shadow: none;
    font-size: 1.75rem;
    transition: all 0.3s ease;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#network-modal .modal-header .close:hover {
    opacity: 1;
    transform: scale(1.1);
    background: rgba(255, 255, 255, 0.1);
}

/* Network Controls - Ultra Modern Design */
.network-controls {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #cbd5e0;
    padding: 2rem;
}

.network-controls label {
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: block;
    color: #2d3748;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.network-controls .form-control-sm {
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    font-weight: 600;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
}

.network-controls .form-control-sm:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
    outline: none;
    transform: translateY(-1px);
}

.network-controls .btn-sm {
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
    margin-right: 0.75rem;
    transition: all 0.3s ease;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-width: 2px;
}

.network-controls .btn-outline-primary {
    border-color: #667eea;
    color: #667eea;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.network-controls .btn-outline-primary:hover {
    background-color: #667eea;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3);
}

.network-controls .btn-outline-secondary {
    border-color: #718096;
    color: #718096;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.network-controls .btn-outline-secondary:hover {
    background-color: #718096;
    border-color: #718096;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(113, 128, 150, 0.3);
}

/* Cytoscape Container - Premium Background */
#cy-container {
    border: none;
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    position: relative;
    overflow: hidden;
    border-radius: 0;
    box-shadow: inset 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

#cy-container:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 25% 25%, rgba(102, 126, 234, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(118, 75, 162, 0.05) 0%, transparent 50%),
        linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
    pointer-events: none;
    z-index: 0;
}

/* Network Legend - Premium Design */
.network-legend {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-top: 1px solid #cbd5e0;
    padding: 2rem;

}

.network-legend h6 {
    color: #2d3748;
    font-weight: 700;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 1.5rem;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
    color: #4a5568;
    font-weight: 600;
    padding: 0.5rem 1rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Legend Nodes - Premium Styling */
.legend-node {
    width: 28px;
    height: 28px;

    display: inline-block;
    border: 3px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.legend-node.ceo {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.legend-node.manager {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

.legend-node.normal {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

/* Legend Edges - Premium Styling */
.legend-edge {
    width: 36px;
    height: 5px;
    display: inline-block;

    position: relative;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.legend-edge:after {
    content: '';
    position: absolute;
    right: -5px;
    top: -6px;
    width: 0;
    height: 0;
    border-left: 10px solid;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    filter: drop-shadow(2px 2px 2px rgba(0, 0, 0, 0.1));
}

.legend-edge.superior {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.legend-edge.superior:after {
    border-left-color: #0056b3;
}

.legend-edge.colleague {
    background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
    opacity: 0.8;
}

.legend-edge.colleague:after {
    border-left-color: #17a2b8;
}

.legend-edge.subordinate {
    background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%);
}

.legend-edge.subordinate:after {
    border-left-color: #e55a00;
}

/* Row styling improvements */
.network-controls .row {
    align-items: end;
}

.network-controls .row > div {
    margin-bottom: 0;
}

/* Additional responsive improvements */
@media (max-width: 768px) {
    #network-modal .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
    }
    
    .network-controls {
        padding: 1.5rem;
    }
    
    .network-controls .row > div {
        margin-bottom: 1.5rem;
    }
    
    .legend-items {
        flex-direction: column;
        gap: 1rem;
    }
    
    .network-legend {
        padding: 1.5rem;
        

    }
    
    #network-modal .modal-content {
        
    }
    
    #network-modal .modal-header {
        
        padding: 1.5rem;
    }
}

/* Loading state improvements */
.network-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 500px;
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    border-radius: 0;
    color: #4a5568;
    font-weight: 600;
    font-size: 1.1rem;
}

/* Enhanced hover effects */
.network-controls .btn-sm:active {
    transform: translateY(0);
}

.network-controls .form-control-sm:hover {
    border-color: #a0aec0;
}
</style>