<?php
/**
 * Migration: v3.1 email-gate funnel (PERDAVIMAS-v3-2026-07-29.md).
 * Screen 8 becomes an email gate; 9a unlocked / 9b skipped; 10 = shared
 * VISION page. Retires the "Išsiųsta" page copy and the email block that
 * used to live on screen 10.
 */

class EmailGateFlowMigration {
    public function up($db) {
        $texts = [
            // 8 · gate
            ['result.chip', 'Rezultatas paruoštas', 'Rezultato ženkliukas (8)'],
            ['result.title', 'Štai kas tave veda', 'Rezultato antraštė'],
            ['result.primaryKicker', 'Tavo pagrindinė vertybė', 'Pagrindinės vertybės etiketė'],
            ['result.staticLine', 'Šią vertybę tavo atsakymai paminėjo dažniausiai.', 'Statinė eilutė po pagrindine vertybe (vienoda visiems)'],
            ['unlock.title', 'Kai įrašysi el. paštą, atrakinsi', 'Vartų kortelės antraštė'],
            ['unlock.sub', 'Šį rezultatą verta turėti po ranka. Po kelių dienų daugelis pastebi tai, ko pirmą kartą nematė.', 'Vartų kortelės tekstas'],
            ['unlock.b1', 'Antrą tavo vertybę', 'Vartų punktas 1'],
            ['unlock.b2', 'Ką šis derinys reiškia kasdien', 'Vartų punktas 2'],
            ['unlock.b3', 'Kur šis derinys kelia įtampą', 'Vartų punktas 3'],
            ['unlock.cta', 'Gauti rezultatą', 'Vartų mygtukas'],
            ['unlock.skip', 'Praleisti', 'Praleidimo nuoroda'],
            ['unlock.success', 'Atrakinta. Rezultatas išsiųstas.', 'Sėkmės eilutė po pateikimo'],
            // 9a · unlocked
            ['unlocked.badge', 'Atrakinta', 'Atrakinta ženkliukas (9a)'],
            ['unlocked.meaningTitle', 'Ką tai reiškia kasdien', 'Reikšmės blokas (9a)'],
            ['unlocked.tensionTitle', 'Kur ši įtampa jau pasirodo', 'Įtampos blokas (9a)'],
            ['unlocked.cta', 'Kas dar lemia sprendimus? →', 'Perėjimas į 10 (9a)'],
            // 9b · skipped
            ['skip.reminder', 'Jei persigalvosi, rezultatas vis dar laukia. Įrašyk el. paštą ir atrakinsi visą.', 'Priminimo kortelė (9b)'],
            ['skip.cta', 'Atrakinti', 'Priminimo mygtukas (9b)'],
            ['skip.next', 'Toliau', 'Perėjimas į 10 (9b)'],
            // 10 · shared VISION page
            ['next.reward', 'Puiku. Dabar žinai, kas tave labiausiai veda.', 'Kito žingsnio antraštė'],
            ['next.gapIntro', 'Bet šis testas neatsako į vieną svarbų klausimą.', 'Perėjimas į klausimą'],
            ['next.question', 'Kodėl kartojasi tie patys sprendimai?', 'Smalsumo klausimas'],
            ['next.gap1', 'Kas stabdo pokyčius', 'Spraga 1'],
            ['next.gap2', 'Kaip vertybės susiduria realiose situacijose', 'Spraga 2'],
            ['next.gap3', 'Kaip priimti sprendimus, kai vertybės susiduria', 'Spraga 3'],
            ['result.interpLater', 'Išsamią interpretaciją atsiųsime el. paštu.', 'Kai interpretacija dar ruošiama'],
        ];
        foreach ($texts as [$key, $value, $context]) {
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), context = VALUES(context)",
                [$key, $value, $context]
            );
        }

        // Retired with the old tail: standalone "Išsiųsta" page, the email block
        // on screen 10, the 4th gap row (now the headline question), old kickers.
        $db->query("DELETE FROM ui_texts WHERE text_key IN (
            'sent.title','sent.to','sent.toFallback','sent.thanks','sent.follow','sent.followLink',
            'sent.spam','sent.again','next.emailTitle','next.emailCta','next.gap4','next.footer',
            'next.heroSub','next.hero','result.rank1','result.tensionTitle','result.meaningTitle',
            'result.nextCta','result.nextCaption','calc.title')");
    }

    public function down($db) {
        // Content migration — no rollback.
    }
}
