-- Datový model: Course, Participant, Enrollment
-- SQLite schema
-- Tento soubor definuje strukturu celé databáze. Spouští ho automaticky
-- App\Database::migrate() při prvním připojení (viz src/Database.php).

CREATE TABLE IF NOT EXISTS courses (
    -- Tabulka "courses" = kurzy. "IF NOT EXISTS" zajistí, že se skript
    -- dá bezpečně spustit opakovaně, aniž by to způsobilo chybu.
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Jedinečné ID kurzu, automaticky generované databází při vložení.
    name       TEXT    NOT NULL,
    -- Název kurzu - povinné pole ze zadání (nesmí být NULL).
    code       TEXT    NOT NULL,
    -- Kód kurzu - povinné pole ze zadání (nesmí být NULL); jedinečnost
    -- řeší samostatný index o pár řádků níž.
    capacity   INTEGER NOT NULL CHECK (capacity > 0),
    -- Kapacita kurzu - povinné pole ze zadání; CHECK (capacity > 0) je
    -- validace přímo na úrovni databáze - POVINNÉ VALIDAČNÍ PRAVIDLO
    -- ZE ZADÁNÍ "Kapacita je celé číslo větší než 0" (navíc se to ověřuje
    -- i v PHP kódu v CourseRepository, pro lepší chybové hlášky).
    starts_at  TEXT    NOT NULL
    -- Termín konání kurzu - povinné pole ze zadání, uložené jako text
    -- (SQLite nemá vlastní typ pro datum/čas).
);

-- Kód kurzu musí být jedinečný
CREATE UNIQUE INDEX IF NOT EXISTS idx_courses_code_unique ON courses(code);
-- Unikátní index na sloupec "code" - databáze sama nedovolí vložit dva
-- kurzy se stejným kódem. POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ
-- "Kód kurzu je jedinečný" - vynucené na úrovni databáze jako poslední
-- pojistka (hlavní kontrola běží už v PHP, viz CourseRepository::validate()).

CREATE TABLE IF NOT EXISTS participants (
    -- Tabulka "participants" = účastníci.
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Jedinečné ID účastníka.
    name  TEXT NOT NULL,
    -- Jméno účastníka - povinné pole ze zadání.
    email TEXT NOT NULL
    -- E-mail účastníka - povinné pole ze zadání; jedinečnost a platnost
    -- řeší index níž (jedinečnost) a PHP validace (platný formát).
);

-- E-mail účastníka musí být jedinečný
CREATE UNIQUE INDEX IF NOT EXISTS idx_participants_email_unique ON participants(email);
-- Unikátní index na "email" - POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ
-- "E-mail účastníka je ... jedinečný", vynucené databází jako pojistka.

CREATE TABLE IF NOT EXISTS enrollments (
    -- Tabulka "enrollments" = přihlášky - propojuje kurz a účastníka.
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Jedinečné ID přihlášky.
    course_id      INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    -- Cizí klíč na kurz - povinné pole ze zadání (course_id). REFERENCES
    -- říká, že musí odkazovat na existující kurz; ON DELETE CASCADE
    -- zajistí, že při smazání kurzu se automaticky smažou i jeho přihlášky.
    participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
    -- Cizí klíč na účastníka - povinné pole ze zadání (participant_id);
    -- stejná logika jako výše, jen pro účastníka.
    enrolled_at    TEXT    NOT NULL,
    -- Datum a čas přihlášení - povinné pole ze zadání (enrolled_at).
    status         TEXT    NOT NULL DEFAULT 'enrolled'
                   CHECK (status IN ('enrolled', 'cancelled', 'completed'))
    -- Stav přihlášky - povinné pole ze zadání (status); DEFAULT 'enrolled'
    -- znamená, že nová přihláška má automaticky tento počáteční stav;
    -- CHECK dovolí jen tři přesné hodnoty - DOMÁCÍ PRÁCE ZE ZADÁNÍ
    -- "Přidejte stavy enrolled, cancelled a completed", vynucené i databází.
);

CREATE INDEX IF NOT EXISTS idx_enrollments_course ON enrollments(course_id);
-- Běžný (ne unikátní) index na course_id - zrychlí dotazy typu "najdi
-- všechny přihlášky tohoto kurzu" (používá se často, např. na detailu kurzu).
CREATE INDEX IF NOT EXISTS idx_enrollments_participant ON enrollments(participant_id);
-- Totéž pro participant_id - zrychlí dotazy "najdi přihlášky tohoto účastníka".

-- Účastník se nesmí přihlásit dvakrát: jedna AKTIVNÍ (status = 'enrolled')
-- přihláška na kurz a účastníka (částečný unikátní index). Po zrušení
-- přihlášky (status = 'cancelled') se může přihlásit znovu.
CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollments_unique_active
    ON enrollments(course_id, participant_id)
    WHERE status = 'enrolled';
-- "Částečný" unikátní index (platí jen pro řádky, kde WHERE status =
-- 'enrolled') - zaručuje, že pro jednu dvojici (course_id, participant_id)
-- může existovat NEJVÝŠE JEDNA aktivní přihláška. POVINNÉ VALIDAČNÍ
-- PRAVIDLO ZE ZADÁNÍ "Účastník se nesmí přihlásit dvakrát", vynucené
-- na úrovni databáze jako poslední pojistka (hlavní kontrola běží
-- v PHP, viz EnrollmentRepository::enroll()).
