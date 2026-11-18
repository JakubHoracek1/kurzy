<?php
$percent = $course['capacity'] > 0
    ? min(100, (int) round($course['enrolled_count'] / $course['capacity'] * 100))
    : 0;
$isFull = $course['free_count'] <= 0;

$statusLabels = [
    'enrolled' => 'Přihlášen',
    'cancelled' => 'Zrušeno',
    'completed' => 'Absolvováno',
];
?>

<div class="page-header">
    <h1><?= h($course['name']) ?></h1>
    <div class="actions">
        <a class="btn" href="/courses/<?= (int) $course['id'] ?>/edit">Upravit kurz</a>
        <a class="btn" href="/courses">Zpět na seznam</a>
    </div>
</div>

<div class="card">
    <p><strong>Kód kurzu:</strong> <?= h($course['code']) ?></p>
    <p><strong>Termín konání:</strong> <?= h($course['starts_at']) ?></p>
    <p>
        <strong>Obsazenost:</strong>
        <?= (int) $course['enrolled_count'] ?> / <?= (int) $course['capacity'] ?>
        (<?= (int) $course['free_count'] ?> volných míst)
        <?php if ($isFull): ?><span class="badge badge-cancelled">plno</span><?php endif; ?>
    </p>
    <div class="capacity-bar">
        <div class="capacity-bar-fill <?= $isFull ? 'full' : '' ?>" style="width: <?= $percent ?>%;"></div>
    </div>
</div>

<div class="card">
    <h2>Přihlásit účastníka</h2>
    <?php if ($isFull): ?>
        <p class="muted">Kurz je plně obsazen, nelze přidat dalšího účastníka.</p>
    <?php elseif ($availableParticipants === []): ?>
        <p class="muted">Všichni existující účastníci jsou již do tohoto kurzu přihlášeni. <a href="/participants/new">Vytvořit nového účastníka</a>.</p>
    <?php else: ?>
        <form method="post" action="/courses/<?= (int) $course['id'] ?>/enroll">
            <div class="form-group">
                <label for="participant_id">Účastník</label>
                <select id="participant_id" name="participant_id" required>
                    <option value="">– vyberte účastníka –</option>
                    <?php foreach ($availableParticipants as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Přihlásit</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Seznam přihlášených</h2>
    <?php if ($enrollments === []): ?>
        <p class="empty-state">Do tohoto kurzu se ještě nikdo nepřihlásil.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Účastník</th>
                <th>E-mail</th>
                <th>Přihlášen</th>
                <th>Stav</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($enrollments as $e): ?>
                <tr>
                    <td><?= h($e['participant_name']) ?></td>
                    <td><?= h($e['participant_email']) ?></td>
                    <td><?= h($e['enrolled_at']) ?></td>
                    <td>
                        <span class="badge badge-<?= h($e['status']) ?>">
                            <?= h($statusLabels[$e['status']] ?? $e['status']) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <?php if ($e['status'] === 'enrolled'): ?>
                            <form class="inline" method="post" action="/enrollments/<?= (int) $e['id'] ?>/complete">
                                <button class="btn btn-sm" type="submit">Absolvoval</button>
                            </form>
                            <form class="inline" method="post" action="/enrollments/<?= (int) $e['id'] ?>/cancel"
                                  onsubmit="return confirm('Zrušit přihlášku tohoto účastníka?');">
                                <button class="btn btn-sm btn-danger" type="submit">Zrušit</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
