<div class="page-header">
    <!-- Řádek s nadpisem stránky a tlačítkem pro vytvoření nového kurzu. -->
    <h1>Kurzy</h1>
    <a class="btn btn-primary" href="/courses/new">+ Nový kurz</a>
    <!-- Odkaz vypadající jako tlačítko - vede na formulář nového kurzu. -->
</div>

<form method="get" action="/courses" class="filters card">
    <!-- Formulář filtru - metoda GET, aby se filtr dal poslat kamarádovi
         jako odkaz (parametry jsou vidět v URL) a dal se i Zpětně
         procházet v historii prohlížeče. POŽADOVANÁ FUNKCE ZE ZADÁNÍ
         "Filtr kurzů podle termínu a volných míst." -->
    <div class="form-group">
        <label for="from">Termín od</label>
        <!-- "for" propojuje popisek s inputem stejného "id" - usnadňuje
             kliknutí na popisek i přístupnost (čtečky obrazovky). -->
        <input type="date" id="from" name="from" value="<?= h($filters['from']) ?>">
        <!-- Date input - prohlížeč sám nabídne kalendář. "value" se
             naplní aktuálně zadaným filtrem (po odeslání formuláře),
             aby uživatel viděl, co má nastaveno. -->
    </div>
    <div class="form-group">
        <label for="to">Termín do</label>
        <input type="date" id="to" name="to" value="<?= h($filters['to']) ?>">
    </div>
    <div class="form-group checkbox-row">
        <!-- checkbox-row = speciální CSS třída, aby checkbox a popisek
             byly vedle sebe, ne nad sebou. -->
        <input type="checkbox" id="only_free" name="only_free" value="1" <?= $filters['only_free'] ? 'checked' : '' ?>>
        <!-- Pokud byl filtr "jen volná místa" aktivní, vypíše se
             atribut "checked", aby checkbox zůstal po odeslání zaškrtlý. -->
        <label for="only_free" style="margin:0;">Jen kurzy s volným místem</label>
    </div>
    <div class="form-group" style="min-width:0;">
        <button class="btn btn-primary" type="submit">Filtrovat</button>
        <!-- Odešle formulář metodou GET na stejnou adresu /courses. -->
        <a class="btn" href="/courses">Zrušit filtr</a>
        <!-- Odkaz bez parametrů = vrátí se na nefiltrovaný seznam. -->
    </div>
</form>

<?php if ($courses === []): ?>
    <!-- Pokud je výsledné (případně filtrem omezené) pole kurzů prázdné. -->
    <div class="card empty-state">Žádné kurzy neodpovídají zadanému filtru.</div>
<?php else: ?>
    <!-- Jinak vypíšeme kartičky - kurzy zobrazujeme jako katalog
         (kartičkovou mřížku), ne jako nudnou tabulku, aby to působilo
         jako skutečná "nabídka kurzů" ze zadání. -->
    <div class="course-grid">
        <!-- course-grid = CSS grid, který kartičky sám rozmístí do
             sloupců podle šířky obrazovky. -->
        <?php foreach ($courses as $course): ?>
            <?php $isFull = $course['free_count'] <= 0; ?>
            <!-- Pomocná proměnná - true, pokud kurz nemá žádné volné místo. -->
            <?php $percent = $course['capacity'] > 0
                ? min(100, (int) round($course['enrolled_count'] / $course['capacity'] * 100))
                : 0; ?>
            <!-- Spočítá procento obsazenosti (pro šířku progress baru);
                 min(100, ...) pojistí, že to nikdy nepřeteče přes 100 %. -->
            <div class="course-card">
                <!-- Jedna kartička = jeden kurz. -->
                <div class="course-card__top">
                    <!-- Horní řádek: název kurzu (klikatelný na detail) a kód. -->
                    <a class="course-card__name" href="/courses/<?= (int) $course['id'] ?>">
                        <?= h($course['name']) ?>
                    </a>
                    <!-- (int) přetypování ID zabrání vložení čehokoliv
                         jiného než čísla do URL; h() escapuje název kurzu. -->
                    <span class="course-card__code"><?= h($course['code']) ?></span>
                    <!-- Kód kurzu zobrazený jako malá "pilulka". -->
                </div>

                <div class="course-card__meta">
                    <!-- Řádek s datem a časem konání kurzu. -->
                    <span>📅</span>
                    <!-- Jednoduchá emoji ikonka kalendáře - bez potřeby
                         stahovat další ikonkový font. -->
                    <span><?= h($course['starts_at']) ?></span>
                </div>

                <div>
                    <!-- Blok s textovým vyjádřením obsazenosti a progress barem. -->
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                        <!-- Inline styl jen pro rychlé zarovnání dvou
                             prvků do jednoho řádku (text vlevo, badge vpravo). -->
                        <span class="muted">
                            <?= (int) $course['enrolled_count'] ?> / <?= (int) $course['capacity'] ?> obsazeno
                        </span>
                        <!-- Např. "2 / 5 obsazeno". -->
                        <?php if ($isFull): ?>
                            <span class="badge badge-cancelled">plno</span>
                            <!-- Pokud je kurz plný, červený badge "plno". -->
                        <?php else: ?>
                            <span class="badge badge-enrolled"><?= (int) $course['free_count'] ?> volných</span>
                            <!-- Jinak zelený badge s počtem volných míst. -->
                        <?php endif; ?>
                    </div>
                    <div class="capacity-bar">
                        <!-- Šedý "žlab" progress baru. -->
                        <div class="capacity-bar-fill <?= $isFull ? 'full' : '' ?>" style="width: <?= $percent ?>%;"></div>
                        <!-- Vyplnění baru - šířka v procentech nastavená
                             přímo inline stylem podle spočítané obsazenosti;
                             třída "full" (pokud je plno) přebarví fill na
                             červeno přes CSS. -->
                    </div>
                </div>

                <div class="course-card__footer">
                    <!-- Spodní řádek s akčními tlačítky, přilepený vždy
                         dolů (viz CSS "margin-top: auto"). -->
                    <a class="btn btn-sm" href="/courses/<?= (int) $course['id'] ?>">Detail</a>
                    <a class="btn btn-sm" href="/courses/<?= (int) $course['id'] ?>/edit">Upravit</a>
                    <form class="inline" method="post" action="/courses/<?= (int) $course['id'] ?>/delete"
                          onsubmit="return confirm('Opravdu smazat kurz včetně všech přihlášek?');">
                        <!-- HTML formuláře umí jen GET/POST, proto je smazání
                             přes POST formulář s jedním tlačítkem, ne přes
                             obyčejný odkaz. onsubmit s confirm() se zeptá
                             na potvrzení, než se formulář doopravdy odešle -
                             vrátí-li confirm() false, odeslání se zastaví. -->
                        <button class="btn btn-sm btn-danger" type="submit">Smazat</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
