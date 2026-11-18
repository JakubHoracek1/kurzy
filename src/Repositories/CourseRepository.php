<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Repositories;
// Namespace App\Repositories - soubor src/Repositories/CourseRepository.php.

use App\Models\Course;
// Import třídy Course, abychom ji mohli používat bez plného jména App\Models\Course.
use App\Validation\ValidationException;
// Import vlastní výjimky pro validační chyby.
use PDO;
// Import vestavěné třídy PDO (databázové připojení).

/**
 * CRUD a validace pro Course.
 *
 * Validační pravidla ze zadání:
 *  - Kód kurzu je jedinečný.
 *  - Kapacita je celé číslo větší než 0.
 */
final class CourseRepository
{
    // "Repository" = třída, která se stará o veškerou práci s databází
    // pro jeden konkrétní datový typ (tady kurzy) - CRUD + validace.

    public function __construct(private readonly PDO $pdo)
    {
        // Konstruktor přijme databázové připojení a uloží ho (property
        // promotion) do soukromé, neměnné vlastnosti $this->pdo, kterou
        // pak používají všechny metody této třídy.
    }

    /**
     * Vrátí kurzy včetně počtu aktivních přihlášek a volné kapacity,
     * volitelně filtrované podle termínu konání a volných míst.
     *
     * $filters:
     *   from      - string|null (datum od, formát Y-m-d\TH:i nebo Y-m-d)
     *   to        - string|null (datum do)
     *   only_free - bool (zobrazit jen kurzy s volným místem)
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        // Metoda pro výpis (READ) všech kurzů - používá se na stránce
        // se seznamem kurzů, včetně filtrování.
        $sql = "SELECT c.*,
                    (SELECT COUNT(*) FROM enrollments e
                        WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_count
                FROM courses c
                WHERE 1 = 1";
        // Základní SQL dotaz: vybere všechny sloupce kurzu (c.*) a navíc
        // podřazeným (pod)dotazem spočítá, kolik má daný kurz AKTIVNÍCH
        // (status = enrolled) přihlášek - "WHERE 1 = 1" je trik, díky
        // kterému můžeme níž jednoduše přidávat "AND ..." podmínky.
        $params = [];
        // Pole parametrů pro tzv. prepared statement (ochrana proti SQL
        // injection - hodnoty se nevkládají přímo do SQL textu).

        if (!empty($filters['from'])) {
            // Pokud byl zadán filtr "od" (datum).
            $sql .= ' AND c.starts_at >= :from';
            // Přidá do SQL podmínku: termín konání musí být od tohoto data.
            $params['from'] = $filters['from'];
            // Uloží hodnotu filtru do parametrů pro bezpečné dosazení.
        }
        if (!empty($filters['to'])) {
            // Pokud byl zadán filtr "do" (datum).
            $sql .= ' AND c.starts_at <= :to';
            // Přidá podmínku: termín konání nejpozději do tohoto data.
            $params['to'] = $filters['to'];
        }

        $sql .= ' ORDER BY c.starts_at ASC, c.id ASC';
        // Seřadí výsledky podle termínu konání (nejbližší první),
        // při shodě podle ID (stabilní pořadí).

        $stmt = $this->pdo->prepare($sql);
        // Připraví SQL dotaz k provedení (prepared statement).
        $stmt->execute($params);
        // Spustí dotaz s danými parametry (bezpečně dosadí hodnoty).
        $rows = $stmt->fetchAll();
        // Načte všechny vrácené řádky jako pole asociativních polí.

        foreach ($rows as &$row) {
            // Projde všechny řádky; "&$row" = reference, takže úpravy
            // uvnitř cyklu se promítnou přímo do $rows.
            $row['enrolled_count'] = (int) $row['enrolled_count'];
            // Přetypuje počet přihlášených na celé číslo (z DB může
            // přijít jako string).
            $row['capacity'] = (int) $row['capacity'];
            // Přetypuje kapacitu na celé číslo.
            $row['free_count'] = max(0, $row['capacity'] - $row['enrolled_count']);
            // Dopočítá volná místa = kapacita minus obsazenost, nikdy
            // méně než 0 (pro jistotu, kdyby něco nesedělo).
        }
        unset($row);
        // Zruší referenční proměnnou $row po cyklu - dobrá praxe v PHP,
        // aby se "&$row" neprojevilo omylem v dalším kódu.

        if (!empty($filters['only_free'])) {
            // Pokud je zapnutý filtr "jen kurzy s volným místem".
            $rows = array_values(array_filter($rows, static fn (array $r) => $r['free_count'] > 0));
            // array_filter ponechá jen řádky s volným místem (free_count > 0);
            // array_values znovu přeindexuje pole od 0 (array_filter
            // zachovává původní klíče, což by míchalo číslování).
        }

        return $rows;
        // Vrátí finální (případně odfiltrovaný) seznam kurzů.
    }

    /**
     * Najde kurz podle ID včetně obsazenosti. Vrátí null, pokud neexistuje.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        // Metoda pro načtení JEDNOHO kurzu podle ID - používá se na
        // detailu kurzu, v editačním formuláři i při validaci.
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                (SELECT COUNT(*) FROM enrollments e
                    WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_count
             FROM courses c WHERE c.id = :id"
        );
        // Stejný princip jako v all(), jen omezeno na jeden konkrétní kurz.
        $stmt->execute(['id' => $id]);
        // Spustí dotaz s daným ID.
        $row = $stmt->fetch();
        // Načte jeden řádek (nebo false, pokud nic nenašel).
        if ($row === false) {
            // Kurz s tímto ID neexistuje.
            return null;
            // Vrátí null namísto false - jasnější signál "nic nenalezeno".
        }

        $row['enrolled_count'] = (int) $row['enrolled_count'];
        // Přetypuje na int (stejně jako v all()).
        $row['capacity'] = (int) $row['capacity'];
        // Přetypuje kapacitu na int.
        $row['free_count'] = max(0, $row['capacity'] - $row['enrolled_count']);
        // Dopočítá volná místa.

        return $row;
        // Vrátí nalezený (a doplněný) řádek.
    }

    /**
     * Vytvoří nový kurz. Validuje vstup a vyhodí ValidationException při chybě.
     *
     * @param array{name?: string, code?: string, capacity?: mixed, starts_at?: string} $data
     */
    public function create(array $data): Course
    {
        // Metoda pro vytvoření (CREATE) nového kurzu - $data obvykle
        // přichází přímo z formuláře (z $_POST).
        $clean = $this->validate($data);
        // Nejprve ověří všechna validační pravidla; pokud je vstup
        // neplatný, validate() vyhodí ValidationException a dál se
        // v této metodě nepokračuje.

        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (name, code, capacity, starts_at) VALUES (:name, :code, :capacity, :starts_at)'
        );
        // Připraví INSERT dotaz s placeholdery pro bezpečné dosazení hodnot.
        $stmt->execute($clean);
        // Spustí dotaz s očištěnými (validovanými) daty - klíče v $clean
        // odpovídají placeholderům :name, :code, :capacity, :starts_at.
        $id = (int) $this->pdo->lastInsertId();
        // Zjistí ID nově vloženého řádku (AUTOINCREMENT z databáze).

