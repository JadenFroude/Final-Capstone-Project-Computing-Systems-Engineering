<?php

require_once "db.php";

/*
|--------------------------------------------------------------------------
| Get and validate movie ID
|--------------------------------------------------------------------------
*/

$movieId = trim($_GET["id"] ?? "");

if (!preg_match('/^tt\d+$/', $movieId)) {
    http_response_code(400);
    die("Invalid movie ID.");
}

/*
|--------------------------------------------------------------------------
| Get main movie information
|--------------------------------------------------------------------------
*/

$movieSql = "
    SELECT
        m.tconst,
        m.primarytitle AS title,
        m.originaltitle AS original_title,
        m.startyear AS release_year,
        m.endyear AS end_year,
        m.runtimeminutes AS runtime,
        m.genres,
        m.titletype AS title_type,
        m.poster_url,
        r.averagerating AS rating,
        r.numvotes AS votes
    FROM movies m
    LEFT JOIN ratings r
        ON m.tconst = r.tconst
    WHERE m.tconst = :movie_id
    LIMIT 1
";

$movieStatement = $pdo->prepare($movieSql);

$movieStatement->execute([
    "movie_id" => $movieId
]);

$movie = $movieStatement->fetch();

if (!$movie) {
    http_response_code(404);
    die("Movie not found.");
}

/*
|--------------------------------------------------------------------------
| Get actors and actresses
|--------------------------------------------------------------------------
*/

$castSql = "
    SELECT
        pe.nconst,
        pe.primaryname AS name,
        pr.category,
        pr.characters,
        pr.ordering
    FROM principals pr
    INNER JOIN people pe
        ON pr.nconst = pe.nconst
    WHERE pr.tconst = :movie_id
      AND pr.category IN ('actor', 'actress', 'self')
    ORDER BY pr.ordering
    LIMIT 12
";

$castStatement = $pdo->prepare($castSql);

$castStatement->execute([
    "movie_id" => $movieId
]);

$cast = $castStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Get directors
|--------------------------------------------------------------------------
*/

$directorSql = "
    SELECT DISTINCT
        pe.nconst,
        pe.primaryname AS name
    FROM principals pr
    INNER JOIN people pe
        ON pr.nconst = pe.nconst
    WHERE pr.tconst = :movie_id
      AND pr.category = 'director'
    ORDER BY pe.primaryname
";

$directorStatement = $pdo->prepare($directorSql);

$directorStatement->execute([
    "movie_id" => $movieId
]);

$directors = $directorStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Get writers
|--------------------------------------------------------------------------
*/

$writerSql = "
    SELECT DISTINCT
        pe.nconst,
        pe.primaryname AS name,
        pr.job
    FROM principals pr
    INNER JOIN people pe
        ON pr.nconst = pe.nconst
    WHERE pr.tconst = :movie_id
      AND pr.category = 'writer'
    ORDER BY pe.primaryname
    LIMIT 10
";

$writerStatement = $pdo->prepare($writerSql);

$writerStatement->execute([
    "movie_id" => $movieId
]);

$writers = $writerStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function displayValue(?string $value, string $fallback = "Not available"): string
{
    if (
        $value === null ||
        trim($value) === "" ||
        $value === '\N'
    ) {
        return $fallback;
    }

    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatRuntime(?string $runtime): string
{
    if (
        $runtime === null ||
        $runtime === '\N' ||
        !ctype_digit($runtime)
    ) {
        return "Not available";
    }

    $minutes = (int) $runtime;
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours === 0) {
        return $minutes . " minutes";
    }

    if ($remainingMinutes === 0) {
        return $hours . "h";
    }

    return $hours . "h " . $remainingMinutes . "m";
}

function formatCharacterNames(?string $characters): string
{
    if (
        $characters === null ||
        trim($characters) === "" ||
        $characters === '\N'
    ) {
        return "";
    }

    $decoded = json_decode($characters, true);

    if (!is_array($decoded)) {
        return htmlspecialchars(
            trim($characters, '[]"'),
            ENT_QUOTES,
            "UTF-8"
        );
    }

    $decoded = array_map(
        fn($character) => htmlspecialchars(
            (string) $character,
            ENT_QUOTES,
            "UTF-8"
        ),
        $decoded
    );

    return implode(", ", $decoded);
}

/*
|--------------------------------------------------------------------------
| Build poster URL
|--------------------------------------------------------------------------
*/

$placeholderPoster = "images/poster-placeholder.jpg";
$posterUrl = $placeholderPoster;

