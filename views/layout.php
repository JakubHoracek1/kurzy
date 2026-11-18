<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Kurzy') ?> – Kurzy a přihlašování účastníků</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="app-header">
    <a class="brand" href="/courses">Kurzy a přihlašování účastníků</a>
    <nav class="app-nav">
        <a href="/courses">Kurzy</a>
        <a href="/participants">Účastníci</a>
    </nav>
</header>
<main>
    <?php $success = flash('success'); ?>
    <?php if ($success !== null): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php $error = flash('error'); ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?= $content ?>
</main>
</body>
</html>
