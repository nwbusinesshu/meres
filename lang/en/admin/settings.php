<?php

return [

    // Toggle settings errors
    'bonus_malus_disabled' => 'Bonus/Malus display is disabled.',
    'parent_settings_disabled' => 'Parent settings (Bonus/Malus display and Bonus calculation) are disabled.',
    'strict_anon_blocks_ai' => 'Strict anonymization is enabled, so AI telemetry cannot be enabled.',
    'unknown_setting' => 'Unknown setting.',
    
    // Save success
    'settings_saved' => 'Settings saved!',
    
    // API Keys
    'api_key_name_invalid' => 'API key name can only contain letters, numbers, spaces, hyphens, and underscores.',
    'api_key_already_exists' => 'An active API key already exists. You must revoke the existing one first.',
    'api_key_created' => 'API key successfully created!',
    'api_key_creation_failed' => 'An error occurred while creating the API key: :error',
    'api_key_not_found' => 'API key not found.',
    'api_key_already_revoked' => 'API key has already been revoked.',
    'api_key_revoked' => 'API key successfully revoked!',
    'api_key_revoke_failed' => 'Failed to revoke API key.',
    'api_key_revoke_error' => 'An error occurred while revoking the API key: :error',
    
    'settings' => [
        // Menu and main titles
        'menu' => 'Settings',
        'title' => 'Settings',
        'section_ai_privacy' => 'AI & Privacy',
        'section_program'    => 'Program Settings',

        // Strict anon
        'strict' => [
            'title' => 'Strict Anonymous Mode',
            'meta_html' => 'In strict anonymous mode, the user ID submitting the assessment is not stored in the database even for analytical purposes. Warning: enabling strict anonymous mode also disables AI telemetry. The data recording mode can be changed at any time, but data from previous measurements will be incomplete. Behavioral patterns and potential fraud will not be detected and filtered.',
        ],

        // AI telemetry
        'ai' => [
            'title' => 'NWB THEMIS AI Engine',
            'meta_html' => 'NWB Advanced Intelligence - we weight submissions based on behavioral patterns and try to filter out fraud. The model does not process users\' personal data, determining submission reliability solely based on anonymous behavioral and content patterns. The feature improves measurement results over time and learns individual employee behaviors.',
        ],

        // Multi-level department management
        'multi_level' => [
            'title' => 'Multi-level Department Management',
            'description' => 'After enabling the department manager level, users can be assigned to department(s), and managers can rank their department subordinates.<br><strong>Irreversible:</strong> once enabled, it cannot be disabled.',
            'enabled_alert' => 'Multi-level department management is <strong>enabled</strong> and <u>cannot be disabled</u>.',
        ],

        // Bonus/Malus display
        'bonus_malus' => [
            'title' => 'Reward Bonus System',
            'description' => 'Toggle Bonus/Malus classification display in the user interface. When disabled, calculations continue to work, but categories will not appear in the employee list and related editing options will not be accessible.',
        ],

        // Easy relation setup
        'easy_relations' => [
            'title' => 'Simplified Relationship Setup',
            'description' => 'When enabled, relationships are set bidirectionally automatically.<br><strong>Subordinate → Colleague:</strong> If X evaluates Y as a subordinate, then Y automatically evaluates X as a colleague.<br><strong>Colleague → Colleague:</strong> If X evaluates Y as a colleague, then Y also evaluates X as a colleague.<br>In case of conflict, the system provides a warning and allows correction.',
        ],

        // Force 2FA for OAuth login
        'oauth_2fa' => [
            'title' => 'Force 2FA for OAuth Login',
            'description' => 'When enabled, two-factor authentication (email verification code) is mandatory for Google and Microsoft OAuth logins as well.<br><strong>Disabled (default):</strong> OAuth users log in directly without 2FA since Google/Microsoft already provides strong authentication.<br><strong>Enabled:</strong> All users must confirm login with an email code regardless of login method.<br><em>Recommended only for organizations handling highly confidential data.</em>',
        ],

        // Scoring section subtitle
        'scoring_subtitle' => 'Methodology Settings',

        // Mode selector box
        'mode' => [
            'title' => 'Scoring Method',
            'meta'  => 'Choose how to determine assessment score limits. Does not affect previous closed assessment periods.',
            'options' => [
                'fixed'     => 'Fixed Points',
                'hybrid'    => 'Hybrid',
                'dynamic'   => 'Dynamic',
                'suggested' => 'Advanced Intelligence',
            ],
        ],

        // Employees see bonuses warnings
    'warn_employees_see_bonuses_on' => 'If you enable this, employees will see their own bonus/malus amount on the results page.',
    'warn_employees_see_bonuses_off' => 'If you disable this, employees will NOT see bonus/malus amounts.',
    
    // Enable bonus calculation warnings
    'warn_enable_bonus_calc_on' => 'If you enable this, the system will automatically calculate bonuses when assessments close.',
    'warn_enable_bonus_calc_off' => 'If you disable this, the system will NOT automatically calculate bonuses.',
    
    // Fallback text
    'api_unknown_user' => 'Unknown',

        // FIXED panel
        'fixed' => [
            'title' => 'Fixed Points (lower and upper score limits)',
            'meta_html' => 'We use two fixed limits: those who exceed the <strong>upper limit</strong> advance; those who fall below the <strong>lower limit</strong> receive feedback or demotion.',
            'description_html' => 'A simple and transparent method. You set a lower and upper limit in advance: those who perform above the upper limit advance; those who fall below the lower limit receive a development plan.',
            'pros' => [
                'Easy to communicate and understand.',
                'Stable benchmark: always the same limits.',
                'Good default setting for smaller, standardized teams.',
            ],
            'cons' => [
                'Does not follow team level fluctuations.',
                'If team level shifts, fixed numbers may become outdated.',
            ],
            'when' => 'Use when you want a constant benchmark (e.g. manufacturing, highly standard processes).',
            'fields' => [
                'normal_level_up'   => 'Upper Limit',
                'normal_level_down' => 'Lower Limit',
            ],
        ],

        // HYBRID panel
        'hybrid' => [
            'title' => 'Hybrid (fixed lower + top% upper)',
            'meta'  => 'Those who exceed a fixed score AND are in the top X% advance.',
            'description_html' => 'The lower limit remains a fixed score (e.g. 70), but we supplement promotion with the team\'s top X% (e.g. top 20%). If someone performs well but the team is also strong, only those in the "best twenty" advance.',
            'pros' => [
                'Maintains selectivity in strong teams.',
                'Has a guaranteed lower limit for filtering weak performance.',
                'Good middle ground between fixed and dynamic methods.',
            ],
            'cons' => [
                'More complex to communicate than fixed.',
                'If the team performs uniformly, many people are excluded from promotion.',
            ],
            'when' => 'Ideal when you have a large team but want a guaranteed "lower safety net" (e.g. 500+ people).',
            'fields' => [
                'normal_level_down' => 'Lower Limit (fixed)',
                'threshold_min_abs_up' => 'Promotion Absolute Min.',
                'threshold_top_pct' => 'Upper Limit (top %)',
            ],
        ],

        // DYNAMIC panel
        'dynamic' => [
            'title' => 'Dynamic (bottom% lower + top% upper)',
            'meta'  => 'Both lower and upper limits adjust to the team\'s current performance.',
            'description_html' => 'The most dynamic method: after each measurement, we recalculate who belongs in the top X% (promoted) and who in the weakest Y% (feedback). This ensures there are always promotions and always candidates for regression - limits "move" with the team\'s level.',
            'pros' => [
                'Always has promotions and warning signals, regardless of team level.',
                'Continuously encourages competitive situations.',
                'Follows team changes well (e.g. seasonal fluctuation).',
            ],
            'cons' => [
                'If the team overall weakens, limits also go down.',
                'Inflates scores: the team doesn\'t necessarily get better, only the ranking remains.',
            ],
            'when' => 'For large, dynamic teams where ranking is important but team level changes quickly.',
            'fields' => [
                'threshold_bottom_pct' => 'Lower Limit (bottom %)',
                'threshold_top_pct' => 'Upper Limit (top %)',
            ],
        ],

        // SUGGESTED panel
        'suggested' => [
            'title' => 'Advanced Intelligence (AI-suggested decisions)',
            'meta' => 'AI analyzes team performance, variance, history, and makes suggestions for promotion/demotion.',
            'description_html' => 'AI takes over decision-making: considers team variance, history, individual performance, and determines thresholds AND suggests specific people for promotion or demotion. You only accept or modify the suggestion. AI learns from every closed cycle, becoming increasingly accurate. Requirements for activation: at least 1 closed measurement + AI telemetry enabled.',
            'pros' => [
                'Full automation: AI makes the tough decisions.',
                'Considers context (variance, history, etc.).',
                'Continuously learns and improves from each cycle.',
            ],
            'cons' => [
                'Decisions are hard to understand (black box).',
                'Requires strong trust in AI, as humans only validate.',
                'If it learns bad data, it gives bad suggestions.',
            ],
            'when' => 'For large, complex organizations where you want to automate promotion decisions and already have enough data.',
            'advanced_settings' => 'Advanced Settings',
            'fields' => [
                'target_promo_rate_max_pct' => 'Max. promotion rate (%): The maximum percentage of the team AI can promote in one measurement. Prevents AI from promoting "too many" people at once, maintaining its filtering role.',
                'target_demotion_rate_max_pct' => 'Max. demotion rate (%): The maximum percentage of the team AI can demote. Prevents half the team from suddenly regressing due to one bad measurement.',
                'never_below_abs_min_for_promo' => 'Promotion absolute minimum (0–100, empty = none): No matter how poorly the team performs, AI will never set the promotion threshold below this score.',
                'use_telemetry_trust' => 'Telemetry-based weighting: AI considers submission reliability scores.',
                'no_forced_demotion_if_high_cohesion' => 'No forced demotion with high cohesion: If the team performs closely together, AI won\'t force demotions.',
            ],
        ],

        // Buttons
        'buttons' => [
            'save_settings' => 'Save Settings',
        ],

        // JavaScript messages
        'confirm' => 'Confirmation Required',
        'warn_strict_on' => 'Are you sure you want to enable strict anonymous mode? This will also disable AI telemetry.',
        'warn_ai_on' => 'Are you sure you want to enable AI telemetry?',
        'warn_ai_off' => 'Are you sure you want to disable AI telemetry? Behavioral pattern analysis will stop.',
        'warn_multi_on' => 'Are you sure you want to enable Multi-level department management? This decision is final and cannot be disabled later. Before enabling, learn about the consequences in the documentation!',
        'warn_bonus_malus_off' => 'Are you sure you want to hide Bonus/Malus categories? Classifications will continue to be calculated but won\'t be visible in the user interface.',
        'warn_bonus_malus_on' => 'Are you sure you want to display Bonus/Malus categories in the user interface?',
        'warn_easy_relation_off' => 'Are you sure you want to disable simplified relationship setup? Relationships will then need to be set manually in both directions.',
        'warn_easy_relation_on' => 'Are you sure you want to enable simplified relationship setup? Relationships will be automatically set bidirectionally.',
        'warn_force_oauth_2fa_on' => 'Are you sure you want to force 2FA for OAuth logins? Google and Microsoft logins will also require email verification codes.',
        'warn_force_oauth_2fa_off' => 'Are you sure you want to disable 2FA enforcement for OAuth logins? Google and Microsoft logins will happen without 2FA.',
        'saved' => 'Settings saved!',
        'error' => 'Error',
    

'api_subtitle' => 'API Connection',
'api_title' => 'API Key',
'api_description' => 'The API key allows third-party systems (e.g. ERP, HR software) to connect to the Quarma360 system and export organization data.',
'api_important' => 'Important',
'api_important_text' => 'The API key is only displayed in full once. After that, only the last 8 characters are visible.',

// Status messages
'api_loading' => 'Loading...',
'api_no_key' => 'No API key created yet.',
'api_key_last_chars' => 'API key (last 8 characters):',

// Badges
'api_badge_active' => 'Active',
'api_badge_revoked' => 'Revoked',

// Metadata labels
'api_meta_name' => 'Name',
'api_meta_created' => 'Created',
'api_meta_created_by' => 'Created By',
'api_meta_last_used' => 'Last Used',
'api_meta_requests_24h' => 'Requests (24h)',
'api_meta_never_used' => 'Never Used',

// Buttons
'api_btn_generate' => 'Generate New API Key',
'api_btn_revoke' => 'Revoke Key',
'api_btn_copy' => 'Copy',
'api_btn_copied' => 'Copied!',

// Modal - Generate
'api_modal_generate_title' => 'Generate New API Key',
'api_modal_generate_name_label' => 'Key Name:',
'api_modal_generate_name_placeholder' => 'e.g. ERP integration',
'api_modal_generate_name_help' => 'Give the key a descriptive name for easier identification.',
'api_modal_generate_confirm' => 'Create',

// Modal - Display Key (one-time)
'api_modal_display_title' => '🔑 New API Key Created',
'api_modal_display_warning' => '⚠️ Warning!',
'api_modal_display_warning_text' => 'This API key is only displayed once. Copy and store it securely because it will not be accessible again later!',
'api_modal_display_key_label' => 'API Key:',
'api_modal_display_usage_hint' => 'To use the key, add the following header to every API call:',
'api_modal_display_close' => 'OK, I\'ve Saved It',

// Modal - Revoke
'api_modal_revoke_title' => 'Revoke API Key',
'api_modal_revoke_text' => 'Are you sure you want to revoke this API key? This action cannot be undone!',
'api_modal_revoke_confirm' => 'Yes, Revoke',

// Validation messages
'api_validation_name_required' => 'Enter the key name!',
'api_validation_name_too_short' => 'The name must be at least 3 characters long!',
'api_validation_name_invalid' => 'API key name can only contain letters, numbers, spaces, hyphens, and underscores.',

// Success messages
'api_generate_success' => 'API key successfully created!',
'api_revoke_success' => 'API key successfully revoked!',
'api_copy_success' => 'API key copied to clipboard!',

// Error messages
'api_generate_error' => 'An error occurred while creating the API key.',
'api_revoke_error' => 'An error occurred while revoking the API key.',
'api_load_error' => 'An error occurred while loading API keys.',
'api_already_exists' => 'An active API key already exists. You must revoke the existing one first.',
'api_not_found' => 'API key not found.',
'api_already_revoked' => 'API key has already been revoked.',
'api_revoke_failed' => 'Failed to revoke API key.',

// Loading states
'api_generating' => 'Creating API key...',
'api_revoking' => 'Revoking API key...',
],

];