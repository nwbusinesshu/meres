<?php

return [

    // Flow titles
    'flow_title' => 'Welcome! Register an Admin User!',
    'flow_subtitle' => 'The admin has access to everything: employees, assessments, settings. Enter the admin\'s name and email address. You can also log in later with your Google or Microsoft account.',

    // Step titles
    'step1_title' => 'Admin User',
    'step2_title' => 'Company and Billing Information',
    'step3_title' => 'Basic Settings',
    'step4_title' => 'Summary',

    // Step 1 - Admin user
    'admin' => [
        'name' => 'Name',
        'email' => 'Email',
        'employee_limit' => 'Number of Employees',
        'employee_limit_placeholder' => 'e.g. 50',
    ],

    // Step 2 - Company and billing
    'company' => [
        'name' => 'Company Name',
        'country' => 'Country',
        'postal_code' => 'Postal Code',
        'region' => 'State/Region',
        'city' => 'City',
        'street' => 'Street',
        'house_number' => 'House Number',
        'phone' => 'Phone Number',
        'phone_placeholder' => '+36…',
        'tax_number' => 'Tax Number',
        'tax_number_placeholder' => 'e.g. 12345678-1-12',
        'eu_vat' => 'EU VAT',
        'eu_vat_placeholder' => 'e.g. DE123456789',
    ],

    // Countries
    'countries' => [
        'HU' => 'Hungary (HU)',
        'AT' => 'Austria (AT)',
        'BE' => 'Belgium (BE)',
        'BG' => 'Bulgaria (BG)',
        'HR' => 'Croatia (HR)',
        'CY' => 'Cyprus (CY)',
        'CZ' => 'Czech Republic (CZ)',
        'DK' => 'Denmark (DK)',
        'EE' => 'Estonia (EE)',
        'FI' => 'Finland (FI)',
        'FR' => 'France (FR)',
        'DE' => 'Germany (DE)',
        'GR' => 'Greece (GR)',
        'IE' => 'Ireland (IE)',
        'IT' => 'Italy (IT)',
        'LV' => 'Latvia (LV)',
        'LT' => 'Lithuania (LT)',
        'LU' => 'Luxembourg (LU)',
        'MT' => 'Malta (MT)',
        'NL' => 'Netherlands (NL)',
        'PL' => 'Poland (PL)',
        'PT' => 'Portugal (PT)',
        'RO' => 'Romania (RO)',
        'SK' => 'Slovakia (SK)',
        'SI' => 'Slovenia (SI)',
        'ES' => 'Spain (ES)',
        'SE' => 'Sweden (SE)',
    ],

    // Step 3 - Settings
    'settings' => [
        'ai_telemetry_title' => 'AI Telemetry',
        'ai_telemetry_description' => 'Enable telemetry and AI support functions. (Can be changed later.)',
        'multi_level_title' => 'Multi-level Department Management',
        'multi_level_description' => 'Enable departments and management levels. <strong>Irreversible:</strong> cannot be disabled later.',
        'bonus_malus_title' => 'Bonus/Malus Display',
        'bonus_malus_description' => 'Toggle visibility of classifications in the interface.',
    ],

    // Buttons
    'buttons' => [
        'next' => 'Next',
        'back' => 'Back',
        'finalize' => 'Finalize',
    ],

    // Footer
    'footer' => [
        'already_have_account' => 'Already have an account?',
        'login' => 'Log In',
    ],

    'validation' => [
        'required' => 'This field is required.',
        'invalid_email' => 'Please enter a valid email address.',
        'employee_limit_min' => 'The number of employees must be at least 1.',
        'tax_number_required' => 'A valid Hungarian tax number is required.',
        'eu_vat_format_hu' => 'A valid EU VAT number format is required (e.g. HU12345678).',
        'eu_vat_required' => 'A valid EU VAT number is required (e.g. DE123456789).',
        'network_error' => 'Network error. Please try again.',
        'server_error' => 'A server error occurred.',
        'accept_terms_required' => 'Accepting the Terms of Service is required to continue',
        'accept_privacy_required' => 'Accepting the Privacy Policy is required to continue',
        'accept_gdpr_required' => 'Consent to employee data processing is required to continue',
    ],

    'summary' => [
        'checkbox_on' => 'On',
        'checkbox_off' => 'Off',
        'admin' => 'Admin',
        'company_name' => 'Company Name',
        'employee_count' => 'Number of Employees',
        'employee_unit' => 'people',
        'billing_address' => 'Billing Address',
        'phone' => 'Phone',
        'tax_identification' => 'Tax Identification',
        'tax_number' => 'Tax Number',
        'eu_vat' => 'EU VAT',
        'settings' => 'Settings',
        'ai_telemetry' => 'AI Telemetry',
        'multi_level' => 'Multi-level',
        'bonus_malus' => 'Bonus/Malus',
    ],

    'errors' => [
        'tax_number_invalid' => 'Invalid or missing tax number.',
        'tax_number_exists' => 'An organization with this tax number already exists.',
        'eu_vat_invalid_format' => 'Invalid EU VAT number format.',
        'eu_vat_exists' => 'An organization with this EU VAT number already exists.',
        'eu_vat_invalid_or_missing' => 'Invalid or missing EU VAT number.',
        'email_exists' => 'An active user with this email address already exists.',
        'email_required' => 'Email is required.',
        'email_invalid' => 'Invalid email address.',
        'email_in_use' => 'This email address is already in use.',
        'recaptcha_failed' => 'Please confirm that you are not a robot.',
    ],

    'success_message' => 'Registration successful. You can log in with OAuth or set your password from the email sent.',

    'steps' => [
        'step1_title' => 'Welcome! First, register an admin user!',
        'step1_subtitle' => 'The admin has access to everything: employees, assessments, settings. Enter the admin\'s name and email address. You can also log in later with your Google or Microsoft account. Specify how many employees you have!',
        'step2_title' => 'Now enter the company and billing information!',
        'step2_subtitle' => 'For billing purposes, we need address information and tax identification. For EU countries, an EU VAT number is required.',
        'step3_title' => 'Just a few basic settings and terms left...',
        'step3_subtitle' => 'These settings determine how the system operates. Enable department management only if each department has its own designated manager and at least 5-8 people work in the department. For smaller organizational units, you won\'t need department management; you can set up the hierarchy without it. All other settings can be modified after logging in.',
        'step4_title' => 'Almost done: verify the entered information',
        'step4_subtitle' => 'Before finalizing, make sure all information is correct.',
    ],

    'consent' => [
        'section_title' => 'Terms and Consent',
        
        'terms_label' => 'I have read and accept the :terms_link',
        'terms_link_text' => 'Terms of Service',
        
        'privacy_label' => 'I have read and accept the :privacy_link',
        'privacy_link_text' => 'Privacy Policy',
        
        'gdpr_label' => 'I consent to the processing of employee personal data in accordance with GDPR regulations',
        'gdpr_description' => 'By checking this box, you confirm that:<br>
            • You are authorized to process employee data on behalf of your organization<br>
            • You will use employee data exclusively for performance assessment purposes<br>
            • You will inform employees about their data protection rights<br>
            • Employees can access, export, and request deletion of their personal data',
        
        'required_notice' => 'Fields marked with an asterisk are required to complete registration',
    ],

    
    
];