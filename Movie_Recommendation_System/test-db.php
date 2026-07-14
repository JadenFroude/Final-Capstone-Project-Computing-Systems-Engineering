<?php

require_once "db.php";

$sql = "
    SELECT
        m.tconst,
        m.primarytitle AS title,
        m.startyear AS release_year,
        m.runtimeminutes AS runtime,
        m.genres,
        r.averagerating AS rating,
        r.numvotes AS votes
    FROM movies m
    INNER JOIN ratings r
        ON m.tconst = r.tconst
    WHERE m.titletype = 'movie'
      AND m.isadult = 0
      AND r.numvotes >= 100000
    ORDER BY r.averagerating DESC, r.numvotes DESC
    LIMIT 20
";

$statement = $pdo->query($sql);
$movies = $statement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CineMatch Database Test</title>
</head>
<body>

<h1>IMDb Database Test</h1>

<?php foreach ($movies as $movie): ?>
    <div>
        <strong>
            <?= htmlspecialchars($movie["title"]) ?>
        </strong>

        — <?= htmlspecialchars((string) $movie["release_year"]) ?>

        — Rating:
        <?= htmlspecialchars((string) $movie["rating"]) ?>

        — Votes:
        <?= htmlspecialchars((string) $movie["votes"]) ?>
    </div>
<?php endforeach; ?>

</body>
</html>