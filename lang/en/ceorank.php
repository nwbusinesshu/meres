<?php
return [
  "ranking" => "Ranking",
  "employees" => "Employees",
  "value" => "Value",
  "name" => "Name",
  "min" => "Minimum",
  "max" => "Maximum",
  "head" => "person",
  "max-warning" => "No more employees can be placed at this level!",
  "min-warning" => "One or more levels do not meet the minimum number of employees!",
  "no-mobile" => "Please complete the ranking from a higher resolution device!",
  "save-ranks" => "Finalize Ranking",
  "save-ranks-confirm" => "Are you sure you want to finalize the ranking?",
  "save-ranks-success" => "Ranking has been successfully finalized!",
  
  // New mobile-specific translations
  "tap-to-add" => "Click here to add employees",
  "no-employees-left" => "All employees have been classified",
  // Access control
    'no_running_assessment' => 'No assessment is running.',
    'no_access_to_ranking' => 'You do not have access to the ranking page.',
    'no_assigned_department' => 'No department assigned.',
    'no_subordinates' => 'No subordinates in your departments.',
    
    // Validation
    'invalid_request_ranks_not_array' => 'Invalid request: ranks is not an array.',
    'unknown_rank_category' => 'Unknown rank category: :id',
    'unauthorized_user_id' => 'Unauthorized user ID: :id',
    'rank_minimum_required' => 'At least :min people are required in the :rank category.',
    'rank_maximum_exceeded' => 'A maximum of :max people are allowed in the :rank category.',
    
    // Success
    'saved' => 'Saved',
];