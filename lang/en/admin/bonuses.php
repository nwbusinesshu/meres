<?php
return [
    // Page titles and navigation
    'title' => 'Bonuses',
    'bonuses' => 'Bonuses',
    'manage-bonuses' => 'Manage Bonuses',
    'bonus-summary' => 'Period Bonuses',
    
    // Assessment selection
    'select-assessment' => 'Select Assessment',
    'no-closed-assessments' => 'No closed assessments',
    'closed-at' => 'Closed',
    'assessment-period' => 'Assessment Period',
    'no-assessment-yet' => "No closed assessment period yet!",
    'no-assessment-bonuses-info' => "Therefore, no bonus calculation has been performed yet.",
    "bonuses-tasks" => "Once you're done with configuration, start an assessment from the home page! <br> Note that you must provide net wages for anyone you want to pay bonuses to!",
    
    // Table headers
    'employee' => 'Employee',
    'position' => 'Position',
    'assessment-result' => 'Assessment Result',
    'bonus-amount' => 'Bonus Amount',
    'net-wage' => 'Net Wage',
    'currency' => 'Currency',
    'payment-status' => 'Payment Status',
    'actions' => 'Actions',
    'department' => "Department",
    "bonus-malus-level" => "Level",
    'bonus-list' => "Bonus List",
    
    // Payment status
    'paid' => 'Paid',
    'unpaid' => 'Not Paid',
    'toggle-payment' => 'Toggle Payment Status',
    'mark-as-paid' => 'Mark as Paid',
    'mark-as-unpaid' => 'Mark as Not Paid',
    'total-bonuses' => "Total Amount",
    
    // Bonus calculations
    'bonus-calculated' => 'Bonus Calculated',
    'malus-calculated' => 'Malus Calculated',
    'no-wage-data' => 'No Wage Data',
    'wage-not-set' => 'Wage Not Set',
    
    // Configuration
    'configure-multipliers' => 'Configure Multipliers',
    'multiplier-help-text' => 'Set the bonus/malus multipliers for each level. Bonus amount = net wage × multiplier.',
    'level' => 'Level',
    'category' => 'Category',
    'multiplier' => 'Multiplier',
    'reset-defaults' => 'Reset to Defaults',
    'default-multipliers' => 'Default Multipliers (Hungarian)',
    
    // Multiplier ranges
    'range-1-3' => 'Levels 1-3',
    'range-4-6' => 'Levels 4-6',
    'range-7-9' => 'Levels 7-9',
    'range-10-12' => 'Levels 10-12',
    'range-13-15' => 'Levels 13-15',
    
    // Wage management
    'wage-help-text' => 'Enter the net monthly wage for bonus calculation.',
    'save-wage' => 'Save Wage',
    'wage-saved' => 'Wage successfully saved',
    'wage-save-error' => 'An error occurred while saving the wage',
    
    // Configuration save
    'config-saved' => 'Configuration successfully saved',
    'config-save-error' => 'An error occurred while saving the configuration',
    
    // Payment toggle
    'payment-updated' => 'Payment status updated',
    'payment-update-error' => 'An error occurred while updating the payment status',
    
    // Settings page
    'employees-see-bonuses' => 'Display Bonuses',
    'employees-see-bonuses-description' => 'When enabled, employees can see their own bonus/malus amount on the results page.',
    
    // ✅ NEW: Enable bonus calculation setting
    'enable-bonus-calculation' => 'Enable Bonus Calculation Based on Net Wage',
    'enable-bonus-calculation-description' => 'When enabled, the system automatically calculates bonus/malus amounts when the assessment closes based on the configured multipliers and employees\' net wages.',
    
    'default-currency' => 'Default Currency',
    'default-currency-description' => 'Select the default currency for bonus calculations.',
    
    // Export
    'export-csv' => 'Export to CSV',
    'export-excel' => 'Export to Excel',
    
    // Messages
    'no-bonuses-to-display' => 'No bonuses to display for this assessment',
    'bonuses-calculated-on-close' => 'Bonuses are automatically calculated when the assessment closes',
    
    // Errors
    'error-loading-bonuses' => 'Error loading bonuses',
    'error-no-assessment' => 'No assessment selected',
    
    // Multiplier categories
    'malus-levels' => 'Malus Levels',
    'neutral-level' => 'Base Level',
    'bonus-levels' => 'Bonus Levels',
    
    // Reset functionality
    'reset-confirm-title' => 'Reset to Defaults?',
    'reset-confirm-text' => 'This will reset all multipliers to the Hungarian default values.',
    'reset-success' => 'Reset',
    'reset-success-text' => 'Don\'t forget to save your changes!',
    
    // Additional messages
    'config-load-failed' => 'Failed to load configuration',
    'config-saved' => 'Configuration successfully saved',
    'marked-paid' => 'Marked as paid',
    'marked-unpaid' => 'Marked as not paid',
    'toggle-failed' => 'Failed to change status',

    'previous-period' => 'Previous Period',
    'next-period' => 'Next Period',
    'close-assessment-first' => 'Close an assessment first to view bonuses.',
    'no-bonuses' => 'No bonuses to pay in the assessment period!',
];