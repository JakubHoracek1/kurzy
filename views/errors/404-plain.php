<!DOCTYPE html>
<!-- Tato "plain" (holá) 404 stránka se používá JEN přímo z routeru
     v public/index.php, když vůbec žádná routa nesedne (neplatná
     adresa) - v tu chvíli ještě nemusí být připravené proměnné pro
     plný layout, proto je tahle stránka úplně samostatná, bez
     závislosti na views/layout.php. -->
<html lang="cs">
<head><meta charset="UTF-8"><title>404 – Nenalezeno</title></head>
<body>
<h1>404 – Stránka nenalezena</h1>
<p><a href="/courses">Zpět na kurzy</a></p>
</body>
</html>
