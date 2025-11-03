<?php
return [
  "ranking" => "Rangsorolás",
  "employees" => "Alkalmazottak",
  "value" => "Érték",
  "name" => "Megnevezés",
  "min" => "Minimum",
  "max" => "Maximum",
  "head" => "fő",
  "max-warning" => "Több alkalmazott nem kerülhet erre a szintre!",
  "min-warning" => "Egy vagy több szintnél nem teljesül az alkalmazottak minimum száma!",
  "no-mobile" => "Kérlek a rangsorolást végezd nagyobb felbontású eszközről!",
  "save-ranks" => "Rangsorolás véglegesítése",
  "save-ranks-confirm" => "Biztosan véglegesíted a rangsorolást?",
  "save-ranks-success" => "A rangsorolás sikeresen véglegesítésre került!",
  
  // New mobile-specific translations
  "tap-to-add" => "Kattints ide alkalmazottak hozzáadásához",
  "no-employees-left" => "Minden alkalmazott be van sorolva",

  // Access control
    'no_running_assessment' => 'Nincs futó mérés.',
    'no_access_to_ranking' => 'A rangsor oldalhoz nincs jogosultság.',
    'no_assigned_department' => 'Nincs hozzárendelt részleg.',
    'no_subordinates' => 'Nincs beosztott a részlegeidben.',
    
    // Validation
    'invalid_request_ranks_not_array' => 'Hibás kérés: ranks nem tömb.',
    'unknown_rank_category' => 'Ismeretlen rang kategória: :id',
    'unauthorized_user_id' => 'Jogosulatlan felhasználó azonosító: :id',
    'rank_minimum_required' => 'A(z) :rank kategóriában legalább :min fő szükséges.',
    'rank_maximum_exceeded' => 'A(z) :rank kategóriában legfeljebb :max fő engedélyezett.',
    
    // Success
    'saved' => 'Mentve',
];