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
// FINAL FIXED network with proper spacing and Hungarian labels
let cy;
let networkData = null;
let savedPositions = new Map();

function initNetworkModal() {
    $('#network-modal').modal('show');
    
    if (!cy) {
        cy = cytoscape({
            container: document.getElementById('cy-container'),
            elements: [],
            style: [
                // Department Container Styles - PROPER SIZE
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
                        'min-width': '320px',      // Slightly wider for better spacing
                        'min-height': '240px',     // Taller for better spacing
                        'padding': '10px',         // More padding
                        'compound-sizing-wrt-labels': 'exclude',
                        'z-index': 1,
                        'text-background-color': '#ffffff',
                        'text-background-opacity': 0.9,
                        'text-background-padding': '4px',
                        'text-background-shape': 'round-rectangle'
                    }
                },
                // User Node Styles - READABLE TEXT WITH HUNGARIAN LABELS
                {
                    selector: 'node[type != "department"]',
                    style: {
                        'shape': 'rectangle',
                        'width': 150,
                        'height': 80,
                        'background-color': function(ele) {
                            const type = ele.data('type');
                            switch(type) {
                                case 'ceo': return '#dc3545';
                                case 'manager': return '#ffc107';
                                case 'normal': return '#28a745';
                                default: return '#6c757d';
                            }
                        },
                        'border-width': 3,
                        'border-color': '#ffffff',
                        'border-opacity': 1,
                        'label': function(ele) {
                            const name = ele.data('name') || '';
                            const type = ele.data('type') || '';
                            // FIXED: Use Hungarian labels from your language file
                            const typeLabels = {
                                'ceo': 'Ügyvezető',        // From usertypes.php
                                'manager': 'Manager',      // From usertypes.php  
                                'normal': 'Alkalmazott'    // From usertypes.php
                            };
                            return `${name}\n${typeLabels[type] || type}`;
                        },
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-size': '14px',
                        'font-weight': '700',
                        'color': '#ffffff',
                        'text-shadow-color': '#000000',
                        'text-shadow-opacity': 0.6,
                        'text-shadow-offset-x': '1px',
                        'text-shadow-offset-y': '1px',
                        'text-wrap': 'wrap',
                        'text-max-width': '140px',
                        'z-index': 10,
                        'box-shadow-color': '#000000',
                        'box-shadow-opacity': 0.3,
                        'box-shadow-offset-x': '2px',
                        'box-shadow-offset-y': '2px',
                        'box-shadow-blur': '8px'
                    }
                },
                // Edge Styles - PROPER HIERARCHY
                {
                    selector: 'edge',
                    style: {
                        'width': 4,
                        'line-color': function(ele) {
                            const type = getStrongestRelationType(ele.data('types') || [ele.data('type')]);
                            switch(type) {
                                case 'superior': return '#007bff';
                                case 'subordinate': return '#fd7e14';
                                case 'colleague': return '#20c997';
                                default: return '#6c757d';
                            }
                        },
                        'target-arrow-color': function(ele) {
                            const type = getStrongestRelationType(ele.data('types') || [ele.data('type')]);
                            switch(type) {
                                case 'superior': return '#007bff';
                                case 'subordinate': return '#fd7e14';
                                case 'colleague': return '#20c997';
                                default: return '#6c757d';
                            }
                        },
                        'target-arrow-shape': 'triangle',
                        'arrow-scale': 1.8,
                        'curve-style': 'bezier',
                        'control-point-step-size': 50,
                        'opacity': 0.9,
                        'z-index': 5,
                        'line-style': function(ele) {
                            const type = getStrongestRelationType(ele.data('types') || [ele.data('type')]);
                            return type === 'colleague' ? 'dashed' : 'solid';
                        }
                    }
                },
                // Selected/Highlighted States
                {
                    selector: 'node[type != "department"]:selected',
                    style: {
                        'border-width': 5,
                        'border-color': '#4f46e5',
                        'border-opacity': 1
                    }
                },
                {
                    selector: 'node[type != "department"].highlighted',
                    style: {
                        'border-width': 5,
                        'border-color': '#ff6b6b',
                        'border-opacity': 1
                    }
                }
            ],
            layout: { name: 'preset' },
            autoungrabify: false,
            autounselectify: false
        });

        // Event handlers
        cy.on('tap', 'node[type != "department"]', function(evt) {
            const node = evt.target;
            const nodeData = node.data();
            
            cy.nodes('[type != "department"]').removeClass('highlighted');
            node.addClass('highlighted');
            
            console.log('User Details:', {
                name: nodeData.name,
                email: nodeData.email,
                type: nodeData.type,
                department: nodeData.department,
                raterCount: nodeData.rater_count,
                isManager: nodeData.is_manager
            });
        });

        cy.on('dragfree', 'node', function(evt) {
            const node = evt.target;
            const position = node.position();
            savedPositions.set(node.id(), position);
        });

        // Control handlers
        $('#layout-select').on('change', function() {
            const layoutName = $(this).val();
            applyStableLayout(layoutName);
        });

        $('#department-filter').on('change', function() {
            const department = $(this).val();
            filterByDepartment(department);
        });

        $('#fit-network').on('click', function() {
            cy.fit();
        });

        $('#reset-network').on('click', function() {
            savedPositions.clear();
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
        console.log('Raw response:', response);
        
        const transformedData = transformToCompoundNodesFinalFix(response);
        networkData = transformedData;
        
        console.log('Transformed data:', networkData);
        
        updateDepartmentFilter(response.departments);
        
        cy.elements().remove();
        cy.add(transformedData.elements);
        
        // Apply STABLE layout with proper spacing
        applyStableInitialLayout();
        
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

function getStrongestRelationType(types) {
    if (!types || types.length === 0) return 'colleague';
    
    console.log('Evaluating relationship types:', types);
    
    if (types.includes('superior')) {
        console.log('Selected: superior');
        return 'superior';
    }
    if (types.includes('subordinate')) {
        console.log('Selected: subordinate');
        return 'subordinate';
    }
    if (types.includes('colleague')) {
        console.log('Selected: colleague');
        return 'colleague';
    }
    
    console.log('Selected fallback:', types[0]);
    return types[0];
}

function transformToCompoundNodesFinalFix(response) {
    const elements = [];
    const departments = new Map();
    
    console.log('=== TRANSFORMATION DEBUG ===');
    console.log('Raw nodes:', response.nodes);
    console.log('Raw departments:', response.departments);
    
    // Create department containers
    response.departments.forEach(dept => {
        departments.set(dept.id, dept);
        elements.push({
            data: {
                id: `dept_${dept.id}`,
                name: dept.department_name,
                type: 'department'
            }
        });
        console.log(`Created department: dept_${dept.id} (${dept.department_name})`);
    });
    
    // Check if we need "No Department" container
    const hasNoDeptUsers = response.nodes.some(node => 
        !node.department_id && !node.managed_dept_id
    );
    if (hasNoDeptUsers) {
        elements.push({
            data: {
                id: 'dept_none',
                name: 'Közvetlen munkatársak',
                type: 'department'
            }
        });
        console.log('Created: dept_none (Központi Iroda)');
    }
    
    // Transform user nodes with CORRECT department assignment logic
    response.nodes.forEach(node => {
        let parentId;
        let departmentName = null;
        
        console.log(`\n--- Processing user: ${node.label} ---`);
        console.log(`department_id: ${node.department_id}`);
        console.log(`managed_dept_id: ${node.managed_dept_id}`);
        console.log(`department_name: ${node.department_name}`);
        
        if (node.managed_dept_id) {
            parentId = `dept_${node.managed_dept_id}`;
            departmentName = departments.get(node.managed_dept_id)?.department_name || node.department_name;
            console.log(`→ Manager of dept ${node.managed_dept_id} → ${parentId}`);
        } else if (node.department_id) {
            parentId = `dept_${node.department_id}`;
            departmentName = departments.get(node.department_id)?.department_name || node.department_name;
            console.log(`→ Member of dept ${node.department_id} → ${parentId}`);
        } else {
            parentId = 'dept_none';
            console.log(`→ No department → ${parentId}`);
        }
        
        elements.push({
            data: {
                id: node.id,
                name: node.label,
                email: node.email,
                type: node.type,
                department: departmentName,
                department_id: node.department_id,
                managed_dept_id: node.managed_dept_id,
                rater_count: node.rater_count,
                rater_status: node.rater_status,
                is_manager: node.is_manager,
                parent: parentId
            }
        });
        
        console.log(`✓ Assigned ${node.label} to ${parentId}`);
    });
    
    // Transform edges with CORRECT relationship hierarchy
    response.edges.forEach(edge => {
        console.log(`\n--- Processing edge: ${edge.id} ---`);
        console.log(`Types: ${JSON.stringify(edge.types)}`);
        
        const strongestType = getStrongestRelationType(edge.types);
        console.log(`Selected strongest type: ${strongestType}`);
        
        elements.push({
            data: {
                id: edge.id,
                source: edge.source,
                target: edge.target,
                type: strongestType,
                types: edge.types,
                bidirectional: edge.bidirectional,
                is_subordinate: edge.is_subordinate
            }
        });
    });
    
    console.log('=== FINAL ELEMENTS ===');
    elements.forEach(el => {
        if (el.data.type !== 'department') {
            console.log(`${el.data.name} → parent: ${el.data.parent}`);
        }
    });
    
    return { elements };
}

// FIXED: Stable layout with PROPER SPACING to prevent overlaps
function applyStableInitialLayout() {
    const hasSavedPositions = savedPositions.size > 0;
    
    if (hasSavedPositions) {
        // Use saved positions
        const presetLayout = cy.layout({
            name: 'preset',
            positions: function(node) {
                return savedPositions.get(node.id());
            },
            fit: true,
            padding: 50
        });
        presetLayout.run();
    } else {
        // Apply manual layout with PROPER SPACING
        applyManualLayoutWithSpacing();
    }
}

// FIXED: Manual layout with proper spacing to prevent overlap
function applyManualLayoutWithSpacing() {
    console.log('Applying manual layout with proper spacing to prevent overlaps');
    
    const departments = cy.nodes('[type = "department"]');
    const containerWidth = cy.width();
    const containerHeight = cy.height();
    
    // Position departments first - more spacing
    const deptCount = departments.length;
    const deptSpacing = Math.max(450, containerWidth / deptCount); // Increased from 400 to 450
    
    departments.forEach((dept, index) => {
        const x = 150 + (index * deptSpacing); // Start further from edge
        const y = containerHeight / 2;
        
        dept.position({ x, y });
        console.log(`Positioned department ${dept.data('name')} at (${x}, ${y})`);
        
        // Position users within this department - FIXED SPACING
        const usersInDept = cy.nodes(`[parent = "${dept.id()}"]`);
        const userCount = usersInDept.length;
        
        if (userCount > 0) {
            // FIXED: Better grid arrangement with proper spacing
            const cols = Math.min(Math.ceil(Math.sqrt(userCount)), 3); // Max 3 columns
            const rows = Math.ceil(userCount / cols);
            const userSpacingX = 120; // FIXED: Increased from 80 to 120 (horizontal spacing)
            const userSpacingY = 110; // FIXED: Increased from 80 to 110 (vertical spacing)
            
            usersInDept.forEach((user, userIndex) => {
                const col = userIndex % cols;
                const row = Math.floor(userIndex / cols);
                
                // FIXED: Position relative to department center with proper spacing
                const userX = x + (col - (cols - 1) / 2) * userSpacingX;
                const userY = y + (row - (rows - 1) / 2) * userSpacingY;
                
                user.position({ x: userX, y: userY });
                console.log(`Positioned user ${user.data('name')} at (${userX}, ${userY}) [col:${col}, row:${row}]`);
                
                // Save position for stability
                savedPositions.set(user.id(), { x: userX, y: userY });
            });
        }
        
        // Save department position
        savedPositions.set(dept.id(), { x, y });
    });
    
    // Fit to view with proper padding
    cy.fit(undefined, 60); // Increased padding from 50 to 60
}

function applyStableLayout(layoutName) {
    console.log(`Applying stable layout: ${layoutName}`);
    
    let layoutOptions;
    
    switch(layoutName) {
        case 'circle':
            layoutOptions = {
                name: 'circle',
                fit: true,
                padding: 60,
                spacingFactor: 1.8, // Increased spacing
                animate: true,
                animationDuration: 1000
            };
            break;
        case 'grid':
            layoutOptions = {
                name: 'grid',
                fit: true,
                padding: 60,
                spacingFactor: 2.5, // Increased spacing
                animate: true,
                animationDuration: 1000
            };
            break;
        case 'breadthfirst':
            layoutOptions = {
                name: 'breadthfirst',
                fit: true,
                padding: 60,
                directed: true,
                spacingFactor: 2, // Increased spacing
                animate: true,
                animationDuration: 1000
            };
            break;
        case 'concentric':
            layoutOptions = {
                name: 'concentric',
                fit: true,
                padding: 60,
                spacingFactor: 2.5, // Increased spacing
                animate: true,
                animationDuration: 1000,
                concentric: function(node) {
                    const type = node.data('type');
                    if (type === 'ceo') return 3;
                    if (type === 'manager') return 2;
                    return 1;
                }
            };
            break;
        default: // cose or manual
            // Use manual layout for consistency
            applyManualLayoutWithSpacing();
            return;
    }
    
    const layout = cy.layout(layoutOptions);
    layout.run();
    
    // Save new positions after layout completes
    layout.promiseOn('layoutstop').then(function() {
        cy.nodes().forEach(function(node) {
            const position = node.position();
            savedPositions.set(node.id(), position);
        });
        console.log('Layout completed and positions saved');
    });
}

function updateDepartmentFilter(departments) {
    const $deptFilter = $('#department-filter');
    $deptFilter.empty().append('<option value="">All Departments</option>');
    
    departments.forEach(dept => {
        $deptFilter.append(`<option value="${dept.department_name}">${dept.department_name}</option>`);
    });
    
    if (networkData && networkData.elements.some(el => 
        el.data.type !== 'department' && !el.data.department_id && !el.data.managed_dept_id)) {
        $deptFilter.append('<option value="none">Központi Iroda</option>');
    }
}

function filterByDepartment(department) {
    if (department === '') {
        cy.elements().show();
    } else if (department === 'none') {
        cy.elements().hide();
        cy.nodes('[type = "department"]').filter('[id = "dept_none"]').show();
        cy.nodes('[type != "department"]').filter(function(node) {
            return !node.data('department_id') && !node.data('managed_dept_id');
        }).show();
        cy.edges().show();
    } else {
        cy.elements().hide();
        
        cy.nodes('[type = "department"]').filter(function(node) {
            return node.data('name') === department;
        }).show();
        
        cy.nodes('[type != "department"]').filter(function(node) {
            return node.data('department') === department;
        }).show();
        
        cy.edges().show();
    }
    
    cy.fit();
}

window.initNetworkModal = initNetworkModal;
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