<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Repositories;
// Namespace App\Repositories - soubor src/Repositories/EnrollmentRepository.php.

use App\Models\Enrollment;
// Import datové třídy Enrollment (a jejích konstant STATUS_*).
use App\Validation\ValidationException;
// Import vlastní výjimky pro validační chyby.
use PDO;
// Import vestavěné třídy PDO.

/**
 * Přihlašování účastníků do kurzů a hlídání kapacity.
 *
 * Validační pravidla ze zadání:
 *  - Účastník se nesmí přihlásit dvakrát (nesmí mít dvě aktivní přihlášky
 *    do stejného kurzu).
 *  - Počet aktivních přihlášek nesmí překročit kapacitu kurzu.
 *
 * Domácí práce:
 *  - Stavy: enrolled, cancelled, completed.
 */
final class EnrollmentRepository
{
    // Nejdůležitější repository v aplikaci - tady se řeší samotné
    // přihlašování, kontrola duplicit, kontrola kapacity a přechody
    // mezi stavy přihlášky.

    public function __construct(private readonly PDO $pdo)
    {
        // Uloží databázové připojení do neměnné vlastnosti.
    }

    /**
     * Seznam přihlášených do kurzu (včetně jména/e-mailu účastníka),
     * od nejnovějších.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByCourse(int $courseId): array
    {
        // Vrátí všechny přihlášky (ve všech stavech) pro daný kurz,
        // včetně jména a e-mailu přihlášeného účastníka - používá se
        // na detailu kurzu pro "seznam přihlášených" ze zadání.
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.name AS participant_name, p.email AS participant_email
             FROM enrollments e
             JOIN participants p ON p.id = e.participant_id
             WHERE e.course_id = :course_id
             ORDER BY e.enrolled_at DESC, e.id DESC'
        );
        // JOIN spojí tabulku enrollments s participants podle
        // participant_id, abychom měli jméno a e-mail účastníka
        // rovnou v jednom řádku (ne jen jeho ID).
        $stmt->execute(['course_id' => $courseId]);
        // Spustí dotaz pro konkrétní kurz.

        return $stmt->fetchAll();
        // Vrátí všechny přihlášky seřazené od nejnovější.
    }

    public function find(int $id): ?array
    {
        // Najde jednu přihlášku podle ID (používá se při cancel/complete).
        $stmt = $this->pdo->prepare('SELECT * FROM enrollments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
        // Vrátí null, pokud přihláška s daným ID neexistuje.
    }

    /**
     * Počet aktivních (status = enrolled) přihlášek v kurzu.
     */
    public function countActive(int $courseId): int
    {
        // Klíčová metoda pro hlídání kapacity - spočítá, kolik má kurz
        // AKTUÁLNĚ aktivních přihlášek (zrušené a absolvované se
        // NEZAPOČÍTAVAJÍ, takže uvolňují místo).
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM enrollments WHERE course_id = :course_id AND status = 'enrolled'"
        );
        // COUNT(*) spočítá počet řádků splňujících podmínku.
        $stmt->execute(['course_id' => $courseId]);
        // Spustí dotaz pro konkrétní kurz.

