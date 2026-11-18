<?php
// PHP blok na začátku šablony - připraví pomocné proměnné pro vykreslení.
$percent = $course['capacity'] > 0
    ? min(100, (int) round($course['enrolled_count'] / $course['capacity'] * 100))
    : 0;
// Spočítá procento obsazenosti kurzu pro šířku progress baru; ochrana
// proti dělení nulou (kdyby capacity bylo 0, i když to validace nedovolí).
$isFull = $course['free_count'] <= 0;
// True, pokud kurz už nemá žádné volné místo.

$statusLabels = [
    // Mapování technických názvů stavů (z databáze/zadání) na hezké
    // české popisky pro zobrazení uživateli.
    'enrolled' => 'Přihlášen',
    'cancelled' => 'Zrušeno',
    'completed' => 'Absolvováno',
];
?>

<div class="page-header">
    <!-- Nadpis s názvem kurzu + tlačítka akcí. -->
    <h1><?= h($course['name']) ?></h1>
    <div class="actions">
        <a class="btn" href="/courses/<?= (int) $course['id'] ?>/edit">Upravit kurz</a>
        <a class="btn" href="/courses">Zpět na seznam</a>
    </div>
</div>

<div class="card">
    <!-- Karta se základními údaji o kurzu a obsazeností. POŽADOVANÁ
         FUNKCE ZE ZADÁNÍ "Zobrazení obsazenosti ..." -->
    <p><strong>Kód kurzu:</strong> <?= h($course['code']) ?></p>
    <p><strong>Termín konání:</strong> <?= h($course['starts_at']) ?></p>
    <p>
        <strong>Obsazenost:</strong>
        <?= (int) $course['enrolled_count'] ?> / <?= (int) $course['capacity'] ?>
        <!-- Např. "3 / 5" - aktuální počet aktivních přihlášek z kapacity. -->
        (<?= (int) $course['free_count'] ?> volných míst)
        <?php if ($isFull): ?><span class="badge badge-cancelled">plno</span><?php endif; ?>
        <!-- Pokud je kurz plný, zvýrazní to červeným badge "plno". -->
    </p>
    <div class="capacity-bar">
        <!-- Vizuální progress bar obsazenosti - stejný princip jako
             v views/courses/index.php. -->
        <div class="capacity-bar-fill <?= $isFull ? 'full' : '' ?>" style="width: <?= $percent ?>%;"></div>
    </div>
</div>

<div class="card">
    <!-- Karta s formulářem pro přihlášení účastníka do kurzu.
         POŽADOVANÁ FUNKCE ZE ZADÁNÍ "Přihlášení účastníka do kurzu." -->
    <h2>Přihlásit účastníka</h2>
    <?php if ($isFull): ?>
        <!-- Pokud je kurz plný, formulář se nezobrazí vůbec (zbytečné
             odesílat požadavek, který by server stejně odmítl). -->
        <p class="muted">Kurz je plně obsazen, nelze přidat dalšího účastníka.</p>
    <?php elseif ($availableParticipants === []): ?>
        <!-- Pokud nejsou žádní účastníci, kteří by se ještě mohli
             přihlásit (všichni už mají aktivní přihlášku). -->
        <p class="muted">Všichni existující účastníci jsou již do tohoto kurzu přihlášeni. <a href="/participants/new">Vytvořit nového účastníka</a>.</p>
    <?php else: ?>
        <!-- Jinak zobrazí formulář s výběrem účastníka. -->
        <form method="post" action="/courses/<?= (int) $course['id'] ?>/enroll">
            <!-- POST na /courses/{id}/enroll - zpracovává
                 CourseController::enroll(). -->
            <div class="form-group">
                <label for="participant_id">Účastník</label>
                <select id="participant_id" name="participant_id" required>
                    <option value="">– vyberte účastníka –</option>
                    <!-- Prázdná volba jako výchozí - donutí uživatele
                         vybrat konkrétního účastníka. -->
                    <?php foreach ($availableParticipants as $p): ?>
                        <!-- Projde jen účastníky, kteří v tomto kurzu
                             ještě nemají aktivní přihlášku (to filtruje
                             ParticipantRepository::allNotActivelyEnrolledInCourse()). -->
                        <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Přihlásit</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <!-- Karta se seznamem všech přihlášek do tohoto kurzu. POŽADOVANÁ
         FUNKCE ZE ZADÁNÍ "Zobrazení ... seznamu přihlášených." -->
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
                <!-- Prázdný sloupec záhlaví pro akční tlačítka. -->
            </tr>
            </thead>
            <tbody>
            <?php foreach ($enrollments as $e): ?>
                <!-- Projde VŠECHNY přihlášky (ve všech stavech - enrolled,
                     cancelled, completed), ne jen aktivní. -->
                <tr>
                    <td><?= h($e['participant_name']) ?></td>
                    <td><?= h($e['participant_email']) ?></td>
                    <td><?= h($e['enrolled_at']) ?></td>
                    <td>
                        <span class="badge badge-<?= h($e['status']) ?>">
                            <!-- Třída badge se dynamicky složí ze stavu
                                 (badge-enrolled / badge-cancelled /
                                 badge-completed) - proto musí CSS mít
                                 definované přesně tyto tři varianty. -->
                            <?= h($statusLabels[$e['status']] ?? $e['status']) ?>
                            <!-- Vypíše český popisek stavu; kdyby náhodou
                                 přišel neznámý stav, vypíše aspoň
                                 technickou hodnotu jako záchrannou síť. -->
                        </span>
                    </td>
                    <td class="actions">
                        <?php if ($e['status'] === 'enrolled'): ?>
                            <!-- Tlačítka pro změnu stavu se zobrazí JEN
                                 u aktivních přihlášek - zrušenou nebo
                                 dokončenou už dál nejde měnit (viz
                                 EnrollmentRepository::transitionStatus()). -->
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
