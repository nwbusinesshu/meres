<div class="modal fade" tabindex="-1" role="dialog" id="network-modal">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-network-wired mr-2"></i>
                    Company Network Visualization
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
let cy;
let networkData = null;

function initNetworkModal() {
    $('#network-modal').modal('show');
    
    if (!cy) {
        // Initialize Cytoscape.js
        cy = cytoscape({
            container: document.getElementById('cy-container'),
            elements: [],
            style: [
                // Node styles
                {
                    selector: 'node',
                    style: {
                        'background-color': function(ele) {
                            const type = ele.data('type');
                            switch(type) {
                                case 'ceo': return '#dc3545';
                                case 'manager': return '#ffc107';
                                case 'normal': return '#28a745';
                                default: return '#6c757d';
                            }
                        },
                        'label': 'data(name)',
                        'width': function(ele) {
                            const type = ele.data('type');
                            return type === 'ceo' ? 60 : type === 'manager' ? 50 : 40;
                        },
                        'height': function(ele) {
                            const type = ele.data('type');
                            return type === 'ceo' ? 60 : type === 'manager' ? 50 : 40;
                        },
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'color': 'white',
                        'text-outline-width': 2,
                        'text-outline-color': '#000',
                        'font-size': '12px',
                        'font-weight': 'bold'
                    }
                },
                // Edge styles
                {
                    selector: 'edge',
                    style: {
                        'width': 3,
                        'line-color': function(ele) {
                            const type = ele.data('type');
                            switch(type) {
                                case 'superior': return '#007bff';
                                case 'subordinate': return '#fd7e14';
                                case 'colleague': return '#20c997';
                                default: return '#6c757d';
                            }
                        },
                        'target-arrow-color': function(ele) {
                            const type = ele.data('type');
                            switch(type) {
                                case 'superior': return '#007bff';
                                case 'subordinate': return '#fd7e14';
                                case 'colleague': return '#20c997';
                                default: return '#6c757d';
                            }
                        },
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                },
                // Highlighted node
                {
                    selector: 'node.highlighted',
                    style: {
                        'border-width': 4,
                        'border-color': '#ff6b6b',
                        'border-opacity': 1
                    }
                }
            ],
            layout: {
                name: 'cose',
                idealEdgeLength: 100,
                nodeOverlap: 20,
                refresh: 20,
                fit: true,
                padding: 30,
                randomize: false,
                componentSpacing: 40,
                nodeRepulsion: 400000,
                edgeElasticity: 100,
                nestingFactor: 5,
                gravity: 80,
                numIter: 1000,
                initialTemp: 200,
                coolingFactor: 0.95,
                minTemp: 1.0
            }
        });

        // Event handlers
        cy.on('tap', 'node', function(evt) {
            const node = evt.target;
            const nodeData = node.data();
            
            // Highlight clicked node
            cy.nodes().removeClass('highlighted');
            node.addClass('highlighted');
            
            // Show node info (you can customize this)
            const info = `
                <strong>${nodeData.name}</strong><br>
                Email: ${nodeData.email}<br>
                Type: ${nodeData.type}<br>
                Department: ${nodeData.department}
            `;
            
            // Simple tooltip - you can replace with a proper tooltip library
            console.log('Node clicked:', nodeData);
        });

        // Control handlers
        $('#layout-select').on('change', function() {
            const layoutName = $(this).val();
            cy.layout({
                name: layoutName,
                fit: true,
                padding: 30
            }).run();
        });

        $('#department-filter').on('change', function() {
            const department = $(this).val();
            
            if (department === '') {
                // Show all nodes
                cy.nodes().show();
            } else {
                // Hide nodes not in selected department
                cy.nodes().hide();
                cy.nodes().filter(function(node) {
                    return node.data('department') === department;
                }).show();
            }
            
            cy.fit();
        });

        $('#fit-network').on('click', function() {
            cy.fit();
        });

        $('#reset-network').on('click', function() {
            loadNetworkData();
        });
    }
    
    loadNetworkData();
}

function loadNetworkData() {
    swal_loader.fire();
    
    $.ajax({
        url: "{{ route('admin.employee.network') }}",
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
    .done(function(response) {
        networkData = response;
        
        // Update department filter options
        const departments = [...new Set(response.elements.nodes.map(n => n.data.department))];
        const $deptFilter = $('#department-filter');
        $deptFilter.empty().append('<option value="">All Departments</option>');
        
        departments.forEach(dept => {
            if (dept) {
                $deptFilter.append(`<option value="${dept}">${dept}</option>`);
            }
        });
        
        // Load data into Cytoscape
        cy.elements().remove();
        cy.add(response.elements);
        
        // Apply layout
        cy.layout({
            name: 'cose',
            idealEdgeLength: 100,
            nodeOverlap: 20,
            refresh: 20,
            fit: true,
            padding: 30,
            randomize: false,
            componentSpacing: 40,
            nodeRepulsion: 400000,
            edgeElasticity: 100,
            nestingFactor: 5,
            gravity: 80,
            numIter: 1000,
            initialTemp: 200,
            coolingFactor: 0.95,
            minTemp: 1.0
        }).run();
        
        swal_loader.close();
    })
    .fail(function(xhr) {
        swal_loader.close();
        console.error('Failed to load network data:', xhr.responseText);
        Swal.fire({
            title: 'Error',
            text: 'Failed to load network data. Please try again.',
            icon: 'error'
        });
    });
}

// Make function globally available
window.initNetworkModal = initNetworkModal;
</script>

<style>
  /* Add this CSS to your main stylesheet or create a separate network.css file */

/* Network Modal Styles */
#network-modal .modal-dialog {
    max-width: 1200px;
    margin: 1rem auto;
}

#network-modal .modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

#network-modal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    border-bottom: none;
}

#network-modal .modal-header .close {
    color: white;
    opacity: 0.8;
    text-shadow: none;
}

