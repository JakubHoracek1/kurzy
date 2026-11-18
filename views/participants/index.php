<div class="page-header">
    <!-- Nadpis stránky + tlačítko pro vytvoření nového účastníka. -->
    <h1>Účastníci</h1>
    <a class="btn btn-primary" href="/participants/new">+ Nový účastník</a>
</div>

<?php if ($participants === []): ?>
    <!-- Pokud v databázi ještě nejsou žádní účastníci. -->
    <div class="card empty-state">Zatím nejsou žádní účastníci.</div>
<?php else: ?>
    <!-- Jinak vypíšeme tabulku všech účastníků. -->
    <table>
        <thead>
        <tr>
            <th>Jméno</th>
            <th>E-mail</th>
            <th>Aktivní přihlášky</th>
            <th></th>
            <!-- Prázdný sloupec pro akční tlačítka (Upravit/Smazat). -->
        </tr>
        </thead>
        <tbody>
        <?php foreach ($participants as $p): ?>
            <!-- Projde všechny účastníky vrácené
                 ParticipantRepository::all(). -->
            <tr>
                <td><?= h($p['name']) ?></td>
                <td><?= h($p['email']) ?></td>
                <td><?= (int) $p['active_enrollments'] ?></td>
                <!-- Počet aktivních přihlášek - dopočítaný podřazeným
                     dotazem přímo v ParticipantRepository::all(). -->
                <td class="actions">
                    <a class="btn btn-sm" href="/participants/<?= (int) $p['id'] ?>/edit">Upravit</a>
                    <form class="inline" method="post" action="/participants/<?= (int) $p['id'] ?>/delete"
                          onsubmit="return confirm('Opravdu smazat tohoto účastníka včetně jeho přihlášek?');">
                        <!-- Smazání přes POST formulář (HTML nemá
                             nativní DELETE metodu); confirm() se zeptá
                             na potvrzení před odesláním. -->
                        <button class="btn btn-sm btn-danger" type="submit">Smazat</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