        return new Course($id, $clean['name'], $clean['code'], (int) $clean['capacity'], $clean['starts_at']);
        // Vrátí objekt Course reprezentující nově vytvořený kurz.
    }

    /**
     * Upraví existující kurz. Validuje vstup a vyhodí ValidationException při chybě.
     */
    public function update(int $id, array $data): Course
    {
        // Metoda pro úpravu (UPDATE) existujícího kurzu podle ID.
        $clean = $this->validate($data, excludeId: $id);
        // Validuje data stejně jako při vytvoření, ale s výjimkou:
        // kontrola jedinečnosti kódu musí ignorovat tento samotný kurz
        // (jinak by si "koliduje se sebou samým").

        $stmt = $this->pdo->prepare(
            'UPDATE courses SET name = :name, code = :code, capacity = :capacity, starts_at = :starts_at WHERE id = :id'
        );
        // Připraví UPDATE dotaz, který přepíše všechny sloupce daného kurzu.
        $stmt->execute($clean + ['id' => $id]);
        // Spustí dotaz; operátor "+" sloučí pole $clean (name, code,
        // capacity, starts_at) s polem obsahujícím 'id' => $id.

        return new Course($id, $clean['name'], $clean['code'], (int) $clean['capacity'], $clean['starts_at']);
        // Vrátí objekt Course s aktualizovanými hodnotami.
    }

    public function delete(int $id): void
    {
        // Metoda pro smazání (DELETE) kurzu podle ID.
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE id = :id');
        // Připraví DELETE dotaz. Díky "ON DELETE CASCADE" ve schema.sql
        // se automaticky smažou i všechny přihlášky navázané na tento kurz.
        $stmt->execute(['id' => $id]);
        // Spustí smazání.
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        // Pomocná metoda - ověří, zda už v databázi existuje kurz
        // s daným kódem (pro validaci jedinečnosti kódu kurzu).
        $sql = 'SELECT id FROM courses WHERE code = :code';
        // Základní dotaz - najít ID kurzu podle kódu.
        $params = ['code' => $code];
        // Parametr pro bezpečné dosazení kódu do dotazu.
        if ($excludeId !== null) {
            // Pokud máme vyloučit konkrétní ID (typicky při úpravě kurzu -
            // nechceme, aby kurz "koliduje" se svým vlastním kódem).
            $sql .= ' AND id != :id';
            // Přidá podmínku "a zároveň to není tento kurz".
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        // Připraví dotaz.
        $stmt->execute($params);
        // Spustí dotaz s parametry.

        return $stmt->fetch() !== false;
        // Pokud fetch() vrátil řádek (ne false), kód už existuje -> true.
    }

    /**
     * Ověří validační pravidla a vrátí "očištěná" data připravená pro uložení.
     *
     * @return array{name: string, code: string, capacity: int, starts_at: string}
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        // Soukromá metoda - validace je interní detail této třídy,
        // zvenčí se volá jen create()/update(), které validate() použijí.
        $errors = [];
        // Pole, do kterého se postupně sbírají všechny nalezené chyby
        // (nekončíme na první chybě, aby uživatel viděl rovnou všechny).

        $name = trim((string) ($data['name'] ?? ''));
        // Vezme hodnotu 'name' ze vstupu (nebo prázdný string, pokud
        // chybí), přetypuje na string a odstraní mezery na začátku/konci.
        if ($name === '') {
            // Pokud po trim() zůstal prázdný string.
            $errors[] = 'Název kurzu je povinný.';
            // Přidá chybovou hlášku do pole chyb.
        }

        $code = trim((string) ($data['code'] ?? ''));
        // Totéž pro kód kurzu.
        if ($code === '') {
            $errors[] = 'Kód kurzu je povinný.';
        } elseif ($this->codeExists($code, $excludeId)) {
            // Jinak (kód není prázdný) zkontroluje, zda už neexistuje
            // v databázi - to je POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ
            // "Kód kurzu je jedinečný."
            $errors[] = 'Kód kurzu musí být jedinečný - tento kód už existuje.';
        }

        $capacityRaw = $data['capacity'] ?? null;
        // Vezme syrovou (ještě nezpracovanou) hodnotu kapacity ze vstupu.
        $capacity = null;
        // Výchozí hodnota, pokud se validace nepovede - zůstane null.
        if ($capacityRaw === null || $capacityRaw === '' ||
            filter_var($capacityRaw, FILTER_VALIDATE_INT) === false) {
            // Pokud kapacita chybí, je prázdná NEBO není platné celé číslo
            // (filter_var s FILTER_VALIDATE_INT vrátí false pro "abc" apod.).
            $errors[] = 'Kapacita musí být celé číslo větší než 0.';
            // POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ:
            // "Kapacita je celé číslo větší než 0."
        } else {
            // Jinak (je to platné celé číslo).
            $capacity = (int) $capacityRaw;
            // Přetypuje na int pro další použití.
            if ($capacity <= 0) {
                // I platné celé číslo musí být kladné (> 0).
                $errors[] = 'Kapacita musí být celé číslo větší než 0.';
            }
        }

        $startsAt = trim((string) ($data['starts_at'] ?? ''));
        // Totéž pro termín konání kurzu.
        if ($startsAt === '') {
            $errors[] = 'Termín konání kurzu je povinný.';
        }

        if ($errors !== []) {
            // Pokud se nasbírala alespoň jedna chyba.
            throw new ValidationException($errors);
            // Vyhodí výjimku se seznamem všech chyb - zastaví další
            // zpracování (create()/update() se dál nespustí).
        }

        return [
            // Pokud jsme se dostali až sem, vstup je v pořádku - vrátíme
            // "očištěná" (trimnutá, přetypovaná) data připravená k uložení.
            'name' => $name,
            'code' => $code,
            'capacity' => $capacity,
            'starts_at' => $startsAt,
        ];
    }
}