#network-modal .modal-header .close:hover {
    opacity: 1;
}

/* Network Controls */
.network-controls {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.network-controls label {
    font-weight: 600;
    margin-bottom: 0.25rem;
    display: block;
}

.network-controls .form-control-sm {
    border-radius: 4px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.network-controls .form-control-sm:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.network-controls .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 4px;
    margin-right: 0.5rem;
    transition: all 0.15s ease-in-out;
}

.network-controls .btn-outline-primary:hover {
    background-color: #667eea;
    border-color: #667eea;
    transform: translateY(-1px);
}

.network-controls .btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    transform: translateY(-1px);
}

/* Cytoscape Container */
#cy-container {
    border: 1px solid #dee2e6;
    background: #ffffff;
    position: relative;
    overflow: hidden;
}

#cy-container:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.05) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

/* Network Legend */
.network-legend {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.network-legend h6 {
    color: #495057;
    font-weight: 600;
    font-size: 0.9rem;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #495057;
}

/* Legend Nodes */
.legend-node {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-block;
    border: 2px solid rgba(0, 0, 0, 0.1);
}

.legend-node.ceo {
    background-color: #dc3545;
}

.legend-node.manager {
    background-color: #ffc107;
}

.legend-node.normal {
    background-color: #28a745;
}

/* Legend Edges */
.legend-edge {
    width: 30px;
    height: 3px;
    display: inline-block;
    position: relative;
}

.legend-edge:after {
    content: '';
    position: absolute;
    right: -3px;
    top: -3px;
    width: 0;
    height: 0;
    border-left: 6px solid;
    border-top: 4px solid transparent;
    border-bottom: 4px solid transparent;
}

.legend-edge.superior {
    background-color: #007bff;
}

.legend-edge.superior:after {
    border-left-color: #007bff;
}

.legend-edge.colleague {
    background-color: #20c997;
}

.legend-edge.colleague:after {
    border-left-color: #20c997;
}

.legend-edge.subordinate {
    background-color: #fd7e14;
}

.legend-edge.subordinate:after {
    border-left-color: #fd7e14;
}

/* Network Button Enhancement */
.btn-outline-primary.relations {
    transition: all 0.2s ease;
}

.btn-outline-primary.relations:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

/* Loading State */
.network-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 100;
    background: rgba(255, 255, 255, 0.9);
    padding: 2rem;
    border-radius: 8px;
    text-align: center;
}

.network-loading .spinner-border {
    color: #667eea;
}

/* Responsive Design */
@media (max-width: 768px) {
    #network-modal .modal-dialog {
        margin: 0.5rem;
        max-width: none;
    }
    
    .network-controls .row {
        flex-direction: column;
    }
    
    .network-controls .col-md-4 {
        margin-bottom: 1rem;
    }
    
    #cy-container {
        height: 400px;
    }
    
    .legend-items {
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Animation for modal appearance */
#network-modal.fade .modal-dialog {
    transform: translate(0, -50px);
    transition: transform 0.3s ease-out;
}

#network-modal.show .modal-dialog {
    transform: translate(0, 0);
}

/* Tooltip styles (if you want to add custom tooltips) */
.cy-tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
    pointer-events: none;
    z-index: 1000;
    max-width: 200px;
}</style>