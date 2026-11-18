<?php
$isEdit = $course !== null;
$action = $isEdit ? '/courses/' . (int) $course['id'] : '/courses';
$name = $old['name'] ?? ($course['name'] ?? '');
$code = $old['code'] ?? ($course['code'] ?? '');
$capacity = $old['capacity'] ?? ($course['capacity'] ?? '');
$startsAt = $old['starts_at'] ?? ($course['starts_at'] ?? '');
?>

<h1><?= $isEdit ? 'Upravit kurz' : 'Nový kurz' ?></h1>

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
            <label for="name">Název kurzu</label>
            <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
        </div>
        <div class="form-group">
            <label for="code">Kód kurzu (musí být jedinečný)</label>
            <input type="text" id="code" name="code" value="<?= h($code) ?>" required>
        </div>
        <div class="form-group">
            <label for="capacity">Kapacita (celé číslo &gt; 0)</label>
            <input type="number" id="capacity" name="capacity" min="1" step="1" value="<?= h($capacity) ?>" required>
        </div>
        <div class="form-group">
            <label for="starts_at">Termín konání</label>
            <input type="datetime-local" id="starts_at" name="starts_at" value="<?= h($startsAt) ?>" required>
        </div>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Uložit změny' : 'Vytvořit kurz' ?></button>
        <a class="btn" href="/courses">Zrušit</a>
    </form>
</div>
