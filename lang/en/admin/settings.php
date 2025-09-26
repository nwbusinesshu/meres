<?php

return [
    'settings' => [
        // Menu and main titles
        'menu' => 'Settings',
        'title' => 'Settings',
        'section_ai_privacy' => 'AI & Privacy',

        // Strict anonymous
        'strict' => [
            'title' => 'Strict Anonymous Mode',
            'meta_html' => 'In strict anonymous mode, the user ID of the person submitting the evaluation is not stored in the database even for analytical purposes. Warning: enabling strict anonymous mode also disables AI telemetry. The data recording mode can be restored at any time, but data from previous measurements will be incomplete. Behavioral patterns and potential fraud will not be detected and filtered out.',
        ],

        // AI telemetry
        'ai' => [
            'title' => 'NWB THEMIS AI Engine',
            'meta_html' => 'NWB Advanced Intelligence - we weight submissions based on behavioral patterns and strive to filter out fraud. The model does not process users\' personal data, determining submission reliability solely based on anonymous behavioral and content patterns. This function improves measurement results long-term and learns individual employee behaviors.',
        ],

        // Scoring section subtitle
        'scoring_subtitle' => 'Methodological Settings',

        // Mode selector box
        'mode' => [
            'title' => 'Scoring Method',
            'meta'  => 'Choose how we determine evaluation score thresholds. Previous, closed evaluation periods are not affected.',
            'options' => [
                'fixed'     => 'Fixed Points',
                'hybrid'    => 'Hybrid',
                'dynamic'   => 'Dynamic',
                'suggested' => 'Advanced Intelligence',
            ],
        ],

        // FIXED panel
        'fixed' => [
            'title' => 'Fixed Points (lower and upper thresholds)',
            'meta_html' => 'We use two fixed boundaries: those who score <strong>above the upper threshold</strong> get promoted; those who fall <strong>below the lower threshold</strong> receive feedback or demotion.',
            'description_html' => 'Simple and transparent method. You set a lower and upper threshold in advance: those who perform above the upper threshold get promoted; those who fall below the lower threshold receive a development plan.',
            'pros' => [
                'Easy to communicate and understand.',
                'Stable benchmark: always the same thresholds.',
                'Good default setting for smaller, standardized teams.',
            ],
            'cons' => [
                'Does not follow team level fluctuations.',
                'If the field\'s level shifts, fixed numbers may become outdated.',
            ],
            'when' => 'Use when you want a constant benchmark (e.g., manufacturing, highly standardized processes).',
            'fields' => [
                'normal_level_up'   => 'Upper Threshold',
                'normal_level_down' => 'Lower Threshold',
            ],
        ],

        // HYBRID panel
        'hybrid' => [
            'title' => 'Hybrid (fixed lower + top% upper)',
            'meta'  => 'Gets promoted if they exceed a fixed score AND are in the top X%.',
            'description_html' => 'Combines minimum expectations and relative performance. This way it\'s not enough to be "good relative to the field," basic quality is also required.',
            'pros' => [
                'Quality assurance + rewarding top performers simultaneously.',
                'Prevents "automatic" promotions in weak fields.',
            ],
            'cons' => [
                'More complex communication to the team.',
                'Two parameters to maintain (minimum points + top%).',
            ],
            'when' => 'Ideal when basic quality and highlighting the best performers is important (e.g., sales, customer service).',
            'fields' => [
                'threshold_min_abs_up' => 'Minimum Score (0–100)',
                'threshold_top_pct'    => 'Upper Threshold for Promotions (%)',
            ],
        ],

        // DYNAMIC panel
        'dynamic' => [
            'title' => 'Dynamic (relative bands)',
            'meta'  => 'The bottom Y% receives a development plan, the top X% gets promoted; the middle zone stagnates.',
            'description_html' => 'Everyone is distributed relative to each other, bands adjust to the field. Useful when team performance moves together.',
            'pros' => [
                'Automatically follows team level.',
                'Motivating, creates competitive situation.',
            ],
            'cons' => [
                'There will always be "winners" and "losers," even with good overall performance.',
                'Can be stressful with excessive competition.',
            ],
            'when' => 'For competition-oriented teams (sales, startup environment) where relative excellence is important.',
            'fields' => [
                'threshold_bottom_pct' => 'Bottom Band Ratio (%)',
                'threshold_top_pct'    => 'Top Band Ratio (%)',
            ],
        ],

        // SUGGESTED panel
        'suggested' => [
            'title' => 'Advanced Intelligence',
            'meta'  => 'At the end of the evaluation period, the THEMIS AI Engine determines unique score thresholds tailored to the employee team.',
            'description_html' => 'AI recommends thresholds based on previous periods, current results, and reliability patterns. Supports quick decisions with management control.',
            'pros' => [
                'Data-driven and dynamic – saves time.',
                'Helps filter out distorting patterns (e.g., downvoting).',
                'Works from vast amounts of company-specific data when setting score thresholds.'
            ],
            'cons' => [
                'Less transparent to employees, may require explanation of threshold calculation method.',
                'No tangible "threshold," thresholds may be different for each measurement.',
            ],
            'when' => 'For at least medium-sized companies where reducing bias is important, or where we suspect subjects may cheat during completion.',
        ],

        // Buttons
        'buttons' => [
            'save_mode'      => 'Select Mode',
            'save_settings'  => 'Save Changes',
        ],

        // JS messages – SAME LOCATION
        'confirm'        => 'Warning!',
        'warn_strict_on' => 'Enabling strict anonymous mode will automatically disable AI telemetry if it was enabled. The setting can be changed at any time, but may cause errors in time-series data.',
        'warn_ai_on'     => 'You are enabling AI telemetry. During measurement periods, users\' behavioral patterns are analyzed anonymously using AI tools and results are weighted in score calculations.',
        'warn_ai_off'    => 'You are disabling AI telemetry. The system will not filter fraud (e.g., downvoting, upvoting, careless completion).',
        'saved'          => 'Setting saved',
        'error'          => 'An error occurred',

        'yes' => 'Yes',
        'no'  => 'Cancel',
    ],
];