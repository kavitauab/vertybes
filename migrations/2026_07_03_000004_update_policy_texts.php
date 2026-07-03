<?php
/**
 * Migration: Replace DRAFT policy stubs with proper basic LT policy texts.
 * Overwrites the two page bodies (they were seeded as placeholders); everything
 * stays editable in the admin afterwards.
 */

class UpdatePolicyTextsMigration {
    public function up($db) {
        $privacy = <<<TXT
Duomenų valdytojas: Tomas Petrikaitis, el. paštas tomas@petrikaitis.com.

Kokius duomenis renkame

• Tavo atsakymus į testo klausimus, pasirinktas vertybes ir palyginimų pasirinkimus — jie reikalingi rezultatui apskaičiuoti.
• El. pašto adresą — tik jei pats jį pateiki (norėdamas gauti rezultatą ar pranešimą apie testo startą).
• Techninius duomenis: užšifruotą (negrįžtamai koduotą) IP adresą ir naršyklės tipą — saugumo ir piktnaudžiavimo prevencijos tikslais.

Dirbtinis intelektas

Tavo atsakymai siunčiami AI paslaugų teikėjui (OpenAI), kad būtų pasiūlytos vertybės. Kartu su atsakymais nesiunčiame nei tavo el. pašto, nei kitų asmenį identifikuojančių duomenų. Pagal OpenAI sąlygas per API pateikti duomenys nenaudojami AI modelių mokymui.

Kiek laiko saugome

• Testo sesijos duomenys saugomi iki 12 mėnesių, vėliau ištrinami.
• El. pašto adresas saugomas tol, kol atšauksi sutikimą.

Tavo teisės

Gali bet kada susipažinti su savo duomenimis, prašyti juos ištaisyti ar ištrinti, taip pat atšaukti sutikimą. Kreipkis el. paštu tomas@petrikaitis.com — atsakysime ne vėliau kaip per 30 dienų. Jei manai, kad tavo teisės pažeistos, gali kreiptis į Valstybinę duomenų apsaugos inspekciją (vdai.lrv.lt).

Slapukai

Naudojame tik būtinuosius slapukus — be jų testas negali veikti. Analitikos ar rinkodaros slapukų nenaudojame. Daugiau — Slapukų politikoje.
TXT;

        $cookies = <<<TXT
Šioje svetainėje naudojami tik būtinieji slapukai — tie, be kurių testas negali veikti. Analitikos, rinkodaros ar trečiųjų šalių slapukų nenaudojame.

Naudojami slapukai

• vt_session — saugo tavo testo eigą, kad atsakymai nedingtų perkrovus puslapį. Galioja 30 dienų.
• PHP sesijos slapukas — naudojamas tik administravimo aplinkoje, lankytojams nenustatomas.

Kaip valdyti slapukus

Slapukus gali bet kada ištrinti savo naršyklės nustatymuose. Ištrynus vt_session slapuką, pradėto testo eiga bus prarasta.

Klausimai — tomas@petrikaitis.com.
TXT;

        $db->query(
            "UPDATE ui_texts SET text_value = ?, context = 'Privatumo puslapio tekstas' WHERE text_key = 'privacy.page.body'",
            [$privacy]
        );
        $db->query(
            "UPDATE ui_texts SET text_value = ?, context = 'Slapukų puslapio tekstas' WHERE text_key = 'cookies.page.body'",
            [$cookies]
        );
    }

    public function down($db) {
        // Texts are content — no rollback.
    }
}
