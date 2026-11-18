<?php
// PHP blok připravující proměnné pro formulář (stejný princip jako
// u views/courses/form.php - jedna šablona pro "nový" i "úprava").
$isEdit = $participant !== null;
// Pokud controller předal existujícího účastníka, jde o editaci.
$action = $isEdit ? '/participants/' . (int) $participant['id'] : '/participants';
// Cíl formuláře podle toho, zda editujeme, nebo vytváříme nového.
$name = $old['name'] ?? ($participant['name'] ?? '');
// Hodnota jména: předchozí neplatný vstup, jinak existující účastník,
// jinak prázdný string.
$email = $old['email'] ?? ($participant['email'] ?? '');
// Stejná logika pro e-mail.
?>

<h1><?= $isEdit ? 'Upravit účastníka' : 'Nový účastník' ?></h1>
<!-- Nadpis podle toho, zda jde o editaci, nebo nového účastníka. -->

<?php if ($errors !== []): ?>
    <!-- Výpis validačních chyb, pokud nějaké controller předal. -->
    <div class="errors">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= h($action) ?>">
        <!-- POST formulář s cílem buď /participants (nový), nebo
             /participants/{id} (úprava). -->
        <div class="form-group">
            <label for="name">Jméno</label>
            <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">E-mail (musí být platný a jedinečný)</label>
            <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
            <!-- type="email" - prohlížeč poskytne základní kontrolu
                 formátu e-mailu (klientská nápověda); skutečná,
                 spolehlivá kontrola platnosti a jedinečnosti běží na
                 serveru v ParticipantRepository::validate(). -->
        </div>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Uložit změny' : 'Vytvořit účastníka' ?></button>
        <a class="btn" href="/participants">Zrušit</a>
    </form>
</div>
