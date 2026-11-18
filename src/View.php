<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App;
// Namespace App - soubor leží v src/View.php, takže třída je App\View.

/**
 * Jednoduchý renderer šablon: vykreslí view soubor do bufferu
 * a vloží jej do společného layoutu (views/layout.php).
 */
final class View
{
    public static function render(string $template, array $data = [], int $statusCode = 200): void
    {
        // Statická metoda, kterou controller zavolá jako
        // View::render('courses/index', ['courses' => ...]).
        // $template je cesta k šabloně (bez .php), $data jsou proměnné,
        // které se v šabloně mají použít, $statusCode je HTTP stav odpovědi.
        http_response_code($statusCode);
        // Nastaví HTTP návratový kód odpovědi (200 OK, 404 Nenalezeno,
        // 422 chybná data formuláře apod.).
        extract($data, EXTR_SKIP);
        // Rozbalí asociativní pole $data do jednotlivých proměnných
        // (např. $data['courses'] se stane proměnnou $courses), aby se
        // v šabloně dalo psát přímo $courses místo $data['courses'].
        // EXTR_SKIP zabrání přepsání už existujících proměnných (např. $template).

        $viewFile = dirname(__DIR__) . '/views/' . $template . '.php';
        // Sestaví absolutní cestu k souboru šablony, např.
        // .../views/courses/index.php.
        if (!is_file($viewFile)) {
            // Pokud takový soubor šablony neexistuje.
            throw new \RuntimeException("Šablona '{$template}' nenalezena.");
            // Vyhodí výjimku s jasnou chybovou hláškou - snadnější debug.
        }

        ob_start();
        // Spustí tzv. output buffering - od teď se žádný výstup (echo,
        // konec PHP tagu v šabloně) nevypisuje hned do prohlížeče, ale
        // ukládá se do bufferu (vyrovnávací paměti).
        require $viewFile;
        // Vloží a provede konkrétní šablonu (např. courses/index.php) -
        // vše, co šablona vypíše, jde do bufferu, ne přímo na výstup.
        $content = ob_get_clean();
        // Vezme vše, co se zatím nahromadilo v bufferu, vrátí to jako
        // string do $content a buffer zase vypne.

        require dirname(__DIR__) . '/views/layout.php';
        // Vloží společný layout (views/layout.php), který uvnitř sebe
        // vypíše proměnnou $content - tím se obsah konkrétní stránky
        // "zabalí" do společné kostry (header, navigace, atd.).
    }
}
