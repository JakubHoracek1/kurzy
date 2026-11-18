<?php
$isEdit = $participant !== null;
$action = $isEdit ? '/participants/' . (int) $participant['id'] : '/participants';
$name = $old['name'] ?? ($participant['name'] ?? '');
$email = $old['email'] ?? ($participant['email'] ?? '');
?>

<h1><?= $isEdit ? 'Upravit účastníka' : 'Nový účastník' ?></h1>

<?php if ($errors !== []): ?>
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
        <div class="form-group">
            <label for="name">Jméno</label>
            <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">E-mail (musí být platný a jedinečný)</label>
            <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
        </div>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Uložit změny' : 'Vytvořit účastníka' ?></button>
        <a class="btn" href="/participants">Zrušit</a>
    </form>
</div>
