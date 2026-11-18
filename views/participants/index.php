<div class="page-header">
    <h1>Účastníci</h1>
    <a class="btn btn-primary" href="/participants/new">+ Nový účastník</a>
</div>

<?php if ($participants === []): ?>
    <div class="card empty-state">Zatím nejsou žádní účastníci.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Jméno</th>
            <th>E-mail</th>
            <th>Aktivní přihlášky</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($participants as $p): ?>
            <tr>
                <td><?= h($p['name']) ?></td>
                <td><?= h($p['email']) ?></td>
                <td><?= (int) $p['active_enrollments'] ?></td>
                <td class="actions">
                    <a class="btn btn-sm" href="/participants/<?= (int) $p['id'] ?>/edit">Upravit</a>
                    <form class="inline" method="post" action="/participants/<?= (int) $p['id'] ?>/delete"
                          onsubmit="return confirm('Opravdu smazat tohoto účastníka včetně jeho přihlášek?');">
                        <button class="btn btn-sm btn-danger" type="submit">Smazat</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
