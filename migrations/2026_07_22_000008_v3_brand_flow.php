<?php
/**
 * Migration: v3 "Vertybės LT" (PERDAVIMAS.md 2026-07-22).
 *
 *  - review screen removed: AI selects 3–5 values silently → comparison → duels
 *  - FINAL 32-value dictionary replaces the 191-catalog as the active AI dict
 *  - session_value_candidates (confidence/mentions/evidence), pair_texts cache
 *  - leads: attribution (lead_source/referral_code, server-verified), marketing
 *    opt-in, consent_version, MailerLite linkage with ml_pending retry
 *  - coaches table for referral verification
 *  - new questions q2–q4, new copy for all 11 screens, full privacy policy
 */

class V3BrandFlowMigration {
    public function up($db) {
        // ── Schema ────────────────────────────────────────────────────────────
        if (!$db->columnExists('leads', 'value_1')) {
            $db->query("ALTER TABLE leads
                ADD COLUMN value_1 VARCHAR(60) NULL AFTER top_values,
                ADD COLUMN value_2 VARCHAR(60) NULL AFTER value_1,
                ADD COLUMN lead_source VARCHAR(60) NULL AFTER value_2,
                ADD COLUMN referral_code VARCHAR(60) NULL AFTER lead_source,
                ADD COLUMN referral_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_code,
                ADD COLUMN marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_verified,
                ADD COLUMN consent_version VARCHAR(20) NULL AFTER consented,
                ADD COLUMN mailerlite_subscriber_id VARCHAR(64) NULL AFTER consent_version,
                ADD COLUMN ml_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER mailerlite_subscriber_id,
                ADD INDEX idx_leads_referral (referral_code),
                ADD INDEX idx_leads_ml_pending (ml_pending)");
        }
        if (!$db->columnExists('test_sessions', 'lead_source')) {
            $db->query("ALTER TABLE test_sessions
                ADD COLUMN lead_source VARCHAR(60) NULL AFTER user_agent,
                ADD COLUMN referral_code VARCHAR(60) NULL AFTER lead_source");
        }
        $db->query("
            CREATE TABLE IF NOT EXISTS coaches (
                code VARCHAR(60) NOT NULL PRIMARY KEY,
                name VARCHAR(120) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->query("INSERT IGNORE INTO coaches (code, name) VALUES ('tomas123', 'Tomas')");

        $db->query("
            CREATE TABLE IF NOT EXISTS session_value_candidates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                value_key VARCHAR(64) NOT NULL,
                label_lt VARCHAR(60) NOT NULL,
                confidence DECIMAL(4,3) NOT NULL DEFAULT 0,
                mentions_json TEXT NULL,
                evidence_json TEXT NULL,
                sort_index TINYINT NOT NULL DEFAULT 0,
                selected TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_session_value (session_id, value_key),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Pair interpretations cache — the same pair always gets the same text
        $db->query("
            CREATE TABLE IF NOT EXISTS pair_texts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pair_key VARCHAR(130) NOT NULL UNIQUE,
                tension_text TEXT NOT NULL,
                meaning_text TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── The FINAL 32-value dictionary (owner-approved 2026-07-22) ─────────
        $dict = [
            'laisve' => 'Laisvė', 'savarankiskumas' => 'Savarankiškumas', 'drasa' => 'Drąsa',
            'nuotykiai' => 'Nuotykiai', 'smalsumas' => 'Smalsumas', 'augimas' => 'Augimas',
            'meistryste' => 'Meistrystė', 'kuryba' => 'Kūryba', 'pasiekimai' => 'Pasiekimai',
            'pripazinimas' => 'Pripažinimas', 'itaka' => 'Įtaka', 'tiesa' => 'Tiesa',
            'autentiskumas' => 'Autentiškumas', 'teisingumas' => 'Teisingumas',
            'atsakomybe' => 'Atsakomybė', 'pagarba' => 'Pagarba', 'saugumas' => 'Saugumas',
            'ramybe' => 'Ramybė', 'sveikata' => 'Sveikata', 'harmonija' => 'Harmonija',
            'disciplina' => 'Disciplina', 'artumas' => 'Artumas', 'seima' => 'Šeima',
            'bendruomene' => 'Bendruomenė', 'empatija' => 'Empatija', 'dosnumas' => 'Dosnumas',
            'prasme' => 'Prasmė', 'dvasingumas' => 'Dvasingumas', 'tradicijos' => 'Tradicijos',
            'gamta' => 'Gamta', 'grozis' => 'Grožis', 'zaismingumas' => 'Žaismingumas',
        ];
        $db->query("UPDATE values_catalog SET is_active = 0");
        $order = 1;
        foreach ($dict as $key => $label) {
            $db->query(
                "INSERT INTO values_catalog (value_key, label_lt, meaning_lt, is_active, is_core, is_custom, sort_order)
                 VALUES (?, ?, '', 1, 0, 0, ?)
                 ON DUPLICATE KEY UPDATE label_lt = VALUES(label_lt), is_active = 1,
                                         is_custom = 0, sort_order = VALUES(sort_order)",
                [$key, $label, $order++]
            );
        }

        // ── Questions (final LT copy, PERDAVIMAS.md) ──────────────────────────
        $instruction = 'Svarbiausios užuominos apie tave atsiras po kelių atsakymų.';
        $questions = [
            ['q1', 'Ką labiausiai mėgsti veikti laisvalaikiu?',
             ['Pvz. Kelionės', 'Pvz. Bėgimas', 'Pvz. Laikas su draugais', 'Pvz. Skaitymas', 'Pvz. Gaminti valgyti', 'Pvz. Žvejyba']],
            ['q2', 'Kas tave labiausiai suerzina ar nuvilia?',
             ['Pvz. Melas', 'Pvz. Vėlavimas', 'Pvz. Abejingumas', 'Pvz. Netesėti pažadai', 'Pvz. Spaudimas', 'Pvz. Neteisybė']],
            ['q3', 'Kuo savo gyvenime labiausiai didžiuojiesi?',
             ['Pvz. Šeima', 'Pvz. Užbaigti darbai', 'Pvz. Savo namai', 'Pvz. Išmoktas amatas', 'Pvz. Draugystės', 'Pvz. Sveikata']],
            ['q4', 'Be ko tavo gyvenimas prarastų prasmę?',
             ['Pvz. Artimieji', 'Pvz. Laisvė', 'Pvz. Kūryba', 'Pvz. Gamta', 'Pvz. Tikslai', 'Pvz. Ramybė']],
        ];
        foreach ($questions as [$key, $text, $placeholders]) {
            $db->query(
                "UPDATE questions SET text = ?, hint = ?, placeholders_json = ? WHERE question_key = ?",
                [$text, $instruction, json_encode($placeholders, JSON_UNESCAPED_UNICODE), $key]
            );
        }

        // ── Settings ──────────────────────────────────────────────────────────
        $force = [
            'site_name' => 'Vertybės LT',
            'consent_version' => 'v1',
            'vision_url' => 'https://vision.lt',
            'vision_session_url' => 'https://vision.lt/kaip-vyksta-koucingo-sesija',
            'facebook_url' => 'https://www.facebook.com/WhatIfMore/',
            'ga4_id' => 'G-GGS59F2SHD',
            'meta_pixel_id' => '1012985031473778',
            'clarity_id' => 'xq3b5kvo6i',
            'min_answers_per_question' => '2',
        ];
        foreach ($force as $k => $v) {
            $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$k, $v]);
        }
        foreach (['mailerlite_token' => '', 'ml_group_test' => '', 'ml_group_marketing' => ''] as $k => $v) {
            $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE setting_key = setting_key", [$k, $v]);
        }

        // ── UI texts: retire v2 keys, seed v3 copy (prototype = copy source) ──
        $db->query("DELETE FROM ui_texts WHERE text_key REGEXP
            '^(intro|steps|consent|policy|cookies|questions|loading|review|picker|compare|tiebreak|result|sent)\\\\.'");

        $texts = [
            ['brand.name', 'Vertybės LT', 'Viršutinės juostos ženklas'],
            // 1 · Intro
            ['intro.hero', 'Kas iš tikrųjų lemia tavo sprendimus?', 'Pradžios antraštė'],
            ['intro.sub', 'Šis testas padės pamatyti, kas tave veda: mažiau vidinės sumaišties, daugiau aiškumo priimant sprendimus.', 'Pradžios paantraštė'],
            ['intro.bullet1', '~3 minutės', 'Pradžios punktas 1'],
            ['intro.bullet2', 'Be registracijos', 'Pradžios punktas 2'],
            ['intro.bullet3', 'Tik 4 klausimai. Atsakyti gali ir vienu žodžiu.', 'Pradžios punktas 3'],
            ['intro.bullet4', 'Rezultatas iš karto', 'Pradžios punktas 4'],
            ['intro.cta', 'Atrasti savo vertybes', 'Pradžios mygtukas'],
            // Cookie sheet (2 layers)
            ['cookies.title', 'Slapukai', 'Slapukų lango antraštė'],
            ['cookies.body', 'Atėjai sužinoti savo vertybių. Būtini slapukai saugo tavo atsakymus, kad pradėtas testas nedingtų. Kai leisi visus, testas taps dar sklandesnis, o statistika liks be asmens duomenų.', 'Slapukų tekstas'],
            ['cookies.necessary.title', 'Būtini', 'Būtinų slapukų pavadinimas'],
            ['cookies.necessary.desc', 'Sesija ir atsakymų išsaugojimas.', 'Būtinų slapukų aprašymas'],
            ['cookies.always', 'Visada', 'Būtinų slapukų būsena'],
            ['cookies.stats.title', 'Statistika', 'Statistikos slapukų pavadinimas'],
            ['cookies.stats.desc', 'Kur testas stringa. Be asmens duomenų.', 'Statistikos slapukų aprašymas'],
            ['cookies.acceptAll', 'Leisti visus ir tęsti', 'Slapukų mygtukas'],
            ['cookies.onlyNecessary', 'Tik būtini', 'Slapukų mygtukas'],
            ['cookies.settings', 'Nustatymai', 'Slapukų nustatymų nuoroda'],
            ['cookies.saveChoice', 'Išsaugoti pasirinkimą', 'Slapukų mygtukas'],
            ['cookies.link', 'Slapukų nustatymai', 'Slapukų nuoroda apačioje'],
            // 2 · Prieš pradedant
            ['consent.title', 'Prieš pradedant', 'Sutikimo antraštė'],
            ['consent.aiInfo', 'AI perskaitys tavo atsakymus ir pasiūlys vertybes. Kurios iš tikrųjų tavo, nuspręsi tu.', 'AI kortelė'],
            ['consent.g1', 'Rašyk pirmą mintį. Ji tikriausia.', 'Patarimas 1'],
            ['consent.g2', 'Visai nebūtina rašyti sakiniais. Užtenka žodžio.', 'Patarimas 2'],
            ['consent.g3', 'Kiekvienas atsakymas atskleidžia dar vieną tavo pusę', 'Patarimas 3'],
            ['consent.g4', 'Neskubėk. Šios minutės dirba tau.', 'Patarimas 4'],
            ['consent.linkIntro', 'Tavo atsakymai asmeniški, todėl klausiame. Prieš tęsiant susipažink su', 'Eilutė prieš nuorodą'],
            ['consent.linkText', 'Privatumo politika ir AI naudojimo informacija', 'Nuorodos tekstas'],
            ['consent.checkbox', 'Susipažinau su Privatumo politika ir AI naudojimo informacija.', 'Sutikimo varnelė'],
            ['consent.error', 'Prašome patvirtinti, kad susipažinote su Privatumo politika ir AI naudojimo informacija.', 'Sutikimo klaida'],
            // 3 · Klausimai
            ['questions.progress', 'Klausimas {current} iš {total}', 'Klausimo progresas'],
            ['questions.savedFlash', 'Atsakymas išsaugotas', 'Išsaugojimo blyksnis'],
            ['questions.savedAll', 'Atsakymai išsaugoti.', 'Visų atsakymų patvirtinimas'],
            ['questions.autosave', 'Atsakymai išsaugomi automatiškai.', 'Autosave užrašas'],
            ['questions.kasdar', 'Kas dar?', '3-io laukelio pavyzdys'],
            ['questions.needMore', 'AI rado per mažai skirtingų vertybių. Papildyk atsakymus keliais pavyzdžiais.', 'Per mažai vertybių'],
            ['coach.0', '💡|Kuo daugiau pavyzdžių, tuo aiškesnis rezultatas.', 'Užuomina (0 atsakymų)'],
            ['coach.1', '🌱|Puiki pradžia. Su kiekvienu atsakymu AI pažįsta tave geriau.', 'Užuomina (1)'],
            ['coach.2', '🤖|AI jau pastebi pasikartojančias temas…', 'Užuomina (2)'],
            ['coach.3', '🔎|Ryškėja kryptis. Kai pridėsi dar porą, vaizdas taps tikslus.', 'Užuomina (3)'],
            ['coach.4', '✨|Tavo rezultatas ką tik tapo gerokai tikslesnis.', 'Užuomina (4)'],
            ['coach.5', '🎯|AI jau mato aiškius tavo dėsningumus.', 'Užuomina (5)'],
            ['coach.6', 'MOUNTAIN|Puiku. AI jau gali parodyti, kas tave veda.', 'Užuomina (6, lieka)'],
            ['coach.helper', 'Kiekvienas atsakymas – dar viena užuomina apie tai, kas tau svarbu.', 'Pilka pagalbos eilutė'],
            ['gate.title', 'Pirmas atsakymas jau įrašytas', 'Min-2 modalo antraštė'],
            ['gate.b1', 'Kol kas AI mato tik vieną tavo pusę.', 'Min-2 modalo tekstas 1'],
            ['gate.b2', 'Kai pridėsi dar 2–3 atsakymus, rezultatas taps gerokai tikslesnis.', 'Min-2 modalo tekstas 2'],
            ['gate.b3', 'Tai užtruks mažiau nei minutę.', 'Min-2 modalo tekstas 3'],
            ['gate.cta', 'Pridėsiu daugiau', 'Min-2 modalo mygtukas'],
            // 4 · AI analizė
            ['analysis.title', 'Analizuojame tavo atsakymus…', 'Analizės antraštė'],
            ['analysis.step1', 'Ieškome pasikartojančių temų', 'Analizės žingsnis 1'],
            ['analysis.step2', 'Ryškėja svarbiausios vertybės', 'Analizės žingsnis 2'],
            ['analysis.step3', 'Vertiname galimą vidinę įtampą…', 'Analizės žingsnis 3'],
            ['analysis.saved', 'Atsakymai išsaugoti.', 'Analizės patvirtinimas'],
            ['analysis.edit', 'Redaguoti atsakymus', 'Analizės redagavimo nuoroda'],
            ['analysis.seed', 'Tai, kas tau svarbu, jau ryškėja. Įdomiausia dažnai slypi giliau.', 'Analizės sėklos eilutė'],
            ['analysis.failed', 'Nepavyko užbaigti analizės. Tavo atsakymai išsaugoti — bandyk dar kartą.', 'Analizės klaida'],
            ['analysis.retry', 'Bandyti dar kartą', 'Analizės kartojimo mygtukas'],
            // 5 · Palyginimas
            ['comparison.title', 'Svarbiausios vertybės', 'Palyginimo antraštė'],
            ['comparison.sub', 'Šios temos tavo atsakymuose kartojosi dažniausiai.', 'Palyginimo paantraštė'],
            ['comparison.analyzing', 'Analizuojame...', 'Palyginimo statusas'],
            ['comparison.next', 'Beliko suprasti, kurios iš jų tau svarbiausios.', 'Eilutė prieš mygtuką'],
            ['comparison.cta', 'Palyginti vertybes', 'Palyginimo mygtukas'],
            ['comparison.caption', '~ užtruks apie minutę', 'Trukmės užrašas'],
            ['comparison.restart', 'Kažkas ne taip? Pradėk iš naujo', 'Perkrovimo nuoroda'],
            // 6 · Duelis
            ['duel.title', 'Jei šiandien galėtum pasirinkti tik vieną…', 'Duelio antraštė'],
            ['duel.caption', 'Ilgai negalvok.', 'Duelio užrašas'],
            ['duel.last', 'Štai ir viskas.', 'Paskutinio duelio kadras'],
            // 7 · Lygiosios
            ['tiebreak.chip', 'Lygiosios', 'Lygiųjų ženkliukas'],
            ['tiebreak.title', 'Paskutinis žodis tavo', 'Lygiųjų antraštė'],
            ['tiebreak.sub', 'Šios dvi surinko po lygiai. Vienas pasirinkimas viską išsprendžia.', 'Lygiųjų tekstas'],
            ['tiebreak.caption', 'Pasirink tą, kuri dabar artimesnė.', 'Lygiųjų užrašas'],
            // 8 · Rezultatas
            ['result.chip', 'Testas baigtas', 'Rezultato ženkliukas'],
            ['result.title', 'Štai kas tave veda', 'Rezultato antraštė'],
            ['result.rank1', 'Tavo Nr. 1', 'Nr. 1 etiketė'],
            ['result.rank2', 'Kita stipri vertybė', 'Nr. 2 etiketė'],
            ['result.meaningTitle', 'Ką tai reiškia', 'Reikšmės blokas'],
            ['result.tensionTitle', 'Galima vidinė įtampa', 'Įtampos blokas'],
            ['result.nextCta', 'Kitas žingsnis', 'Rezultato mygtukas'],
            ['result.nextCaption', 'Vertybės yra tik pradžia.', 'Rezultato užrašas'],
            // 9 · Kitas žingsnis
            ['next.hero', 'Vertybės yra tik pradžia.', 'Kito žingsnio antraštė'],
            ['next.heroSub', 'Sprendimus lemia ne tik vertybės.', 'Kito žingsnio paantraštė'],
            ['next.gapsTitle', 'Ko šis testas dar neparodo', 'Spragos: antraštė'],
            ['next.gap1', 'Kodėl kartojasi tie patys sprendimų modeliai', 'Spraga 1'],
            ['next.gap2', 'Kas stabdo pokyčius', 'Spraga 2'],
            ['next.gap3', 'Kaip vertybės susiduria realiose situacijose', 'Spraga 3'],
            ['next.gap4', 'Kaip priimti sprendimus, kai vertybės susiduria', 'Spraga 4'],
            ['next.methodTitle', 'Kaip VISION metodas padeda', 'Metodo antraštė'],
            ['next.methodBody', 'VISION metodas padeda ne tik suprasti save, bet ir veikti pagal tai, ką supratai.', 'Metodo tekstas'],
            ['next.heroBig', 'Nuo „žinau savo vertybes“ iki „suprantu save geriau“.', 'Didysis hero'],
            ['next.heroBigSub', 'VISION metodas padeda paversti įžvalgas sprendimais.', 'Didžiojo hero paantraštė'],
            ['next.visionCta', 'Pamatyti VISION metodą', 'VISION mygtukas'],
            ['next.emailTitle', 'Išsaugok rezultatą', 'El. pašto antraštė'],
            ['next.emailPlaceholder', 'Tavo el. paštas', 'El. pašto laukas'],
            ['next.emailCta', 'Gauti rezultatą', 'El. pašto mygtukas'],
            ['next.consentRequired', 'Sutinku, kad mano el. pašto adresas būtų naudojamas testo rezultatui atsiųsti.', 'Privalomas sutikimas'],
            ['next.consentMarketing', 'Noriu gauti ir Vision LT įžvalgas el. paštu. (pasirinktinai)', 'Marketingo sutikimas'],
            ['next.deeper', 'Kai norėsi pažvelgti giliau:', 'Gilyn eilutė'],
            ['next.sessionLink', 'Kaip vyksta sesija →', 'Sesijos nuoroda'],
            ['next.moreInsights', 'Nori daugiau panašių įžvalgų?', 'Įžvalgų eilutė'],
            ['next.followFb', 'Sekti Vision LT Facebook →', 'Facebook nuoroda'],
            ['next.footer', 'Prie šio rezultato verta sugrįžti.', 'Poraštės eilutė'],
            ['next.tagline', 'See what matters.', 'Šūkis'],
            // 10 · Išsiųsta
            ['sent.title', 'Rezultatas išsiųstas.', 'Išsiuntimo antraštė'],
            ['sent.to', 'Išsiuntėme į {email}', 'Kur išsiųsta'],
            ['sent.toFallback', 'Išsiuntėme į tavo el. paštą', 'Kur išsiųsta (be adreso)'],
            ['sent.thanks', 'Ačiū, kad skyrei laiko sau.', 'Padėka'],
            ['sent.follow', 'Naujos įžvalgos →', 'Sekimo antraštė'],
            ['sent.followLink', 'Sek Vision LT Facebook', 'Sekimo nuoroda'],
            ['sent.spam', 'Nerandi laiško? Patikrink „Spam“.', 'Spam užrašas'],
            ['sent.again', 'Atlikti testą dar kartą', 'Kartojimo mygtukas'],
            // Errors
            ['error.email', 'Įvesk teisingą el. pašto adresą.', 'El. pašto klaida'],
            ['error.consent', 'Sutik su sąlygomis, kad galėtum gauti rezultatą.', 'Sutikimo klaida'],
            // 11 · Privatumas
            ['policy.title', 'Privatumo politika', 'Politikos antraštė'],
            ['policy.updated', 'Paskutinį kartą atnaujinta: 2026-07-22', 'Atnaujinimo data'],
            ['policy.back', 'Grįžti į testą', 'Grįžimo nuoroda'],
            ['policy.ok', 'Supratau', 'Politikos mygtukas'],
            ['policy.footer', 'Boeder Equipments Limited · Atnaujinta 2026-07-22', 'Politikos poraštė'],
            ['policy.full', self::policyText(), 'Pilna privatumo politika (10 skyrių)'],
        ];
        foreach ($texts as [$key, $value, $context]) {
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), context = VALUES(context)",
                [$key, $value, $context]
            );
        }
    }

    private static function policyText() {
        return "1. Kas esame\nŠi privatumo politika taikoma Vertybės LT testui, pasiekiamam adresu vertybes.lt.\nDuomenų valdytojas: Boeder Equipments Limited, 4th Floor East, 35–37 Ludgate Hill, London EC4M 7JN, Jungtinė Karalystė.\nEl. paštas: tomas@vertybes.lt\n\n2. Kokius duomenis renkame\nJeigu naudojiesi testu, galime tvarkyti:\n• tavo pateiktus atsakymus į testo klausimus;\n• pasirinktų vertybių informaciją;\n• el. pašto adresą (jeigu nusprendi gauti rezultatus arba naujienas);\n• techninius duomenis (IP adresą, naršyklės informaciją, įrenginio tipą, slapukus), jeigu jie reikalingi svetainės veikimui ar analitikai.\n\n3. Kam naudojame duomenis\nTavo duomenis naudojame:\n• apskaičiuoti testo rezultatą;\n• sugeneruoti individualias AI įžvalgas;\n• išsiųsti testo rezultatą el. paštu;\n• siųsti informaciją apie Vertybės LT, jei tam davei atskirą sutikimą;\n• gerinti paslaugos kokybę ir užtikrinti sistemos saugumą.\n\n4. AI naudojimas\nVertybės LT naudoja dirbtinį intelektą tam, kad analizuotų tavo atsakymus ir padėtų suformuoti personalizuotas įžvalgas.\nAI nepaskiria tavo vertybių savarankiškai. Galutinis rezultatas apskaičiuojamas pagal tavo atsakymus ir pasirinktus sprendimus testo metu.\nAnalizei naudojame OpenAI API. Duomenys perduodami tik tiek, kiek būtina analizei atlikti. Naudojant OpenAI API, perduoti duomenys pagal numatytuosius API nustatymus nėra naudojami OpenAI modelių mokymui.\n\n5. Kam perduodame duomenis\nTam tikrais atvejais naudojamės patikimais paslaugų teikėjais. Šiuo metu naudojame:\n• MailerLite – el. laiškų siuntimui;\n• OpenAI Ireland Ltd. / OpenAI – AI analizei;\n• kitus techninius paslaugų teikėjus, reikalingus svetainės veikimui.\nŠie paslaugų teikėjai tvarko duomenis tik mūsų vardu ir tik tiek, kiek būtina paslaugoms suteikti.\n\n6. Kiek laiko saugome duomenis\nTesto atsakymus saugome ne ilgiau nei reikia rezultatui apskaičiuoti ir paslaugai teikti.\nJeigu palikai savo el. paštą rezultatams ar naujienoms gauti, jį saugome tol, kol:\n• atsisakai prenumeratos;\n• paprašai ištrinti duomenis;\n• arba to reikalauja teisės aktai.\nTokiu atveju asmeninius duomenis nuasmeniname, o nuasmenintus galime toliau saugoti statistikai ir paslaugos kokybei gerinti.\n\n7. Tarptautinis duomenų perdavimas\nKai kurie mūsų paslaugų teikėjai gali tvarkyti duomenis už Europos ekonominės erdvės ribų. Tokiais atvejais taikomos Europos Sąjungos teisės aktuose numatytos apsaugos priemonės, įskaitant standartines sutarčių sąlygas arba kitus teisėtus duomenų perdavimo mechanizmus.\n\n8. Tavo teisės\nPagal BDAR (GDPR) turi teisę:\n• susipažinti su savo duomenimis;\n• ištaisyti netikslius duomenis;\n• prašyti juos ištrinti;\n• apriboti jų tvarkymą;\n• nesutikti su duomenų tvarkymu;\n• atšaukti anksčiau duotą sutikimą;\n• pateikti skundą Valstybinei duomenų apsaugos inspekcijai.\nNorėdamas pasinaudoti šiomis teisėmis, parašyk: tomas@vertybes.lt\n\n9. Slapukai\nSvetainėje gali būti naudojami būtini slapukai bei analitiniai slapukai. Jeigu naudojame Google Analytics ar Meta Pixel, jie aktyvuojami tik gavus tavo sutikimą pagal slapukų pasirinkimus.\n\n10. Politikos pakeitimai\nPrivatumo politika gali būti atnaujinama. Naujausia versija visada skelbiama šiame puslapyje.";
    }

    public function down($db) {
        // Content/schema migration — no rollback.
    }
}
