<?php
return [
  "assess" => "Evaluate",

  "question" => "Question",

  "self" => "Yourself",

  "warning" => "Warning!",

  "warning-1" => "Your answers will not be saved if you exit or leave the page!",

  "warning-2" => "All questions must be answered!",

  "warning-3" => "You have given multiple identical answers within this competency. Please strive for honest completion and reconsider whether you have marked correctly!",

  "warning-4" => "Your self-evaluation cannot be either 0% or 100%. Please strive for honest completion!",

  "info" => "Information:",

  "info-1" => "The submitted data is processed anonymously, and the system immediately aggregates the responses. Only the fact that you have evaluated the person is saved, not how you evaluated them!",

  "send-in" => "Submit Answers",

  "send-in-confirm" => "Are you sure you want to submit your answers?",
  
  "send-in-success" => "Your answers have been successfully submitted!",
  "telemetry-toast" => "Telemetry is running on this page",

  // AssessmentValidator - Creation Validation
    'min-users-required' => 'At least :min active users are required to start the assessment. Currently: :count users.',
    'and-more' => '(and :count more)',
    'no-competencies' => 'The following users have no competencies assigned: :users. Every user must have at least 1 competency.',
    'no-relations' => 'The following users have no relationships defined: :users. Every user must have at least 1 colleague or subordinate relationship.',
    
    // AssessmentValidator - Close Validation
    'not-found' => 'Assessment not found.',
    'no-snapshot' => 'No snapshot exists for this assessment.',
    'no-self-evaluation' => 'The following users have not completed their self-evaluation: :users. Self-evaluation is mandatory for all users.',
    'no-ceo-rank' => 'The following users were not ranked by any CEO: :users. All non-CEO users must be ranked.',
    'ceo-no-feedback' => 'The following CEOs did not receive feedback from their subordinates: :users. All CEOs must be evaluated by their subordinates (manager relationship).',
    'no-external-feedback' => 'The following users did not receive external evaluation: :users. Every user must be evaluated by at least one other user.',

     // SnapshotService - Snapshot Creation
    'org-not-found' => 'Organization not found: :org_id',
    'no-active-users' => 'No active users in the organization (org_id: :org_id)',
    'no-relations-defined' => 'No relationships defined in the organization (org_id: :org_id)',

    // SuggestedThresholdService - AI Errors
    'ai-key-missing' => 'AI API key is not configured.',
    'ai-connection-failed' => 'NWB AI API connection error: The server is unreachable. Please check your internet connection.',
    'ai-auth-failed' => 'NWB AI API authentication error.',
    'ai-rate-limit' => 'NWB AI API rate limit exceeded: Too many requests. Please try again in 1-2 minutes.',
    'ai-server-error' => 'NWB AI API server error: The service is temporarily unavailable.',
    'ai-http-error' => 'NWB AI API error (HTTP :status): :message',
    'ai-call-failed' => 'NWB AI API call failed: :message',

     // ThresholdService - FIXED Mode (Rögzített küszöbök)
    'fixed-config-missing' => 'Threshold configuration is missing. Please check settings and provide the ":key" value.',
    'fixed-up-lte-down' => 'The promotion threshold cannot be less than or equal to the demotion threshold. Please set the thresholds correctly.',
    
    // ThresholdService - HYBRID Mode (Hibrid küszöbök)
    'hybrid-config-missing' => 'Hybrid threshold configuration is missing. Please check settings and provide the ":key" value.',
    'hybrid-top-pct-invalid' => 'The specified upper percentage value is invalid. Please provide a value between 0 and 100.',
    'hybrid-threshold-collision' => 'The calculated promotion threshold is lower than or equal to the demotion threshold. Please modify the settings or increase the minimum threshold.',
    
    // ThresholdService - DYNAMIC Mode (Dinamikus küszöbök)
    'dynamic-config-missing' => 'Dynamic threshold configuration is missing. Please check settings and provide the ":key" value.',
    'dynamic-top-pct-invalid' => 'The specified upper percentage value is invalid. Please provide a value between 0 and 100.',
    'dynamic-bottom-pct-invalid' => 'The specified lower percentage value is invalid. Please provide a value between 0 and 100.',
    'dynamic-pct-sum-invalid' => 'The sum of upper and lower percentage values cannot be 100% or greater. Please reduce the values to allow for a middle band.',
    'dynamic-threshold-collision' => 'The calculated promotion threshold is lower than or equal to the demotion threshold. Please modify the percentage values.',
    
    // ThresholdService - SUGGESTED Mode (AI által javasolt küszöbök)
    'suggested-invalid-threshold' => 'The AI-suggested thresholds are invalid. Please try again or select a different threshold calculation method.',
    'suggested-below-minimum' => 'The AI-suggested promotion threshold (:up) is lower than the configured minimum (:min). The AI recommendation is not acceptable, please modify the minimum value or choose a different method.',
    'suggested-threshold-collision' => 'The AI-suggested promotion threshold is lower than or equal to the demotion threshold. The AI recommendation is invalid, please try again.',

  // Assessment creation/modification
  'unpaid_initial_payment' => 'Assessment period cannot be started before the initial payment is settled. Please complete the payment in the Billing section.',
  'no_organization_selected' => 'No organization selected.',
  'cannot_start' => 'The assessment cannot be started.',
  'already_running' => 'An assessment period is already in progress.',
  'snapshot_creation_failed' => 'Snapshot creation failed: :error',

  // Assessment closure
  'not_authorized_organization' => 'Unauthorized organization.',
  'cannot_close_yet' => 'The assessment cannot be closed yet.',
  'invalid_threshold_method' => 'Invalid threshold calculation method: :method',
  'no_participants' => 'No participants in the assessment.',
  'no_scores' => 'No scores in the assessment. Nothing to close.',

  // AI related
  'ai_response_empty' => 'AI response is empty.',
  'ai_response_missing_thresholds' => 'AI response does not contain thresholds.',
  'ai_calculation_failed' => 'AI threshold calculation failed: :error',

  // Snapshot/Results
  'snapshot_save_failed' => 'Failed to save results to snapshot.',
  'closed_successfully' => 'Assessment successfully closed.',

  // JSON encoding
  'json_encoding_failed' => 'JSON encoding failed (:context): :error',
  'json_result_empty' => 'JSON encoding result is empty (:context)',
  'json_too_large' => 'JSON too large (:context): :size MB. Maximum 95 MB.',

  // Bonuses
'bonus_feature_disabled' => 'The bonus feature is not enabled. Please enable it in Settings.',

];