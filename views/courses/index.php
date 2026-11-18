<div class="page-header">
    <h1>Kurzy</h1>
    <a class="btn btn-primary" href="/courses/new">+ Nový kurz</a>
</div>

<form method="get" action="/courses" class="filters card">
    <div class="form-group">
        <label for="from">Termín od</label>
        <input type="date" id="from" name="from" value="<?= h($filters['from']) ?>">
    </div>
    <div class="form-group">
        <label for="to">Termín do</label>
        <input type="date" id="to" name="to" value="<?= h($filters['to']) ?>">
    </div>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="only_free" name="only_free" value="1" <?= $filters['only_free'] ? 'checked' : '' ?>>
        <label for="only_free" style="margin:0;">Jen kurzy s volným místem</label>
    </div>
    <div class="form-group" style="min-width:0;">
        <button class="btn btn-primary" type="submit">Filtrovat</button>
        <a class="btn" href="/courses">Zrušit filtr</a>
    </div>
</form>

<?php if ($courses === []): ?>
    <div class="card empty-state">Žádné kurzy neodpovídají zadanému filtru.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Název</th>
            <th>Kód</th>
            <th>Termín konání</th>
            <th>Obsazenost</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $course): ?>
            <?php $isFull = $course['free_count'] <= 0; ?>
            <tr>
                <td><a href="/courses/<?= (int) $course['id'] ?>"><?= h($course['name']) ?></a></td>
                <td><?= h($course['code']) ?></td>
                <td><?= h($course['starts_at']) ?></td>
                <td>
                    <?= (int) $course['enrolled_count'] ?> / <?= (int) $course['capacity'] ?>
                    <?php if ($isFull): ?>
                        <span class="badge badge-cancelled">plno</span>
                    <?php else: ?>
                        <span class="badge badge-enrolled"><?= (int) $course['free_count'] ?> volných</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a class="btn btn-sm" href="/courses/<?= (int) $course['id'] ?>">Detail</a>
                    <a class="btn btn-sm" href="/courses/<?= (int) $course['id'] ?>/edit">Upravit</a>
                    <form class="inline" method="post" action="/courses/<?= (int) $course['id'] ?>/delete"
                          onsubmit="return confirm('Opravdu smazat kurz včetně všech přihlášek?');">
                        <button class="btn btn-sm btn-danger" type="submit">Smazat</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
