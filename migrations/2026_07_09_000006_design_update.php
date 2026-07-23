<?php
/**
 * Migration: align data model + content with Tomas's design PDFs
 * (tomas/Vertybiu testas/*, 2026-07-09).
 *
 *  - questions get topic labels (review-screen section headers) and per-slot
 *    example placeholders; texts updated to the design's final questions
 *  - values_catalog gets is_core (picker grid) and is_custom (user-entered)
 *  - test_sessions stores AI-generated first-person value statements
 *  - session_results stores pair-based tension/meaning copy
 *  - leads records the explicit send consent
 *  - 6 values / 15 duels; new email-from settings
 *  - ui_texts: full design copy (overwrites — the design is the copy source)
 */

class DesignUpdateMigration {
    public function up($db) {
        // ── Schema ────────────────────────────────────────────────────────────
        if (!$db->columnExists('questions', 'topic_label')) {
            $db->query("ALTER TABLE questions
                ADD COLUMN topic_label VARCHAR(64) NOT NULL DEFAULT '' AFTER text,
                ADD COLUMN placeholders_json TEXT NULL AFTER hint");
        }
        if (!$db->columnExists('values_catalog', 'is_core')) {
            $db->query("ALTER TABLE values_catalog
                ADD COLUMN is_core TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
                ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER is_core");
        }
        if (!$db->columnExists('test_sessions', 'statements_json')) {
            $db->query("ALTER TABLE test_sessions ADD COLUMN statements_json TEXT NULL AFTER top5_json");
        }
        if (!$db->columnExists('session_results', 'tension_text')) {
            $db->query("ALTER TABLE session_results
                ADD COLUMN tension_text TEXT NULL AFTER top_keys_json,
                ADD COLUMN meaning_text TEXT NULL AFTER tension_text");
        }
        if (!$db->columnExists('leads', 'consented')) {
            $db->query("ALTER TABLE leads ADD COLUMN consented TINYINT(1) NOT NULL DEFAULT 0 AFTER top_values");
        }

        // ── Questions (design texts, labels, placeholders) ────────────────────
        $help = 'Rašyk trumpai. Tinka žodžiai ar trumpos frazės. Gali įrašyti iki 6 atsakymų.';
        $questions = [
            ['q1', 'Ką labiausiai mėgsti veikti laisvalaikiu?', 'Laisvalaikis',
             ['Pvz.: Skaityti knygas po žvaigždėmis', 'Pvz.: Meditacija tylioje erdvėje', 'Pvz.: Žygiai gamtoje']],
            ['q2', 'Kas tave nuoširdžiai prajuokina?', 'Kas prajuokina',
             ['Pvz.: Draugų anekdotai', 'Pvz.: Absurdiškos situacijos', 'Pvz.: Netikėti sutapimai']],
            ['q3', 'Kokias savybes labiausiai vertini kituose?', 'Vertinamos savybės',
             ['Pvz.: Sąžiningumas', 'Pvz.: Šiluma', 'Pvz.: Drąsa']],
            ['q4', 'Kas tave labiausiai pykdo?', 'Kas pykdo',
             ['Pvz.: Nesąžiningumas', 'Pvz.: Abejingumas', 'Pvz.: Melas']],
        ];
        foreach ($questions as [$key, $text, $label, $placeholders]) {
            $db->query(
                "UPDATE questions SET text = ?, hint = ?, topic_label = ?, placeholders_json = ?
                 WHERE question_key = ?",
                [$text, $help, $label, json_encode($placeholders, JSON_UNESCAPED_UNICODE), $key]
            );
        }

        // ── Core picker values (design's "Pagrindinės vertybės" grid) ─────────
        $coreKeys = ['laisve','augimas','saziningumas_ir_vientisumas','artumas','pagarba',
                     'drasa','atsakomybe','kurybiskumas','ramybe','autentiskumas',
                     'saviugda','nuotykis','stabilumas','sveikata','itaka',
                     'kompetencija','istikimybe','tiesa','prasmingumas'];
        $ph = implode(',', array_fill(0, count($coreKeys), '?'));
        $db->query("UPDATE values_catalog SET is_core = 1 WHERE value_key IN ($ph)", $coreKeys);

        // ── Settings ──────────────────────────────────────────────────────────
        $settings = [
            'compare_values_count' => '6',
            'min_distinct_values'  => '6',
            'email_from_name'      => 'Vertybių testas',
            'email_from_address'   => 'noreply@vertybes.lt',
            'email_reply_to'       => 'tomas@petrikaitis.com',
        ];
        foreach ($settings as $k => $v) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$k, $v]
            );
        }
        // the old 5-value default must not linger
        $db->query("UPDATE settings SET setting_value = '6' WHERE setting_key = 'min_distinct_values'");

        // ── UI texts (design copy is the source of truth — overwrite) ─────────
        $texts = [
            // Top bar / wordmark
            ['brand.name', 'Vertybių testas', 'Viršutinės juostos pavadinimas'],
            // Intro
            ['intro.hero', 'Suprask, kodėl vieni sprendimai suteikia energijos, o kiti kelia vidinį konfliktą.', 'Intro antraštė'],
            ['intro.sub', 'Rašyk pirmus atsakymus, kurie ateina į galvą. Ilgai negalvok.', 'Intro paantraštė'],
            ['intro.bullet1', 'Pamatysi, kas iš tikrųjų valdo tavo sprendimus', 'Intro punktas 1'],
            ['intro.bullet2', 'Sužinosi savo vidinės įtampos priežastį', 'Intro punktas 2'],
            ['intro.bullet3', 'Gausi aiškų tavo sprendimų kompasą vos per 5 minutes', 'Intro punktas 3'],
            ['intro.cta', 'Pradėti', 'Intro mygtukas'],
            // Steps
            ['steps.of', 'iš 3 žingsnių', 'Žingsnių etiketė'],
            // Consent (step 2)
            ['consent.title', 'Prieš pradedant', 'Sutikimo žingsnio antraštė'],
            ['consent.aiInfo', 'Šiame teste tavo atsakymai bus analizuojami AI pagalba, kad galėtume pasiūlyti vertybes ir pateikti rezultatus. AI padeda šiame procese, tačiau nepriima sprendimų už tave.', 'AI paaiškinimo kortelė'],
            ['consent.linkIntro', 'Prieš tęsdamas, susipažink su', 'Eilutė prieš nuorodą'],
            ['consent.linkText', 'Privatumo politika ir AI naudojimo informacija', 'Politikos nuorodos tekstas'],
            ['consent.checkbox', 'Susipažinau su Privatumo politika ir AI naudojimo informacija.', 'Sutikimo varnelė'],
            ['consent.error', 'Prašome patvirtinti, kad susipažinote su Privatumo politika ir AI naudojimo informacija.', 'Sutikimo klaida'],
            // Policy popup
            ['policy.title', 'Privatumo politika ir AI', 'Politikos lango antraštė'],
            ['policy.privacyTitle', 'Privatumo politika', 'Politikos skilties antraštė'],
            ['policy.privacyBody', "Ši programa renka tik tuos duomenis, kuriuos pats pateiki atsakydamas į klausimus. Tavo atsakymai naudojami tik vertybių analizei atlikti šio seanso metu.\n\nEl. pašto adresas (jei pateiktas) naudojamas tik rezultatui išsiųsti. Mes nesidalijame tavo duomenimis su trečiosiomis šalimis ir nesaugome asmens duomenų be tavo aiškaus sutikimo.\n\nDuomenys saugomi laikantis BDAR (GDPR) reikalavimų. Bet kada gali paprašyti ištrinti savo duomenis parašęs mums el. paštu.", 'Privatumo tekstas'],
            ['policy.aiTitle', 'AI naudojimo informacija', 'AI skilties antraštė'],
            ['policy.aiBody', "Šiame teste tavo atsakymai yra analizuojami dirbtinio intelekto (AI) pagalba, siekiant pasiūlyti vertybes ir pateikti rezultatus. AI padeda šiame procese, tačiau nepriima sprendimų už tave.\n\nAI modelis apdoroja tavo tekstinius atsakymus ir pateikia pasiūlymus. Galutinį pasirinkimą visada darai tu pats. Jokių tavo duomenų nesaugome be sutikimo ir nesidalijame su trečiosiomis šalimis.", 'AI tekstas'],
            ['policy.ok', 'Supratau', 'Politikos lango mygtukas'],
            // Cookies (step 3)
            ['cookies.title', 'Slapukai ir tavo progresas', 'Slapukų žingsnio antraštė'],
            ['cookies.body', 'Naudojame slapukus, kad testas veiktų sklandžiai. Jie išsaugo tavo progresą ir leidžia priminti užbaigti testą, jei išeisi nebaigęs.', 'Slapukų tekstas'],
            ['cookies.accept', 'Priimti visus', 'Slapukų sutikimo mygtukas'],
            ['cookies.decline', 'Atsisakyti', 'Slapukų atsisakymo mygtukas'],
            ['cookies.declined', 'Be būtinųjų slapukų testas negali veikti. Jei apsigalvosi — spausk „Priimti visus“.', 'Rodoma atsisakius'],
            ['cookies.popup.title', 'Naudojami slapukai', 'Slapukų sąrašo antraštė'],
            ['cookies.popup.intro', 'Žemiau pateikiamas visų šiame teste naudojamų slapukų sąrašas. Visi jie yra būtini ir negali būti išjungti.', 'Slapukų sąrašo įžanga'],
            ['cookies.popup.required', 'Būtinas', 'Slapuko ženkliukas'],
            ['cookies.popup.duration', 'Galiojimas:', 'Galiojimo etiketė'],
            ['cookies.c1.name', 'vt_session', 'Slapukas 1 pavadinimas'],
            ['cookies.c1.desc', 'Išsaugo tavo seanso duomenis ir progresą, kad atsakymai nepradingtų perkraunant puslapį.', 'Slapukas 1 aprašymas'],
            ['cookies.c1.duration', '30 dienų', 'Slapukas 1 galiojimas'],
            ['cookies.c2.name', 'consent_given', 'Slapukas 2 pavadinimas'],
            ['cookies.c2.desc', 'Įsimena, kad davei sutikimą, kad to nereikėtų kartoti kiekvieną kartą.', 'Slapukas 2 aprašymas'],
            ['cookies.c2.duration', '1 metai', 'Slapukas 2 galiojimas'],
            ['cookies.popup.close', 'Uždaryti', 'Slapukų sąrašo mygtukas'],
            // Questions
            ['questions.progress', 'Klausimas {current} iš {total}', 'Klausimų progresas'],
            ['questions.answerLabel', 'Atsakymas {n}', 'Atsakymo etiketė'],
            ['questions.required', 'Būtinas', 'Privalomo lauko ženkliukas'],
            ['questions.addAnswer', 'Pridėti dar vieną atsakymą', 'Papildomo atsakymo mygtukas'],
            ['questions.error', 'Įvesk bent vieną atsakymą, kad galėtum tęsti.', 'Klausimo klaida'],
            ['questions.autosave', 'Atsakymai išsaugomi automatiškai.', 'Autosave užrašas'],
            ['questions.needMore', 'Tavo atsakymuose radome mažiau nei 6 skirtingas vertybes. Papildyk atsakymus, kad rezultatas būtų tikslesnis.', 'Per mažai vertybių'],
            // AI loading
            ['loading.title', 'Analizuoju tavo atsakymus', 'AI laukimo antraštė'],
            ['loading.sub', 'Dabar AI pasiūlys vertybę prie kiekvieno tavo atsakymo. Jei netiks — galėsi pakeisti.', 'AI laukimo tekstas'],
            ['loading.chip', 'Ruošiu pasiūlymus...', 'AI laukimo ženkliukas'],
            ['loading.slow.title', 'Užtrunka ilgiau nei įprastai.', 'Ilgo laukimo antraštė'],
            ['loading.slow.body', 'Ruošiame tavo asmeninę vertybių apžvalgą. Nesijaudink — tavo atsakymai išsaugoti.', 'Ilgo laukimo tekstas'],
            ['loading.wait', 'Palaukti dar', 'Ilgo laukimo mygtukas'],
            ['loading.retry', 'Bandyti dar kartą', 'Kartojimo mygtukas'],
            // Review
            ['review.title', 'Patvirtink arba pakeisk vertybes', 'Peržiūros antraštė'],
            ['review.sub', 'AI pasiūlė vertybę prie kiekvieno atsakymo. Jei netinka — pakeisk.', 'Peržiūros paantraštė'],
            ['review.answerLabel', 'Tavo atsakymas', 'Atsakymo etiketė'],
            ['review.valueLabel', 'Priskirta vertybė', 'Vertybės etiketė'],
            ['review.change', 'Keisti', 'Keitimo mygtukas'],
            ['review.uncertain', 'AI nėra tikras — pasirink pats', 'Neaiškaus AI ženkliukas'],
            ['review.uncertainBody', 'Šis atsakymas gali reikšti skirtingas vertybes. Pasirink, kuri tau artimiausia.', 'Neaiškaus AI tekstas'],
            // Picker
            ['picker.title', 'Pasirinkite vertybę', 'Pasirinkimo antraštė'],
            ['picker.search', 'Ieškok vertybės arba įrašyk savo', 'Paieškos laukas'],
            ['picker.coreTitle', 'Pagrindinės vertybės', 'Pagrindinių vertybių antraštė'],
            ['picker.requiredChip', 'Privaloma pasirinkti', 'Privalomumo ženkliukas'],
            ['picker.allTitle', 'Paieškos rezultatai', 'Paieškos rezultatų antraštė'],
            ['picker.customTitle', 'Kita vertybė', 'Savos vertybės antraštė'],
            ['picker.customPlaceholder', 'Įveskite savo vertybę...', 'Savos vertybės laukas'],
            ['picker.save', 'Išsaugoti', 'Pasirinkimo mygtukas'],
            // Compare
            ['compare.introTitle', 'Toliau lyginsime šias {n} vertybes', 'Lyginimo įžangos antraštė'],
            ['compare.introSub', 'Jas atrinkome pagal pasikartojimus tavo atsakymuose, AI siūlymus ir tavo patvirtinimus.', 'Lyginimo įžangos tekstas'],
            ['compare.introCta', 'Pradėti lyginimą', 'Lyginimo pradžios mygtukas'],
            ['compare.introCaption', 'Užtruksite apie 3 minutes', 'Lyginimo trukmės užrašas'],
            ['compare.progress', 'Palyginimas {current} iš {total}', 'Palyginimo progresas'],
            ['compare.title', 'Kuri vertybė tau svarbesnė?', 'Palyginimo klausimas'],
            ['compare.help', 'Rinkis ne tai, kas skamba gražiau, o ko tikrai nenorėtum išduoti.', 'Palyginimo pagalba'],
            ['compare.or', 'Arba', 'Skirtukas tarp kortelių'],
            ['compare.caption', 'Pagalvokite apie savo kasdienius sprendimus', 'Palyginimo užrašas'],
            // Tie-break
            ['tiebreak.chip', 'Lygiosios', 'Lygiųjų ženkliukas'],
            ['tiebreak.title', 'Reikia dar vieno pasirinkimo', 'Lygiųjų antraštė'],
            ['tiebreak.sub', 'Šios dvi vertybės surinko tiek pat taškų. Pasirink, kuri šiuo metu labiau apie tave.', 'Lygiųjų tekstas'],
            ['tiebreak.choose', 'Ši svarbesnė', 'Lygiųjų mygtukas'],
            // Result
            ['result.title', 'Tavo stipriausios vertybės', 'Rezultato antraštė'],
            ['result.sub', 'Šios dvi vertybės šiuo metu stipriausiai veda Tavo sprendimus.', 'Rezultato paantraštė'],
            ['result.rank1', 'Pirma vertybė', 'Rezultato 1 vietos etiketė'],
            ['result.rank2', 'Antra vertybė', 'Rezultato 2 vietos etiketė'],
            ['result.rank3', 'Trečia vertybė', 'Rezultato 3 vietos etiketė (lygiosios)'],
            ['result.rank4', 'Ketvirta vertybė', 'Rezultato 4 vietos etiketė (lygiosios)'],
            ['result.rank5', 'Penkta vertybė', 'Rezultato 5 vietos etiketė (lygiosios)'],
            ['result.rank6', 'Šešta vertybė', 'Rezultato 6 vietos etiketė (lygiosios)'],
            ['result.tensionTitle', 'Galima vidinė įtampa', 'Įtampos bloko antraštė'],
            ['result.meaningTitle', 'Ką tai reiškia', 'Reikšmės bloko antraštė'],
            ['result.cta', 'Rezervuoti pokalbį', 'Rezervacijos mygtukas'],
            ['result.emailTitle', 'Gauti rezultatą el. paštu', 'El. pašto bloko antraštė'],
            ['result.emailSub', 'Jei nori, atsiųsiu šį rezultatą ir trumpą santrauką.', 'El. pašto bloko tekstas'],
            ['result.emailLabel', 'El. paštas', 'El. pašto etiketė'],
            ['result.emailPlaceholder', 'pavyzdys@pastas.lt', 'El. pašto pavyzdys'],
            ['result.emailConsent', 'Sutinku, kad mano el. pašto adresas būtų naudojamas šio rezultato ir susijusios informacijos siuntimui. Bet kada galėsiu atsisakyti.', 'El. pašto sutikimas'],
            ['result.emailSend', 'Siųsti rezultatą', 'El. pašto mygtukas'],
            ['result.errorEmpty', 'El. paštas negali būti tuščias.', 'El. pašto klaida (tuščias)'],
            ['result.errorInvalid', 'Įvesk teisingą el. pašto adresą.', 'El. pašto klaida (neteisingas)'],
            ['result.errorConsent', 'Sutik su sąlygomis, kad galėtum gauti rezultatą.', 'Sutikimo klaida'],
            ['result.privacyLink', 'Privatumo politika', 'Privatumo nuoroda'],
            // Sent
            ['sent.title', 'Rezultatas išsiųstas', 'Išsiuntimo antraštė'],
            ['sent.sub', 'Patikrink savo el. paštą.', 'Išsiuntimo tekstas'],
            ['sent.done', 'Baigti', 'Išsiuntimo mygtukas'],
            ['sent.caption', 'Jei negavai — patikrink spam aplanką', 'Spam užrašas'],
            // Common
            ['common.back', 'Atgal', 'Bendras mygtukas'],
            ['common.continue', 'Tęsti', 'Bendras mygtukas'],
            ['common.errorGeneric', 'Įvyko klaida. Bandyk dar kartą.', 'Bendra klaida'],
        ];
        foreach ($texts as [$key, $value, $context]) {
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), context = VALUES(context)",
                [$key, $value, $context]
            );
        }
    }

    public function down($db) {
        // Content migration — no rollback.
    }
}
