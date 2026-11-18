<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

// Když běžíme přes vestavěný PHP server (php -S ... public/index.php) a
// požadavek cílí na reálný soubor v public/ (např. /assets/style.css),
// vrátíme false - server si pak soubor obslouží sám. Doporučený postup
// z dokumentace PHP pro router skript vestavěného serveru.
if (PHP_SAPI === 'cli-server') {
    // PHP_SAPI říká, v jakém "rozhraní" PHP právě běží - 'cli-server'
    // znamená, že jde o vestavěný server spuštěný příkazem `php -S`.
    $requestedPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    // Z URL požadavku vezme jen cestu (bez query stringu jako ?from=...)
    // a dekóduje URL-encoded znaky (např. %20 zpět na mezeru).
    $filePath = __DIR__ . $requestedPath;
    // Sestaví reálnou cestu na disku (__DIR__ = složka public/).
    if ($requestedPath !== '/' && is_file($filePath)) {
        // Pokud požadovaná cesta odpovídá existujícímu souboru
        // (např. /assets/style.css) a nejde o kořenovou "/".
        return false;
        // Vrácení false z router skriptu řekne vestavěnému serveru
        // "obsluž si tenhle soubor sám jako statický obsah" - pro
        // ostatní (neexistující) cesty pokračujeme dál do routeru.
    }
}

require __DIR__ . '/../src/bootstrap.php';
// Načte společný bootstrap (autoloader, helpery h()/flash()/redirect()).

use App\Controllers\CourseController;
// Import controlleru pro kurzy.
use App\Controllers\EnrollmentController;
// Import controlleru pro přihlášky.
use App\Controllers\ParticipantController;
// Import controlleru pro účastníky.
use App\Database;
// Import třídy pro databázové připojení.

session_start();
// Spustí PHP session - potřebné pro flash zprávy (success/error hlášky
// zobrazované po přesměrování).

$pdo = Database::connection();
// Získá (nebo při prvním volání vytvoří) databázové připojení k SQLite.

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Vezme jen cestu z URL (bez query stringu), např. z "/courses?from=x"
// vezme "/courses".
$uri = rtrim($uri, '/');
// Odstraní koncové lomítko (aby "/courses/" a "/courses" byly stejná cesta).
if ($uri === '') {
    // Pokud po odstranění lomítka zbyl prázdný string (šlo o samotné "/").
    $uri = '/';
    // Vrátí zpět kořenovou cestu.
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Zjistí HTTP metodu požadavku (GET, POST...).

if ($uri === '/') {
    // Pro kořenovou adresu aplikace.
    redirect('/courses');
    // Přesměruje rovnou na seznam kurzů (úvodní stránka aplikace).
}

$courseController = new CourseController($pdo);
// Vytvoří instanci controlleru pro kurzy.
$participantController = new ParticipantController($pdo);
// Vytvoří instanci controlleru pro účastníky.
$enrollmentController = new EnrollmentController($pdo);
// Vytvoří instanci controlleru pro přihlášky.

/**
 * Routovací tabulka: [HTTP metoda, regex vzor cesty, [controller, metoda]].
 * Zachycené skupiny z regexu (např. ID) se předají jako argumenty metody.
 */
$routes = [
    // Pole routy - každý řádek říká "na tuhle metodu a tenhle vzor cesty
    // zavolej tuhle metodu controlleru".
    ['GET', '#^/courses$#', [$courseController, 'index']],
    // GET /courses -> seznam kurzů.
    ['GET', '#^/courses/new$#', [$courseController, 'create']],
    // GET /courses/new -> formulář nového kurzu.
    ['POST', '#^/courses$#', [$courseController, 'store']],
    // POST /courses -> uložení nového kurzu.
    ['GET', '#^/courses/(\d+)/edit$#', [$courseController, 'edit']],
    // GET /courses/5/edit -> formulář úpravy kurzu; (\d+) zachytí ID.
    ['POST', '#^/courses/(\d+)$#', [$courseController, 'update']],
    // POST /courses/5 -> uložení úpravy kurzu.
    ['POST', '#^/courses/(\d+)/delete$#', [$courseController, 'destroy']],
    // POST /courses/5/delete -> smazání kurzu.
    ['POST', '#^/courses/(\d+)/enroll$#', [$courseController, 'enroll']],
    // POST /courses/5/enroll -> přihlášení účastníka do kurzu.
    ['GET', '#^/courses/(\d+)$#', [$courseController, 'show']],
    // GET /courses/5 -> detail kurzu (musí být AŽ ZA /new a /edit,
    // jinak by regex (\d+) nikdy nedostal šanci - "new" ani "5/edit"
    // samy o sobě nejsou čísla, takže pořadí tu není kritické, ale
    // obecně platí: konkrétnější vzory patří výš).

    ['GET', '#^/participants$#', [$participantController, 'index']],
    // GET /participants -> seznam účastníků.
    ['GET', '#^/participants/new$#', [$participantController, 'create']],
    // GET /participants/new -> formulář nového účastníka.
    ['POST', '#^/participants$#', [$participantController, 'store']],
    // POST /participants -> uložení nového účastníka.
    ['GET', '#^/participants/(\d+)/edit$#', [$participantController, 'edit']],
    // GET /participants/5/edit -> formulář úpravy účastníka.
    ['POST', '#^/participants/(\d+)$#', [$participantController, 'update']],
    // POST /participants/5 -> uložení úpravy účastníka.
    ['POST', '#^/participants/(\d+)/delete$#', [$participantController, 'destroy']],
    // POST /participants/5/delete -> smazání účastníka.

    ['POST', '#^/enrollments/(\d+)/cancel$#', [$enrollmentController, 'cancel']],
    // POST /enrollments/5/cancel -> zrušení přihlášky.
    ['POST', '#^/enrollments/(\d+)/complete$#', [$enrollmentController, 'complete']],
    // POST /enrollments/5/complete -> oznacení přihlášky jako absolvované.
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    // Projde routovací tabulku řádek po řádku; list() syntaxe rozbalí
    // každý vnitřní prvek pole přímo do tří proměnných.
    if ($method !== $routeMethod) {
        // Pokud HTTP metoda požadavku neodpovídá metodě dané routy.
        continue;
        // Přeskočí na další routu, tuhle nemá smysl dál zkoušet.
    }
    if (preg_match($pattern, $uri, $matches) === 1) {
        // Zkusí porovnat cestu požadavku s regulárním výrazem routy;
        // $matches se naplní zachycenými skupinami (např. ID z URL),
        // pokud vzor sedí.
        array_shift($matches);
        // Odstraní první prvek $matches (celý shodný text), takže
        // zůstanou jen zachycené skupiny (tj. ID).
        call_user_func_array($handler, $matches);
        // Zavolá metodu příslušného controlleru ($handler = [objekt,
        // 'nazevMetody']) a jako argumenty jí předá zachycená ID.
        exit;
        // Ukončí skript - požadavek byl obsloužen, dál není co dělat.
    }
}

http_response_code(404);
// Žádná routa nesedla - nastaví HTTP stav 404 (stránka nenalezena).
require __DIR__ . '/../views/errors/404-plain.php';
// Zobrazí jednoduchou 404 stránku (bez společného layoutu, protože ten
// vyžaduje funkční session/flash, což tu nemusí být k dispozici).
