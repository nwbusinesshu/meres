<?php
return [
    // Page titles and navigation
    'title' => 'Bónuszok',
    'bonuses' => 'Bónuszok',
    'manage-bonuses' => 'Bónuszok kezelése',
    
    // Assessment selection
    'select-assessment' => 'Válassz értékelést',
    'no-closed-assessments' => 'Nincsenek lezárt értékelések',
    'closed-at' => 'Lezárva',
    'assessment-period' => 'Értékelési időszak',
    
    // Table headers
    'employee' => 'Alkalmazott',
    'position' => 'Pozíció',
    'assessment-result' => 'Értékelési eredmény',
    'bonus-amount' => 'Bónusz összeg',
    'net-wage' => 'Nettó bér',
    'currency' => 'Pénznem',
    'payment-status' => 'Fizetési státusz',
    'actions' => 'Műveletek',
    'department' => "Részleg",
    "bonus-malus-level" => "Szint",
    'bonus-list' => "Bónusz lista",
    
    // Payment status
    'paid' => 'Kifizetve',
    'unpaid' => 'Nincs kifizetve',
    'toggle-payment' => 'Fizetési státusz váltása',
    'mark-as-paid' => 'Jelölés kifizetettként',
    'mark-as-unpaid' => 'Jelölés ki nem fizetettként',
    'total-bonuses' => "Teljes összeg",
    
    // Bonus calculations
    'bonus-calculated' => 'Bónusz kiszámítva',
    'malus-calculated' => 'Malus kiszámítva',
    'no-wage-data' => 'Nincs bér adat',
    'wage-not-set' => 'Bér nincs beállítva',
    
    // Configuration
    'configure-multipliers' => 'Szorzók konfigurálása',
    'multiplier-help-text' => 'Állítsd be a bónusz/malus szorzókat minden szinthez. A bónusz összeg = nettó bér × szorzó.',
    'level' => 'Szint',
    'category' => 'Kategória',
    'multiplier' => 'Szorzó',
    'reset-defaults' => 'Alapértékek visszaállítása',
    'default-multipliers' => 'Alapértelmezett szorzók (magyar)',
    
    // Multiplier ranges
    'range-1-3' => '1-3. szint',
    'range-4-6' => '4-6. szint',
    'range-7-9' => '7-9. szint',
    'range-10-12' => '10-12. szint',
    'range-13-15' => '13-15. szint',
    
    // Wage management
    'wage-help-text' => 'Adja meg a nettó havi bért a bónusz számításához.',
    'save-wage' => 'Bér mentése',
    'wage-saved' => 'Bér sikeresen mentve',
    'wage-save-error' => 'Hiba történt a bér mentése során',
    
    // Configuration save
    'config-saved' => 'Konfiguráció sikeresen mentve',
    'config-save-error' => 'Hiba történt a konfiguráció mentése során',
    
    // Payment toggle
    'payment-updated' => 'Fizetési státusz frissítve',
    'payment-update-error' => 'Hiba történt a fizetési státusz frissítése során',
    
    // Settings page
    'employees-see-bonuses' => 'Bónuszok megjelenítése',
    'employees-see-bonuses-description' => 'Ha be van kapcsolva, a dolgozók láthatják saját bónusz/malus összegüket az eredmények oldalon.',
    
    // ✅ NEW: Enable bonus calculation setting
    'enable-bonus-calculation' => 'Bónusz számítás bekapcsolása nettó bér alapján',
    'enable-bonus-calculation-description' => 'Ha be van kapcsolva, a rendszer automatikusan számítja a bónusz/malus összegeket az értékelés lezárásakor a beállított szorzók és a dolgozók nettó bére alapján.',
    
    'default-currency' => 'Alapértelmezett pénznem',
    'default-currency-description' => 'Válaszd ki az alapértelmezett pénznemet a bónusz számításokhoz.',
    
    // Export
    'export-csv' => 'Exportálás CSV-be',
    'export-excel' => 'Exportálás Excel-be',
    
    // Messages
    'no-bonuses-to-display' => 'Nincs megjeleníthető bónusz ezen értékeléshez',
    'bonuses-calculated-on-close' => 'A bónuszok automatikusan kiszámításra kerülnek az értékelés lezárásakor',
    
    // Errors
    'error-loading-bonuses' => 'Hiba a bónuszok betöltése során',
    'error-no-assessment' => 'Nincs kiválasztott értékelés',
    
    // Multiplier categories
    'malus-levels' => 'Malus szintek',
    'neutral-level' => 'Alapszint',
    'bonus-levels' => 'Bónusz szintek',
    
    // Reset functionality
    'reset-confirm-title' => 'Alapértékek visszaállítása?',
    'reset-confirm-text' => 'Ez visszaállítja az összes szorzót a magyar alapértékekre.',
    'reset-success' => 'Visszaállítva',
    'reset-success-text' => 'Ne felejtsd el menteni a változtatásokat!',
    
    // Additional messages
    'config-load-failed' => 'Nem sikerült betölteni a konfigurációt',
    'config-saved' => 'Konfiguráció sikeresen mentve',
    'marked-paid' => 'Kifizetettnek jelölve',
    'marked-unpaid' => 'Kijelölve ki nem fizetettként',
    'toggle-failed' => 'Nem sikerült módosítani a státuszt',
];