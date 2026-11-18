<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Repositories;
// Namespace App\Repositories - soubor src/Repositories/ParticipantRepository.php.

use App\Models\Participant;
// Import datové třídy Participant.
use App\Validation\ValidationException;
// Import vlastní výjimky pro validační chyby.
use PDO;
// Import vestavěné třídy PDO.

/**
 * CRUD a validace pro Participant.
 *
 * Validační pravidla ze zadání:
 *  - E-mail účastníka je platný a jedinečný.
 */
final class ParticipantRepository
{
    // Repository pro účastníky - obsluhuje databázovou práci s tabulkou
    // "participants" (CRUD + validace e-mailu).

    public function __construct(private readonly PDO $pdo)
    {
        // Uloží databázové připojení do neměnné vlastnosti $this->pdo.
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        // Vrátí všechny účastníky pro stránku se seznamem.
        $stmt = $this->pdo->query(
            "SELECT p.*,
                (SELECT COUNT(*) FROM enrollments e
                    WHERE e.participant_id = p.id AND e.status = 'enrolled') AS active_enrollments
             FROM participants p
             ORDER BY p.name ASC"
        );
        // ->query() se používá místo prepare()+execute(), protože dotaz
        // nemá žádné vstupní parametry od uživatele (nehrozí SQL injection).
        // Podřazeným dotazem navíc spočítá, kolik má účastník AKTIVNÍCH
        // přihlášek (zajímavý údaj pro přehled).

        return $stmt->fetchAll();
        // Vrátí všechny řádky jako pole asociativních polí.
    }

    public function find(int $id): ?array
    {
        // Najde jednoho účastníka podle ID (pro editaci / validaci).
        $stmt = $this->pdo->prepare('SELECT * FROM participants WHERE id = :id');
        // Připraví dotaz s placeholderem :id (bezpečné proti SQL injection).
        $stmt->execute(['id' => $id]);
        // Spustí dotaz s konkrétním ID.
        $row = $stmt->fetch();
        // Načte jeden řádek (nebo false).

        return $row === false ? null : $row;
        // Pokud nic nenašel, vrátí null, jinak nalezený řádek.
    }

    /**
     * Vrátí účastníky, kteří v daném kurzu NEMAJÍ aktivní (status = enrolled)
     * přihlášku - tedy ty, které lze (znovu) přihlásit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allNotActivelyEnrolledInCourse(int $courseId): array
    {
        // Používá se na detailu kurzu pro naplnění select boxu "koho
        // přihlásit" - nabídne jen účastníky, kteří v tomto kurzu ještě
        // (nebo už znovu) nemají aktivní přihlášku.
        $stmt = $this->pdo->prepare(
            "SELECT p.* FROM participants p
             WHERE p.id NOT IN (
                 SELECT e.participant_id FROM enrollments e
                 WHERE e.course_id = :course_id AND e.status = 'enrolled'
             )
             ORDER BY p.name ASC"
        );
        // Vnitřní (pod)dotaz vybere ID všech účastníků, kteří už mají
        // v tomto kurzu aktivní přihlášku; vnější dotaz vybere všechny
        // účastníky, kteří v tomto seznamu NEJSOU (NOT IN).
        $stmt->execute(['course_id' => $courseId]);
        // Spustí dotaz s konkrétním ID kurzu.

        return $stmt->fetchAll();
        // Vrátí seznam účastníků k přihlášení.
    }

    public function create(array $data): Participant
    {
        // Vytvoří (CREATE) nového účastníka - $data přichází z formuláře.
        $clean = $this->validate($data);
        // Nejprve validuje vstup; při chybě vyhodí ValidationException.

        $stmt = $this->pdo->prepare('INSERT INTO participants (name, email) VALUES (:name, :email)');
        // Připraví INSERT dotaz.
        $stmt->execute($clean);
        // Vloží nového účastníka s očištěnými daty.
        $id = (int) $this->pdo->lastInsertId();
        // Zjistí ID nově vytvořeného řádku.

        return new Participant($id, $clean['name'], $clean['email']);
        // Vrátí objekt Participant reprezentující nového účastníka.
    }

    public function update(int $id, array $data): Participant
    {
        // Upraví (UPDATE) existujícího účastníka podle ID.
        $clean = $this->validate($data, excludeId: $id);
        // Validuje vstup; jedinečnost e-mailu ignoruje tohoto samotného
        // účastníka (jinak by kolidoval se sebou samým).

        $stmt = $this->pdo->prepare('UPDATE participants SET name = :name, email = :email WHERE id = :id');
        // Připraví UPDATE dotaz.
        $stmt->execute($clean + ['id' => $id]);
        // Spustí dotaz; "+" sloučí pole $clean s polem obsahujícím 'id'.

        return new Participant($id, $clean['name'], $clean['email']);
        // Vrátí objekt Participant s aktualizovanými hodnotami.
    }

    public function delete(int $id): void
    {
        // Smaže (DELETE) účastníka podle ID.
        $stmt = $this->pdo->prepare('DELETE FROM participants WHERE id = :id');
        // Připraví DELETE dotaz. Díky ON DELETE CASCADE se smažou
        // i jeho přihlášky.
        $stmt->execute(['id' => $id]);
        // Spustí smazání.
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        // Pomocná metoda - ověří, zda už e-mail v databázi existuje
        // (pro validaci jedinečnosti e-mailu).
        $sql = 'SELECT id FROM participants WHERE email = :email';
        // Základní dotaz.
        $params = ['email' => $email];
        // Parametr pro bezpečné dosazení.
        if ($excludeId !== null) {
            // Pokud máme vyloučit konkrétního účastníka (při úpravě).
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
        // Pokud se našel řádek, e-mail už existuje -> true.
    }

    /**
     * @return array{name: string, email: string}
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        // Soukromá validační metoda - voláno jen uvnitř této třídy.
        $errors = [];
        // Pole pro sběr všech chyb.

        $name = trim((string) ($data['name'] ?? ''));
        // Vezme jméno ze vstupu, přetypuje a odstraní okrajové mezery.
        if ($name === '') {
            // Prázdné jméno není povolené.
            $errors[] = 'Jméno účastníka je povinné.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        // Totéž pro e-mail.
        if ($email === '') {
            // Prázdný e-mail.
            $errors[] = 'E-mail účastníka je povinný.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            // FILTER_VALIDATE_EMAIL ověří formát e-mailu (musí obsahovat
            // @, platnou doménu apod.) - POVINNÉ VALIDAČNÍ PRAVIDLO ZE
            // ZADÁNÍ "E-mail účastníka je platný".
            $errors[] = 'E-mail účastníka není platný.';
        } elseif ($this->emailExists($email, $excludeId)) {
            // Jinak zkontroluje jedinečnost - POVINNÉ VALIDAČNÍ PRAVIDLO
            // ZE ZADÁNÍ "E-mail účastníka je ... jedinečný".
            $errors[] = 'E-mail účastníka musí být jedinečný - tento e-mail už je zaregistrovaný.';
        }

        if ($errors !== []) {
            // Pokud se nasbírala alespoň jedna chyba.
            throw new ValidationException($errors);
            // Zastaví další zpracování a vyhodí výjimku se všemi chybami.
        }

        return [
            // Vrátí očištěná data připravená k uložení do databáze.
            'name' => $name,
            'email' => $email,
        ];
    }
}
