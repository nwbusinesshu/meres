<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Language Lines - Hungarian
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in email templates
    |
    */

    // Password Setup Email
    'password_setup' => [
        'subject' => 'Jelszó beállítása – :org_name',
        'title' => 'Jelszó beállítás',
        'greeting' => 'Üdvözlünk a **:org_name** szervezet 360 értékelési rendszerében!',
        'invitation' => 'Meghívást kaptál a rendszerbe a **:email** e-mail címmel.',
        'action_text' => 'Az első belépéshez kattints az alábbi gombra és állíts be jelszót:',
        'button' => 'Jelszó beállítása',
        'expires' => 'Ez a link **:expires_at** időpontig érvényes.',
        'ignore' => 'Ha nem te kezdeményezted, hagyd figyelmen kívül ezt a levelet.',
        'salutation' => 'Üdv,',
    ],

    // Password Reset Email
    'password_reset' => [
        'subject' => 'Jelszó visszaállítása – :org_name',
        'title' => 'Jelszó visszaállítás',
        'intro' => 'A(z) **:org_name** rendszerében jelszó visszaállítást kértünk a **:email** fiókhoz.',
        'action_text' => 'Kattints az alábbi gombra az új jelszó beállításához:',
        'button' => 'Jelszó visszaállítása',
        'expires' => 'Ez a link **:expires_at** időpontig érvényes.',
        'warning' => 'Ha nem te kérted, azonnal értesítsd a céges admin kapcsolattartód.',
        'salutation' => 'Üdv,',
    ],

    // Email Verification Code
    'verification_code' => [
        'subject' => 'Bejelentkezési ellenőrző kód',
        'greeting' => 'Kedves :user_name!',
        'intro' => 'A bejelentkezéshez szükséges ellenőrző kódod:',
        'code_label' => 'Ellenőrző kód:',
        'expires' => 'Ez a kód 10 percig érvényes.',
        'warning' => 'Ha nem te próbáltál meg bejelentkezni, kérjük, hagyd figyelmen kívül ezt az emailt.',
        'salutation' => 'Üdvözlettel,',
        'team' => 'A Quarma360 csapata',
    ],

    // Assessment Started Email
    'assessment_started' => [
        'subject' => 'Értékelési időszak elindult – :org_name',
        'title' => 'Értékelési időszak elkezdődött',
        'greeting' => 'Kedves :name!',
        'intro' => 'Értesítünk, hogy a **:org_name** szervezetben új értékelési időszak kezdődött.',
        'deadline_info' => 'A kitöltés határideje: **:deadline**',
        'action_text' => 'Jelentkezz be a rendszerbe az értékelések kitöltéséhez:',
        'button' => 'Bejelentkezés',
        'reminder' => 'Kérjük, a határidőig töltsd ki az értékeléseket.',
        'salutation' => 'Üdvözlettel,',
    ],

    // Assessment Closed Email
    'assessment_closed' => [
        'subject' => 'Értékelési időszak lezárva – :org_name',
        'title' => 'Értékelési időszak lezárult',
        'greeting' => 'Kedves :name!',
        'intro' => 'Az értékelési időszak lezárult a **:org_name** szervezetben.',
        'results_ready' => 'Az eredményeid most már megtekinthetők a rendszerben.',
        'action_text' => 'Jelentkezz be az eredmények megtekintéséhez:',
        'button' => 'Eredmények megtekintése',
        'reminder' => 'Az eredmények részletes bontását a rendszerben találod.',
        'salutation' => 'Üdvözlettel,',
    ],

    // Ticket Notification Email
    'ticket_notification' => [
        'subject_new' => 'Új support jegy létrehozva – #:ticket_id',
        'subject_update' => 'Support jegy frissítve – #:ticket_id',
        'title_new' => 'Új support jegy',
        'title_update' => 'Support jegy frissítve',
        'greeting' => 'Kedves :name!',
        'new_ticket_intro' => 'Új support jegy lett létrehozva. Jegy azonosító: **#:ticket_id**',
        'update_intro' => 'A support jegyed frissítve lett. Jegy azonosító: **#:ticket_id**',
        'ticket_title' => 'Tárgy',
        'status' => 'Státusz',
        'priority' => 'Prioritás',
        'conversation' => 'Beszélgetés előzményei',
        'action_text' => 'Jelentkezz be a teljes beszélgetés megtekintéséhez:',
        'button' => 'Jegy megtekintése',
        'salutation' => 'Üdvözlettel,',
    ],

    // Payment Pending Email
    'payment_pending' => [
        'subject' => 'Nyitott fizetés – :org_name',
        'title' => 'Fizetésre vár',
        'greeting' => 'Kedves :name!',
        'intro' => 'Nyitott fizetés található a **:org_name** szervezet fiókjában.',
        'assessment_info' => 'Értékelési időszak azonosító: **#:assessment_id**',
        'amount' => 'Fizetendő összeg',
        'created' => 'Létrehozva',
        'action_text' => 'Jelentkezz be a fizetés rendezéséhez:',
        'button' => 'Fizetés',
        'note' => 'Az értékelési időszak lezárásához szükséges a fizetés teljesítése.',
        'salutation' => 'Üdvözlettel,',
    ],

    // Payment Success Email
    'payment_success' => [
        'subject' => 'Sikeres fizetés – :org_name',
        'title' => 'Fizetés sikeresen teljesítve',
        'greeting' => 'Kedves :name!',
        'intro' => 'A fizetés sikeresen teljesítve lett a **:org_name** szervezet számára.',
        'amount' => 'Fizetett összeg',
        'invoice_number' => 'Számlaszám',
        'paid_at' => 'Fizetés időpontja',
        'processing' => 'Feldolgozás alatt',
        'invoice_ready' => 'A számlát letöltheted az alábbi gombra kattintva:',
        'download_button' => 'Számla letöltése',
        'thank_you' => 'Köszönjük a fizetést!',
        'salutation' => 'Üdvözlettel,',
    ],

    // Assessment Progress Email (Daily Reminder)
    'assessment_progress' => [
        'subject' => 'Értékelési állapot – :org_name',
        'title' => 'Értékelési időszak állapota',
        'greeting' => 'Kedves :name!',
        'intro' => 'Napi emlékeztető az aktuális értékelési időszakról a **:org_name** szervezetben.',
        'completion_status' => 'Kitöltési állapot',
        'assessments_completed' => 'Értékelések kitöltve',
        'rankings_completed' => 'Rangsorolások kitöltve',
        'deadline' => 'Határidő',
        'payment_warning' => 'Fizetési figyelmeztetés',
        'payment_blocked' => 'Nyitott fizetés (:amount) blokkolja az értékelési időszak lezárását. Kérjük, rendezd a fizetést.',
        'action_text' => 'Jelentkezz be a részletek megtekintéséhez:',
        'button' => 'Bejelentkezés',
        'salutation' => 'Üdvözlettel,',
    ],

    // Common elements
    'footer' => [
        'copyright' => '© :year :app_name. Minden jog fenntartva. Ezt a levelet azért kaptad, mert e-mail címed regisztrált felhasználója a QUARMA360 teljesítményértékelő alkalmazásnak. Ha szerinted tévedés történt, kérjük, tekintsd levelünket tárgytalannak, vagy vedd fel velünk a kapcsolatot!',
    ],
];