        return (int) $stmt->fetchColumn();
        // fetchColumn() vrátí hodnotu prvního sloupce prvního řádku
        // (tady přímo výsledek COUNT(*)); přetypuje na int.
    }

    /**
     * Přihlásí účastníka do kurzu.
     *
     * Ověří:
     *  - kurz a účastník existují,
     *  - účastník ještě nemá v kurzu aktivní přihlášku (nesmí se přihlásit dvakrát),
     *  - kapacita kurzu není naplněná.
     *
     * Celá operace běží v transakci (BEGIN IMMEDIATE), aby dvě souběžná
     * přihlášení nemohla kapacitu překročit.
     */
    public function enroll(int $courseId, int $participantId): Enrollment
    {
        // Hlavní metoda celé aplikace - vytvoří novou přihlášku a hlídá
        // při tom VŠECHNA povinná validační pravidla ze zadání týkající
        // se přihlašování.
        $this->pdo->exec('BEGIN IMMEDIATE');
        // Spustí databázovou TRANSAKCI s okamžitým zámkem (IMMEDIATE) -
        // zabrání tomu, aby si dva souběžné požadavky "přečetly" stejný
        // (ještě ne naplněný) počet míst a oba prošli kontrolou kapacity
        // dřív, než se první z nich stihne zapsat. Díky zámku se druhý
        // požadavek musí počkat, až první transakce skončí.

        try {
            // Celá logika běží v try/catch, aby se při jakékoli chybě
            // transakce bezpečně vrátila zpět (ROLLBACK) a databáze
            // nezůstala v polovičatém stavu.
            $course = $this->pdo->prepare('SELECT * FROM courses WHERE id = :id');
            // Připraví dotaz na existenci kurzu.
            $course->execute(['id' => $courseId]);
            // Spustí dotaz.
            $courseRow = $course->fetch();
            // Načte řádek kurzu (nebo false).
            if ($courseRow === false) {
                // Kurz s tímto ID neexistuje.
                throw new ValidationException(['Vybraný kurz neexistuje.']);
                // Vyhodí validační výjimku - zachytí se v catch bloku níž.
            }

            $participant = $this->pdo->prepare('SELECT * FROM participants WHERE id = :id');
            // Připraví dotaz na existenci účastníka.
            $participant->execute(['id' => $participantId]);
            if ($participant->fetch() === false) {
                // Účastník s tímto ID neexistuje.
                throw new ValidationException(['Vybraný účastník neexistuje.']);
            }

            $existing = $this->pdo->prepare(
                "SELECT id FROM enrollments
                 WHERE course_id = :course_id AND participant_id = :participant_id AND status = 'enrolled'"
            );
            // Dotaz, který zjistí, zda tento účastník už má v tomto
            // kurzu AKTIVNÍ přihlášku.
            $existing->execute(['course_id' => $courseId, 'participant_id' => $participantId]);
            if ($existing->fetch() !== false) {
                // Pokud se taková přihláška našla.
                throw new ValidationException(['Účastník je do tohoto kurzu již přihlášen.']);
                // POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ:
                // "Účastník se nesmí přihlásit dvakrát."
            }

            $activeCount = $this->countActive($courseId);
            // Zjistí, kolik má kurz aktuálně aktivních přihlášek.
            if ($activeCount >= (int) $courseRow['capacity']) {
                // Pokud je počet aktivních přihlášek už na hranici
                // (nebo nad) kapacity kurzu.
                throw new ValidationException(['Kurz je plně obsazen, kapacitu nelze překročit.']);
                // POVINNÉ VALIDAČNÍ PRAVIDLO ZE ZADÁNÍ:
                // "Počet aktivních přihlášek nesmí překročit kapacitu."
            }

            $enrolledAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            // Vytvoří aktuální datum a čas a zformátuje ho do textu
            // (formát "RRRR-MM-DD HH:MM:SS") pro uložení do databáze.

            $insert = $this->pdo->prepare(
                "INSERT INTO enrollments (course_id, participant_id, enrolled_at, status)
                 VALUES (:course_id, :participant_id, :enrolled_at, 'enrolled')"
            );
            // Připraví INSERT nové přihlášky - nový záznam má vždy
            // počáteční stav 'enrolled' (přihlášen).
            $insert->execute([
                'course_id' => $courseId,
                'participant_id' => $participantId,
                'enrolled_at' => $enrolledAt,
            ]);
            // Spustí vložení s konkrétními hodnotami.
            $id = (int) $this->pdo->lastInsertId();
            // Zjistí ID nově vytvořené přihlášky.

            $this->pdo->exec('COMMIT');
            // Potvrdí (uloží natrvalo) celou transakci - od teď jsou
            // změny viditelné i pro ostatní připojení/požadavky.

            return new Enrollment($id, $courseId, $participantId, $enrolledAt, Enrollment::STATUS_ENROLLED);
            // Vrátí objekt Enrollment reprezentující novou přihlášku.
        } catch (\Throwable $e) {
            // Zachytí JAKOUKOLI chybu/výjimku (ValidationException i jiné).
            $this->pdo->exec('ROLLBACK');
            // Vrátí transakci zpět - žádná z provedených změn se neuloží,
            // databáze zůstane v původním (konzistentním) stavu.
            throw $e;
            // Chybu znovu vyhodí dál (nepolyká ji), aby ji mohl zpracovat
            // controller a zobrazit uživateli chybovou hlášku.
        }
    }

    /**
     * Zruší přihlášku (status -> cancelled). Lze zrušit jen aktivní přihlášku.
     * Uvolní tím místo v kapacitě kurzu.
     */
    public function cancel(int $enrollmentId): void
    {
        // Veřejné rozhraní pro zrušení přihlášky - interně jen zavolá
        // obecnou metodu pro přechod mezi stavy.
        $this->transitionStatus($enrollmentId, Enrollment::STATUS_ENROLLED, Enrollment::STATUS_CANCELLED);
        // Povolený přechod je jen enrolled -> cancelled (viz DOMÁCÍ PRÁCE
        // ZE ZADÁNÍ: "Přidejte stavy enrolled, cancelled a completed.").
    }

    /**
     * Oznaci přihlášku jako absolvovanou (status -> completed). Lze provést
     * jen z aktivního stavu.
     */
    public function complete(int $enrollmentId): void
    {
        // Veřejné rozhraní pro oznacení přihlášky jako dokončené.
        $this->transitionStatus($enrollmentId, Enrollment::STATUS_ENROLLED, Enrollment::STATUS_COMPLETED);
        // Povolený přechod je jen enrolled -> completed.
    }

    private function transitionStatus(int $enrollmentId, string $fromStatus, string $toStatus): void
    {
        // Soukromá, obecná metoda, kterou používají cancel() i complete() -
        // hlídá, že se stav mění jen správným, povoleným směrem.
        $row = $this->find($enrollmentId);
        // Načte přihlášku podle ID.
        if ($row === null) {
            // Přihláška s tímto ID neexistuje.
            throw new ValidationException(['Přihláška neexistuje.']);
        }
        if ($row['status'] !== $fromStatus) {
            // Pokud přihláška NENÍ v očekávaném výchozím stavu (např. se
            // snažíme zrušit přihlášku, která už je 'completed') -
            // takový přechod není povolen.
            throw new ValidationException(["Tento přechod stavu není povolen (aktuální stav: {$row['status']})."]);
        }

        $stmt = $this->pdo->prepare('UPDATE enrollments SET status = :status WHERE id = :id');
        // Připraví UPDATE, který změní jen sloupec status.
        $stmt->execute(['status' => $toStatus, 'id' => $enrollmentId]);
        // Spustí změnu stavu. Protože zrušené (cancelled) i dokončené
        // (completed) přihlášky se v countActive() NEPOČÍTAJÍ, tím
        // pádem se automaticky uvolní místo v kapacitě kurzu.
    }
}
