<!DOCTYPE html>
<!-- Deklaruje, že jde o dokument HTML5 - bez toho by prohlížeč mohl
     stránku vykreslit v tzv. "quirks mode" (nestandardně). -->
<html lang="cs">
<!-- Kořenový element stránky; lang="cs" říká prohlížeči/čtečkám,
     že obsah je v češtině (lepší čtečky obrazovky, SEO). -->
<head>
    <meta charset="UTF-8">
    <!-- Nastaví kódování znaků na UTF-8, aby se správně zobrazovaly
         české znaky s diakritikou (ě, š, č, ř...). -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Zajistí správné zobrazení a měřítko na mobilních zařízeních. -->
    <title><?= h($title ?? 'Kurzy') ?> – Kurzy a přihlašování účastníků</title>
    <!-- Titulek stránky v záložce prohlížeče; h() escapuje hodnotu
         $title, kdyby náhodou obsahovala speciální HTML znaky. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <!-- Prohlížeč se "předpřipojí" na Google Fonts dřív, než font
         skutečně potřebuje stáhnout - zrychlí načítání. -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <!-- Načte font "Inter" v několika tloušťkách (400 až 800), který
         používáme v public/assets/style.css. -->
    <link rel="stylesheet" href="/assets/style.css">
    <!-- Načte náš vlastní soubor se styly aplikace. -->
</head>
<body>
<!-- Začátek viditelného obsahu stránky. -->
<header class="app-header">
    <!-- Horní lišta se jménem aplikace a navigací. -->
    <a class="brand" href="/courses">Kurzy a přihlašování účastníků</a>
    <!-- Logo/název aplikace - zároveň odkaz na úvodní seznam kurzů. -->
    <nav class="app-nav">
        <!-- Hlavní navigace mezi dvěma částmi aplikace. -->
        <a href="/courses">Kurzy</a>
        <a href="/participants">Účastníci</a>
    </nav>
</header>
<main>
    <!-- Hlavní obsahová část, do které se vkládá konkrétní stránka. -->
    <?php $success = flash('success'); ?>
    <!-- Přečte (a smaže) uloženou úspěšnou flash zprávu, pokud nějaká
         čeká po přesměrování (např. "Kurz byl vytvořen."). -->
    <?php if ($success !== null): ?>
        <!-- Pokud nějaká úspěšná zpráva čekala. -->
        <div class="alert alert-success"><?= h($success) ?></div>
        <!-- Vypíše ji v zeleném boxu (escapovanou přes h()). -->
    <?php endif; ?>

    <?php $error = flash('error'); ?>
    <!-- Totéž pro chybovou flash zprávu (např. "Kurz je plně obsazen."). -->
    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?= $content ?>
    <!-- Vloží obsah konkrétní stránky, který připravila metoda
         App\View::render() (proměnná $content vzniká v ní). Zde se
         VĚDOMĚ nepoužívá h(), protože $content je už hotové HTML
         vygenerované našimi důvěryhodnými šablonami, ne syrový
         uživatelský vstup. -->
</main>
</body>
</html>