$storedPoster = trim((string) ($movie["poster_url"] ?? ""));

if (
    $storedPoster !== "" &&
    $storedPoster !== '\N'
) {
    /*
     * Use complete URLs as stored.
     */
    if (filter_var($storedPoster, FILTER_VALIDATE_URL)) {
        $posterUrl = $storedPoster;
    }

    /*
     * Convert a TMDB poster path such as /abc123.jpg
     * into a complete TMDB image URL.
     */
    elseif (str_starts_with($storedPoster, "/")) {
        $posterUrl =
            "https://image.tmdb.org/t/p/w500" .
            $storedPoster;
    }
}

/*
|--------------------------------------------------------------------------
| Format genres
|--------------------------------------------------------------------------
*/

$genres = [];

if (
    !empty($movie["genres"]) &&
    $movie["genres"] !== '\N'
) {
    $genres = array_map(
        "trim",
        explode(",", $movie["genres"])
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($movie["title"]) ?> | CineMatch
    </title>

    <link rel="stylesheet" href="css/style.css?v=4">
</head>

<body>

<header class="navbar">

    <h1>
        <a href="index.php">🎬 CineMatch</a>
    </h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="index.php#movie-search">Search</a>
        <a href="index.php#algorithm">Algorithm</a>
        <a href="index.php#genres">Genres</a>
    </nav>

</header>

<main>

    <section class="details-page">

        <div class="details-card">

            <div class="details-poster-column">

                <img
    class="details-poster"
    src="<?= htmlspecialchars($posterUrl, ENT_QUOTES, "UTF-8") ?>"
    alt="<?= htmlspecialchars($movie["title"], ENT_QUOTES, "UTF-8") ?> poster"
    onerror="this.onerror=null; this.src='images/poster-placeholder.jpg';"
>

                <a
    class="imdb-button"
    href="https://www.imdb.com/title/<?= urlencode($movieId) ?>/"
    target="_blank"
    rel="noopener noreferrer"
>
    🎬 View on IMDb
</a>

            </div>

            <div class="details-info">

                <h2>
                    <?= htmlspecialchars($movie["title"]) ?>
                </h2>

                <?php if (
                    !empty($movie["original_title"]) &&
                    $movie["original_title"] !== $movie["title"]
                ): ?>

                    <p class="original-title">
                        Original title:
                        <?= htmlspecialchars($movie["original_title"]) ?>
                    </p>

                <?php endif; ?>

                <div class="movie-facts">

                    <span>
                        ⭐
                        <?= $movie["rating"] !== null
                            ? htmlspecialchars((string) $movie["rating"]) . "/10"
                            : "Not rated"
                        ?>
                    </span>

                    <span>
                        📅
                        <?= displayValue($movie["release_year"]) ?>
                    </span>

                    <span>
                        ⏱
                        <?= htmlspecialchars(formatRuntime($movie["runtime"])) ?>
                    </span>

                    <span>
                        🗳
                        <?= number_format((int) ($movie["votes"] ?? 0)) ?>
                        votes
                    </span>

                </div>

                <?php if (!empty($genres)): ?>

    <div class="genre-tags">

        <?php foreach ($genres as $genre): ?>

            <a
                class="genre-pill"
                href="search.php?genre=<?= urlencode(trim($genre)) ?>"
            >
                <?= htmlspecialchars(trim($genre)) ?>
            </a>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

                <div class="movie-information-grid">

    <div class="information-item">
        <span>📅 Release Year</span>
        <strong>
            <?= displayValue($movie["release_year"]) ?>
        </strong>
    </div>

    <div class="information-item">
        <span>⏱ Runtime</span>
        <strong>
            <?= htmlspecialchars(formatRuntime($movie["runtime"])) ?>
        </strong>
    </div>

    <div class="information-item">
        <span>⭐ IMDb Rating</span>
        <strong>
            <?= $movie["rating"] !== null
                ? htmlspecialchars((string) $movie["rating"]) . "/10"
                : "Not available"
            ?>
        </strong>
    </div>

    <div class="information-item">
        <span>🎬 IMDb ID</span>
        <strong>
            <?= htmlspecialchars($movie["tconst"]) ?>
        </strong>
    </div>

</div>

                <div class="crew-section">

                    <h3>Director</h3>

                    <?php if (empty($directors)): ?>

                        <p>Director information is not available.</p>

                    <?php else: ?>

                        <p>
                            <?php foreach ($directors as $index => $director): ?>

                                <?= $index > 0 ? ", " : "" ?>

                                <?= htmlspecialchars($director["name"]) ?>

                            <?php endforeach; ?>
                        </p>

                    <?php endif; ?>

                </div>

                <div class="crew-section">

                    <h3>Writers</h3>

                    <?php if (empty($writers)): ?>

                        <p>Writer information is not available.</p>

                    <?php else: ?>

                        <ul class="crew-list">

                            <?php foreach ($writers as $writer): ?>

                                <li>
                                    <strong>
                                        <?= htmlspecialchars($writer["name"]) ?>
                                    </strong>

                                    <?php if (
                                        !empty($writer["job"]) &&
                                        $writer["job"] !== '\N'
                                    ): ?>

                                        <span>
                                            <?= htmlspecialchars($writer["job"]) ?>
                                        </span>

                                    <?php endif; ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

                <div class="details-buttons">

                    <a
                        class="secondary-details-button"
                        href="javascript:history.back()"
                    >
                        ← Back
                    </a>

                    <button
                        type="button"
                        class="favorite-button"
                        id="favorite-button"
                        data-movie-id="<?= htmlspecialchars($movieId) ?>"
                    >
                        ♡ Add to Favorites
                    </button>

                </div>

            </div>

        </div>

    </section>

    <section class="cast-section">

        <div class="section-heading">

            <h2>Cast</h2>

            <p>
                Principal cast members listed in the IMDb dataset.
            </p>

        </div>

        <?php if (empty($cast)): ?>

            <div class="empty-information">
                Cast information is not available for this title.
            </div>

        <?php else: ?>

            <div class="cast-grid">

                <?php foreach ($cast as $person): ?>

                    <?php
                    $characters = formatCharacterNames(
                        $person["characters"] ?? null
                    );
                    ?>

                    <article class="cast-card">

                        <div class="cast-avatar">
                            <?= htmlspecialchars(
                                strtoupper(
                                    substr($person["name"], 0, 1)
                                )
                            ) ?>
                        </div>

                        <div>

                            <h3>
                                <?= htmlspecialchars($person["name"]) ?>
                            </h3>

                            <?php if ($characters !== ""): ?>

                                <p>
                                    as <?= htmlspecialchars($characters) ?>
                                </p>

                            <?php else: ?>

                                <p>
                                    <?= htmlspecialchars(
                                        ucfirst($person["category"])
                                    ) ?>
                                </p>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <section class="recommendation-box">

        <h2>Why might this movie match?</h2>

        <div class="reason-grid">

            <div>
                <strong>⭐ Audience rating</strong>

                <p>
                    This title has an IMDb rating of
                    <?= $movie["rating"] !== null
                        ? htmlspecialchars((string) $movie["rating"])
                        : "N/A"
                    ?>.
                </p>
            </div>

            <div>
                <strong>🗳 Popularity</strong>

                <p>
                    It has received
                    <?= number_format((int) ($movie["votes"] ?? 0)) ?>
                    IMDb votes.
                </p>
            </div>

            <div>
                <strong>🎭 Genres</strong>

                <p>
                    <?= displayValue(
                        $movie["genres"],
                        "Genre information is unavailable."
                    ) ?>
                </p>
            </div>

            <div>
                <strong>⏱ Runtime</strong>

                <p>
                    The movie runs for
                    <?= htmlspecialchars(formatRuntime($movie["runtime"])) ?>.
                </p>
            </div>

        </div>

    </section>

</main>

<footer>

    <p>
        © 2026 CineMatch |
        Database-Driven Movie Recommendation System
    </p>

</footer>

<script>
const favoriteButton = document.getElementById("favorite-button");

favoriteButton.addEventListener("click", () => {
    const movieId = favoriteButton.dataset.movieId;
    const storageKey = "cinematchFavorites";

    let favorites = JSON.parse(
        localStorage.getItem(storageKey) || "[]"
    );

    if (favorites.includes(movieId)) {
        favorites = favorites.filter(id => id !== movieId);
        favoriteButton.textContent = "♡ Add to Favorites";
    } else {
        favorites.push(movieId);
        favoriteButton.textContent = "♥ Added to Favorites";
    }

    localStorage.setItem(
        storageKey,
        JSON.stringify(favorites)
    );
});

const savedFavorites = JSON.parse(
    localStorage.getItem("cinematchFavorites") || "[]"
);

if (savedFavorites.includes(favoriteButton.dataset.movieId)) {
    favoriteButton.textContent = "♥ Added to Favorites";
}
</script>

</body>

</html>