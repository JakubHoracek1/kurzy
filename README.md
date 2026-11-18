# Kurzy a přihlašování účastníků

Aplikace pro nabídku kurzů a přihlašování účastníků. Přihláška (`Enrollment`)
propojuje účastníka (`Participant`) s kurzem (`Course`) a má vlastní stav.

Čisté PHP (bez frameworku) + PDO/SQLite, vlastní jednoduchý router a šablony.

## Datový model

- **Course** – `name`, `code` (jedinečný), `capacity`, `starts_at`
- **Participant** – `name`, `email` (jedinečný, platný)
- **Enrollment** – `course_id`, `participant_id`, `enrolled_at`, `status`
  (`enrolled` / `cancelled` / `completed`)

## Funkce

- CRUD kurzů a účastníků
- Přihlášení účastníka do kurzu
- Zobrazení obsazenosti kurzu a seznamu přihlášených
- Filtr kurzů podle termínu konání a volných míst
- Změna stavu přihlášky: `enrolled` → `cancelled` / `completed`

## Validační pravidla

- Kód kurzu je jedinečný.
- Kapacita je celé číslo větší než 0.
- E-mail účastníka je platný a jedinečný.
- Účastník se nesmí přihlásit dvakrát (nesmí mít dvě aktivní přihlášky do
  stejného kurzu) a počet aktivních přihlášek nesmí překročit kapacitu kurzu.

Validace běží v repository vrstvě (`src/Repositories/*`) a vyhazuje
`App\Validation\ValidationException` se seznamem chyb, které se zobrazí ve
formuláři. Přihlašování navíc běží v DB transakci (`BEGIN IMMEDIATE`), takže
ani souběžné požadavky nemohou kapacitu překročit. Jedna aktivní přihláška na
dvojici kurz/účastník je navíc vynucena i částečným unikátním indexem v
databázi (`database/schema.sql`).

## Požadavky

- PHP 8.1+ s rozšířeními `pdo_sqlite` a `sqlite3` (součást standardní PHP
  distribuce pro Windows/Linux/macOS)
- Žádný Composer ani další závislosti nejsou potřeba

## Spuštění aplikace

```bash
php -S localhost:8000 -t public public/index.php
```

Otevřít v prohlížeči: <http://localhost:8000>

Databáze (`database/kurzy.sqlite`) se automaticky vytvoří a naplní
schématem (`database/schema.sql`) při prvním požadavku.

## Spuštění automatických testů

Vlastní jednoduchý testovací runner (bez PHPUnit/Composeru), testy běží nad
izolovanou SQLite databází v paměti:

```bash
php tests/run.php
```

Testy obsahují mimo jiné **povinnou kontrolu ze zadání** – vytvoření kurzu a
ověření jeho uložení (`tests/CourseTest.php`) – a dále testy validačních
pravidel, hlídání kapacity, zákazu duplicitního přihlášení a všech tří stavů
přihlášky (`enrolled`, `cancelled`, `completed`).

## Struktura projektu

```
public/             front controller (public/index.php) + statické soubory
src/
  Models/           Course, Participant, Enrollment
  Repositories/      CRUD + validace + logika přihlašování a kapacity
  Controllers/       HTTP vrstva (routy -> repository -> šablona)
  Validation/        ValidationException
  Database.php        PDO/SQLite připojení + migrace
  View.php            jednoduchý renderer šablon
  bootstrap.php        autoloader + společné helpery
views/               HTML šablony (layout + courses/ + participants/)
database/
  schema.sql          definice databázového schématu
  kurzy.sqlite         (vzniká automaticky, není součástí repozitáře)
tests/
  run.php              vlastní testovací runner
  CourseTest.php        vč. povinné kontroly "vytvoř kurz a ověř uložení"
  ParticipantTest.php
  EnrollmentTest.php
```
