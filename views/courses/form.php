<?php
// Blok PHP logiky na začátku šablony - připraví proměnné pro formulář
// tak, aby stejná šablona šla použít pro "nový kurz" i "úprava kurzu".
$isEdit = $course !== null;
// Pokud controller předal existující kurz, jde o editaci, jinak o nový.
$action = $isEdit ? '/courses/' . (int) $course['id'] : '/courses';
// Cíl formuláře: při editaci POST na /courses/{id} (update), jinak
// POST na /courses (store).
$name = $old['name'] ?? ($course['name'] ?? '');
// Hodnota pole "name": nejdřív zkusí předchozí (neplatný) vstup
// z formuláře ($old - po chybě validace), jinak hodnotu existujícího
// kurzu (editace), jinak prázdný string (nový kurz).
$code = $old['code'] ?? ($course['code'] ?? '');
// Stejná logika pro kód kurzu.
$capacity = $old['capacity'] ?? ($course['capacity'] ?? '');
// Stejná logika pro kapacitu.
$startsAt = $old['starts_at'] ?? ($course['starts_at'] ?? '');
// Stejná logika pro termín konání.
?>

<h1><?= $isEdit ? 'Upravit kurz' : 'Nový kurz' ?></h1>
<!-- Nadpis se liší podle toho, zda jde o editaci nebo nový kurz. -->

<?php if ($errors !== []): ?>
    <!-- Pokud controller předal neprázdné pole chyb (po neúspěšné validaci). -->
    <div class="errors">
        <ul>
            <?php foreach ($errors as $err): ?>
                <!-- Projde všechny chybové hlášky a vypíše každou na
                     samostatný řádek seznamu. -->
                <li><?= h($err) ?></li>
                <!-- h() escapuje text chyby (i když v tomto případě jde
                     o text napsaný přímo v PHP kódu, je to dobrý zvyk
                     escapovat vždy). -->
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <!-- Bílá karta obalující samotný formulář. -->
    <form method="post" action="<?= h($action) ?>">
        <!-- POST formulář - cíl (action) je buď /courses, nebo /courses/{id}. -->
        <div class="form-group">
            <label for="name">Název kurzu</label>
            <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
            <!-- "required" = prohlížeč samotný nepustí odeslání
                 prázdného pole (základní, rychlá validace na klientu;
                 ta skutečná/spolehlivá validace ale běží v PHP na
                 serveru, protože required se dá v prohlížeči obejít). -->
        </div>
        <div class="form-group">
            <label for="code">Kód kurzu (musí být jedinečný)</label>
            <input type="text" id="code" name="code" value="<?= h($code) ?>" required>
        </div>
        <div class="form-group">
            <label for="capacity">Kapacita (celé číslo &gt; 0)</label>
            <input type="number" id="capacity" name="capacity" min="1" step="1" value="<?= h($capacity) ?>" required>
            <!-- type="number" + min="1" + step="1" = prohlížeč nabídne
                 jen celá kladná čísla (klientská nápověda); reálnou
                 kontrolu "celé číslo > 0" ale dělá CourseRepository::validate(). -->
        </div>
        <div class="form-group">
            <label for="starts_at">Termín konání</label>
            <input type="datetime-local" id="starts_at" name="starts_at" value="<?= h($startsAt) ?>" required>
            <!-- datetime-local = prohlížeč nabídne výběr data i času
                 najednou (kalendář + čas). -->
        </div>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Uložit změny' : 'Vytvořit kurz' ?></button>
        <!-- Text tlačítka se liší podle toho, zda jde o editaci, nebo
             vytvoření nového kurzu. -->
        <a class="btn" href="/courses">Zrušit</a>
        <!-- Odkaz zpět na seznam kurzů bez uložení. -->
    </form>
</div>
