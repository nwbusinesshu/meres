<?php
return [
  "assess" => "Értékeld",

  "question" => "Kérdés",

  "self" => "Önmagad",

  "warning" => "Figyelem!",

  "warning-1" => "Kilépés vagy az oldal elhagyása esetén a válaszok nem kerülnek mentésre!",

  "warning-2" => "Minden kérdésre kötelező értéket megadni!",

  "warning-3" => "Az adott kompetencián belül több egyforma választ is adtál. Kérlek, törekedj a becsületes kitöltésre, gondold végig, hogy biztosan jól jelöltél-e!",

  "warning-4" => "Az önértékelésed nem lehet sem 0, sem pedig 100 %-os. Kérlek, törekedj a becsületes kitöltésre!",

  "info" => "Információ:",

  "info-1" => "A beküldött adatokat névtelenül kezeljük, a beküldött válaszokat a rendszer rögtön összesíti. Csak az kerül mentésre, hogy értékelted az adott személyt, az nem, hogy hogyan!",

  "send-in" => "Válaszok beküldése",

  "send-in-confirm" => "Biztosan beküldöd a válaszokat?",
  
  "send-in-success" => "A válaszok sikeresen beküldésre kerültek!",
  "telemetry-toast" => "Az oldalon telemetria fut",

  // AssessmentValidator - Creation Validation
    'min-users-required' => 'Legalább :min aktív felhasználó szükséges az értékelés indításához. Jelenleg: :count fő.',
    'and-more' => '(és még :count fő)',
    'no-competencies' => 'A következő felhasználókhoz nincsenek kompetenciák rendelve: :users. Minden felhasználónak legalább 1 kompetenciával kell rendelkeznie.',
    'no-relations' => 'A következő felhasználóknak nincsenek kapcsolatok definiálva: :users. Minden felhasználónak legalább 1 kolléga vagy beosztott kapcsolattal kell rendelkeznie.',
    
    // AssessmentValidator - Close Validation
    'not-found' => 'Az értékelés nem található.',
    'no-snapshot' => 'Nincs snapshot az értékeléshez.',
    'no-self-evaluation' => 'A következő felhasználók nem töltötték ki az önértékelést: :users. Minden felhasználónak kötelező az önértékelés.',
    'no-ceo-rank' => 'A következő felhasználókat nem rangsorolta egyetlen CEO sem: :users. Minden nem-CEO felhasználót kötelező rangsorolni.',
    'ceo-no-feedback' => 'A következő CEO-k nem kaptak visszajelzést a beosztottaiktól: :users. Minden CEO-t kötelező értékelni a beosztottjainak (felettesi viszony).',
    'no-external-feedback' => 'A következő felhasználók nem kaptak külső értékelést: :users. Minden felhasználót kötelező értékelni legalább egy másik felhasználónak.',

     // SnapshotService - Snapshot Creation
    'org-not-found' => 'A szervezet nem található: :org_id',
    'no-active-users' => 'Nincsenek aktív felhasználók a szervezetben (org_id: :org_id)',
    'no-relations-defined' => 'Nincsenek definiált kapcsolatok a szervezetben (org_id: :org_id)',

    // SuggestedThresholdService - AI Errors
    'ai-key-missing' => 'AI API kulcs nincs konfigurálva.',
    'ai-connection-failed' => 'NWB AI API kapcsolódási hiba: A szerver nem érhető el. Ellenőrizze az internet kapcsolatot.',
    'ai-auth-failed' => 'NWB AI API hitelesítési hiba.',
    'ai-rate-limit' => 'NWB AI API limit túllépés: Túl sok kérés. Próbálja meg 1-2 perc múlva.',
    'ai-server-error' => 'NWB AI API szerver hiba: A szolgáltatás ideiglenesen nem elérhető.',
    'ai-http-error' => 'NWB AI API hiba (HTTP :status): :message',
    'ai-call-failed' => 'NWB AI API hívás sikertelen: :message',

     // ThresholdService - FIXED Mode (Rögzített küszöbök)
    'fixed-config-missing' => 'A küszöbérték beállítása hiányzik. Ellenőrizze a beállításokat és adja meg a(z) ":key" értéket.',
    'fixed-up-lte-down' => 'Az előléptetési ponthatár nem lehet kisebb vagy egyenlő a visszaléptetési ponthatárnál. Kérem állítsa be helyesen a küszöbértékeket.',
    
    // ThresholdService - HYBRID Mode (Hibrid küszöbök)
    'hybrid-config-missing' => 'A hibrid küszöbérték beállítása hiányzik. Ellenőrizze a beállításokat és adja meg a(z) ":key" értéket.',
    'hybrid-top-pct-invalid' => 'A megadott felső százalékérték érvénytelen. Adjon meg egy 0 és 100 közötti értéket.',
    'hybrid-threshold-collision' => 'A számított előléptetési ponthatár alacsonyabb vagy egyenlő a visszaléptetési ponthatárnál. Módosítsa a beállításokat vagy növelje a minimális ponthatárt.',
    
    // ThresholdService - DYNAMIC Mode (Dinamikus küszöbök)
    'dynamic-config-missing' => 'A dinamikus küszöbérték beállítása hiányzik. Ellenőrizze a beállításokat és adja meg a(z) ":key" értéket.',
    'dynamic-top-pct-invalid' => 'A megadott felső százalékérték érvénytelen. Adjon meg egy 0 és 100 közötti értéket.',
    'dynamic-bottom-pct-invalid' => 'A megadott alsó százalékérték érvénytelen. Adjon meg egy 0 és 100 közötti értéket.',
    'dynamic-pct-sum-invalid' => 'A felső és alsó százalékértékek összege nem lehet 100% vagy nagyobb. Csökkentse az értékeket, hogy legyen középső sáv is.',
    'dynamic-threshold-collision' => 'A számított előléptetési ponthatár alacsonyabb vagy egyenlő a visszaléptetési ponthatárnál. Módosítsa a százalékértékeket.',
    
    // ThresholdService - SUGGESTED Mode (AI által javasolt küszöbök)
    'suggested-invalid-threshold' => 'Az AI által javasolt küszöbértékek érvénytelenek. Kérem próbálja újra vagy válasszon másik küszöbszámítási módszert.',
    'suggested-below-minimum' => 'Az AI által javasolt előléptetési ponthatár (:up) alacsonyabb, mint a beállított minimum (:min). Az AI javaslata nem elfogadható, kérem módosítsa a minimum értéket vagy válasszon másik módszert.',
    'suggested-threshold-collision' => 'Az AI által javasolt előléptetési ponthatár alacsonyabb vagy egyenlő a visszaléptetési ponthatárnál. Az AI javaslata érvénytelen, kérem próbálja újra.',

  // Assessment creation/modification
  'unpaid_initial_payment' => 'Értékelési időszak nem indítható a belépési díj rendezése előtt. Kérjük, rendezze a fizetést a Számlázás menüpontban.',
  'no_organization_selected' => 'Nincs kiválasztott szervezet.',
  'cannot_start' => 'Az értékelés nem indítható el.',
  'already_running' => 'Már van folyamatban értékelési időszak.',
  'snapshot_creation_failed' => 'A snapshot létrehozása sikertelen: :error',

  // Assessment closure
  'not_authorized_organization' => 'Nem jogosult szervezet.',
  'cannot_close_yet' => 'Az értékelés még nem zárható le.',
  'invalid_threshold_method' => 'Érvénytelen küszöbszámítási módszer: :method',
  'no_participants' => 'Nincsenek résztvevők az értékelésben.',
  'no_scores' => 'Nincsenek pontszámok az értékelésben. Nincs mit lezárni.',

  // AI related
  'ai_response_empty' => 'AI válasz üres.',
  'ai_response_missing_thresholds' => 'AI válasz nem tartalmaz küszöbértékeket.',
  'ai_calculation_failed' => 'AI küszöbszámítás sikertelen: :error',

  // Snapshot/Results
  'snapshot_save_failed' => 'Az eredmények mentése a snapshot-ba sikertelen.',
  'closed_successfully' => 'Az értékelés sikeresen lezárva.',

  // JSON encoding
  'json_encoding_failed' => 'JSON kódolás sikertelen (:context): :error',
  'json_result_empty' => 'JSON kódolás eredménye üres (:context)',
  'json_too_large' => 'JSON túl nagy (:context): :size MB. Maximum 95 MB.',

  // Bonuses
'bonus_feature_disabled' => 'A bónusz funkció nincs engedélyezve. Kapcsold be a Beállításokban.',

];