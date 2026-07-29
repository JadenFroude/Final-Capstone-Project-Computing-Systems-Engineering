<?php

require_once "db.php";
require_once "tmdb.php";

set_time_limit(300);

$batchSize = 25;
$minimumVotes = 1000;

/*
|--------------------------------------------------------------------------
| Select the next batch
|--------------------------------------------------------------------------
|
| Only select:
| - actual movies
| - non-adult titles
| - movies with enough IMDb votes
| - rows that have never been checked
|
*/

$sql = "
    SELECT
        m.tconst,
        m.primarytitle AS title,
        r.averagerating,
        r.numvotes
    FROM movies m
    INNER JOIN ratings r
        ON m.tconst = r.tconst
    WHERE m.titletype = 'movie'
      AND m.isadult = 0
      AND m.poster_url IS NULL
      AND r.numvotes >= :minimum_votes
    ORDER BY
        (r.averagerating * LN(r.numvotes + 1)) DESC
    LIMIT :batch_size
";

$movieStatement = $pdo->prepare($sql);

$movieStatement->bindValue(
    ":minimum_votes",
    $minimumVotes,
    PDO::PARAM_INT
);

$movieStatement->bindValue(
    ":batch_size",
    $batchSize,
    PDO::PARAM_INT
);

$movieStatement->execute();

$movies = $movieStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Prepare update statements
|--------------------------------------------------------------------------
*/

$updatePosterStatement = $pdo->prepare("
    UPDATE movies
    SET poster_url = :poster_url
    WHERE tconst = :tconst
");

$markMissingStatement = $pdo->prepare("
    UPDATE movies
    SET poster_url = 'POSTER_NOT_FOUND'
    WHERE tconst = :tconst
");

$updated = 0;
$notFound = 0;
$errors = 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Update Movie Posters</title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            font-family: Arial, sans-serif;
            background: #050816;
            color: white;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .result {
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 10px;
            background: #111827;
        }

        .updated {
            border-left: 4px solid #22c55e;
        }

        .missing {
            border-left: 4px solid #facc15;
        }

        .error {
            border-left: 4px solid #ef4444;
        }

        .summary {
            margin-top: 30px;
            padding: 24px;
            border-radius: 16px;
            background: #1e293b;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        a {
            display: inline-block;
            padding: 14px 22px;
            border-radius: 999px;
            background: #38bdf8;
            color: #020617;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Updating Popular Movie Posters</h1>

    <p>
        Processing up to <?= $batchSize ?> movies with at least
        <?= number_format($minimumVotes) ?> IMDb votes.
    </p>

    <?php if (count($movies) === 0): ?>

        <div class="summary">

            <h2>No movies are waiting for poster updates.</h2>

            <p>
                All qualifying movies have either received a poster or
                have already been marked as unavailable.
            </p>

            <div class="button-row">

                <a href="index.php">
                    Return to Homepage
                </a>

            </div>

        </div>

    <?php else: ?>

        <?php foreach ($movies as $movie): ?>

            <?php

            $imdbId = $movie["tconst"];
            $title = $movie["title"] ?? $imdbId;

            try {
                $posterUrl = getMoviePoster($imdbId);

                if (
                    $posterUrl !== null &&
                    filter_var($posterUrl, FILTER_VALIDATE_URL)
                ) {
                    $updatePosterStatement->execute([
                        "poster_url" => $posterUrl,
                        "tconst" => $imdbId
                    ]);

                    $updated++;

                    echo '<div class="result updated">';
                    echo 'Updated: ';
                    echo htmlspecialchars(
                        $title,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    echo ' (' . htmlspecialchars(
                        $imdbId,
                        ENT_QUOTES,
                        "UTF-8"
                    ) . ')';
                    echo '</div>';
                } else {
                    /*
                     * Mark the movie so it will not be selected again
                     * during the next batch.
                     */
                    $markMissingStatement->execute([
                        "tconst" => $imdbId
                    ]);

                    $notFound++;

                    echo '<div class="result missing">';
                    echo 'No poster found: ';
                    echo htmlspecialchars(
                        $title,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    echo ' (' . htmlspecialchars(
                        $imdbId,
                        ENT_QUOTES,
                        "UTF-8"
                    ) . ')';
                    echo '</div>';
                }
            } catch (Throwable $error) {
                $errors++;

                echo '<div class="result error">';
                echo 'Error: ';
                echo htmlspecialchars(
                    $title,
                    ENT_QUOTES,
                    "UTF-8"
                );
                echo ' (' . htmlspecialchars(
                    $imdbId,
                    ENT_QUOTES,
                    "UTF-8"
                ) . ') — ';
                echo htmlspecialchars(
                    $error->getMessage(),
                    ENT_QUOTES,
                    "UTF-8"
                );
                echo '</div>';
            }

            /*
             * Brief delay between TMDB requests.
             */
            usleep(200000);

            ?>

        <?php endforeach; ?>

        <div class="summary">

            <h2>Batch complete</h2>

            <p>
                Updated: <?= $updated ?>
            </p>

            <p>
                No poster found: <?= $notFound ?>
            </p>

            <p>
                Errors: <?= $errors ?>
            </p>

            <div class="button-row">

                <a href="update_posters.php">
                    Run Next Batch
                </a>

                <a href="index.php">
                    Return to Homepage
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>