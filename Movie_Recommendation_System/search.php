<?php

require_once "db.php";

/* -----------------------------
   Read search form values
----------------------------- */

$title = trim($_GET["title"] ?? "");
$actor = trim($_GET["actor"] ?? "");
$genre = trim($_GET["genre"] ?? "");

$year = filter_input(INPUT_GET, "year", FILTER_VALIDATE_INT);
$decade = filter_input(INPUT_GET, "decade", FILTER_VALIDATE_INT);
$minimumRating = filter_input(INPUT_GET, "rating", FILTER_VALIDATE_FLOAT);
$minimumVotes = filter_input(INPUT_GET, "votes", FILTER_VALIDATE_INT);
$runtime = filter_input(INPUT_GET, "runtime", FILTER_VALIDATE_INT);

$genreWeight = filter_input(INPUT_GET, "genre_weight", FILTER_VALIDATE_INT);
$actorWeight = filter_input(INPUT_GET, "actor_weight", FILTER_VALIDATE_INT);
$ratingWeight = filter_input(INPUT_GET, "rating_weight", FILTER_VALIDATE_INT);
$yearWeight = filter_input(INPUT_GET, "year_weight", FILTER_VALIDATE_INT);
$runtimeWeight = filter_input(INPUT_GET, "runtime_weight", FILTER_VALIDATE_INT);

/* -----------------------------
   Set safe default values
----------------------------- */

$year = ($year !== false && $year !== null) ? $year : 0;
$decade = ($decade !== false && $decade !== null) ? $decade : 0;
$minimumRating = ($minimumRating !== false && $minimumRating !== null)
    ? $minimumRating
    : 0;
$minimumVotes = ($minimumVotes !== false && $minimumVotes !== null)
    ? max(0, $minimumVotes)
    : 0;
$runtime = ($runtime !== false && $runtime !== null) ? $runtime : 0;

/*
The sliders arrive as whole percentages such as 80.
They are converted to values between 0.00 and 1.00.
*/
$genreWeight = max(0, min(100, $genreWeight ?? 80)) / 100;
$actorWeight = max(0, min(100, $actorWeight ?? 90)) / 100;
$ratingWeight = max(0, min(100, $ratingWeight ?? 70)) / 100;
$yearWeight = max(0, min(100, $yearWeight ?? 40)) / 100;
$runtimeWeight = max(0, min(100, $runtimeWeight ?? 30)) / 100;

/*
Maximum score possible for the user's selected preferences.
Used to convert the recommendation score into a percentage.
*/
$maximumRecommendationScore = $ratingWeight;

if ($genre !== "") {
    $maximumRecommendationScore += $genreWeight;
}

if ($actor !== "") {
    $maximumRecommendationScore += $actorWeight;
}

if ($year > 0) {
    $maximumRecommendationScore += $yearWeight;
}

if ($runtime > 0) {
    $maximumRecommendationScore += $runtimeWeight;
}

/* -----------------------------
   Validate sorting option
----------------------------- */

$allowedSorts = [
    "popular",
    "rating_high",
    "rating_low",
    "newest",
    "oldest",
    "runtime_short",
    "runtime_long"
];

$sort = $_GET["sort"] ?? "popular";

if (!in_array($sort, $allowedSorts, true)) {
    $sort = "popular";
}

/* -----------------------------
   Choose SQL sorting
----------------------------- */

switch ($sort) {
    case "rating_high":
        $orderBy = "
            r.averagerating DESC NULLS LAST,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "rating_low":
        $orderBy = "
            r.averagerating ASC NULLS LAST,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "newest":
        $orderBy = "
            CASE
                WHEN m.startyear ~ '^[0-9]{4}$'
                THEN CAST(m.startyear AS INTEGER)
            END DESC NULLS LAST,
            recommendation_score DESC,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "oldest":
        $orderBy = "
            CASE
                WHEN m.startyear ~ '^[0-9]{4}$'
                THEN CAST(m.startyear AS INTEGER)
            END ASC NULLS LAST,
            recommendation_score DESC,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "runtime_short":
        $orderBy = "
            CASE
                WHEN m.runtimeminutes ~ '^[0-9]+$'
                THEN CAST(m.runtimeminutes AS INTEGER)
            END ASC NULLS LAST,
            recommendation_score DESC,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "runtime_long":
        $orderBy = "
            CASE
                WHEN m.runtimeminutes ~ '^[0-9]+$'
                THEN CAST(m.runtimeminutes AS INTEGER)
            END DESC NULLS LAST,
            recommendation_score DESC,
            r.numvotes DESC NULLS LAST
        ";
        break;

    case "popular":
    default:
        /*
        This is the recommendation order.
        The slider-based score is used first and vote count breaks ties.
        */
        $orderBy = "
            recommendation_score DESC,
            r.numvotes DESC NULLS LAST,
            r.averagerating DESC NULLS LAST
        ";
        break;
}

/* -----------------------------
   Build movie recommendation query
----------------------------- */

$sql = "
WITH user_input AS (
    SELECT
        CAST(:title AS TEXT) AS title,
        CAST(:actor AS TEXT) AS actor,
        CAST(:genre AS TEXT) AS genre,
        CAST(:preferred_year AS INTEGER) AS preferred_year,
        CAST(:preferred_decade AS INTEGER) AS preferred_decade,
        CAST(:minimum_rating AS NUMERIC) AS minimum_rating,
        CAST(:minimum_votes AS INTEGER) AS minimum_votes,
        CAST(:preferred_runtime AS INTEGER) AS preferred_runtime,
        CAST(:genre_weight AS NUMERIC) AS genre_weight,
        CAST(:actor_weight AS NUMERIC) AS actor_weight,
        CAST(:rating_weight AS NUMERIC) AS rating_weight,
        CAST(:year_weight AS NUMERIC) AS year_weight,
        CAST(:runtime_weight AS NUMERIC) AS runtime_weight
)

SELECT
    m.tconst,
    m.primarytitle AS title,
    m.startyear AS release_year,
    m.runtimeminutes AS runtime,
    m.genres,
    m.poster_url,
    r.averagerating AS rating,
    r.numvotes AS votes,

    ROUND(
        (
            /* Genre match: full genre points when selected genre appears */
            ui.genre_weight *
            CASE
                WHEN ui.genre = '' THEN 0
                WHEN m.genres ILIKE '%' || ui.genre || '%' THEN 1
                ELSE 0
            END

            +

            /* Actor match: full actor points when the actor is in the cast */
            ui.actor_weight *
            CASE
                WHEN ui.actor = '' THEN 0
                WHEN EXISTS (
                    SELECT 1
                    FROM principals pr_score
                    INNER JOIN people p_score
                        ON p_score.nconst = pr_score.nconst
                    WHERE pr_score.tconst = m.tconst
                      AND pr_score.category IN ('actor', 'actress')
                      AND p_score.primaryname ILIKE '%' || ui.actor || '%'
                ) THEN 1
                ELSE 0
            END

            +

            /* Rating score: 10/10 receives the full rating weight */
            ui.rating_weight *
            COALESCE(r.averagerating / 10.0, 0)

            +

            /*
            Year score:
            - Preferred year takes priority.
            - Otherwise, a selected decade uses the decade midpoint.
            - The score gradually falls over a 30-year distance.
            */
            ui.year_weight *
            CASE
                WHEN ui.preferred_year = 0
                     AND ui.preferred_decade = 0
                    THEN 0

                WHEN m.startyear !~ '^[0-9]{4}$'
                    THEN 0

                ELSE GREATEST(
                    0,
                    1 - (
                        ABS(
                            CAST(m.startyear AS INTEGER)
                            -
                            CASE
                                WHEN ui.preferred_year > 0
                                    THEN ui.preferred_year
                                ELSE ui.preferred_decade + 5
                            END
                        ) / 30.0
                    )
                )
            END

            +

            /*
            Runtime score:
            An exact runtime receives full points.
            The score gradually falls over a 120-minute difference.
            */
            ui.runtime_weight *
            CASE
                WHEN ui.preferred_runtime = 0 THEN 0
                WHEN m.runtimeminutes !~ '^[0-9]+$' THEN 0
                ELSE GREATEST(
                    0,
                    1 - (
                        ABS(
                            CAST(m.runtimeminutes AS INTEGER)
                            - ui.preferred_runtime
                        ) / 120.0
                    )
                )
            END
        )::NUMERIC,
        3
    ) AS recommendation_score

FROM movies m

CROSS JOIN user_input ui

LEFT JOIN ratings r
    ON m.tconst = r.tconst

WHERE m.titletype = 'movie'
  AND m.isadult = 0

  /* Movie title stays a direct search filter */
  AND (
      ui.title = ''
      OR m.primarytitle ILIKE '%' || ui.title || '%'
  )

  /* Minimum rating stays a hard minimum */
  AND (
      ui.minimum_rating = 0
      OR r.averagerating >= ui.minimum_rating
  )

  /* Minimum votes now uses the selected dropdown value */
  AND COALESCE(r.numvotes, 0) >= ui.minimum_votes

/*
Genre and actor are intentionally not hard filters.
They add recommendation points so their sliders can affect the ranking.
*/

ORDER BY
    $orderBy

LIMIT 50
";

/* -----------------------------
   Run query
----------------------------- */

$statement = $pdo->prepare($sql);

$statement->execute([
    "title" => $title,
    "actor" => $actor,
    "genre" => $genre,
    "preferred_year" => $year,
    "preferred_decade" => $decade,
    "minimum_rating" => $minimumRating,
    "minimum_votes" => $minimumVotes,
    "preferred_runtime" => $runtime,
    "genre_weight" => $genreWeight,
    "actor_weight" => $actorWeight,
    "rating_weight" => $ratingWeight,
    "year_weight" => $yearWeight,
    "runtime_weight" => $runtimeWeight
]);

$movies = $statement->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Search Results | CineMatch</title>

    <link rel="stylesheet" href="css/style.css?v=10">
</head>

<body>

<header class="navbar">
    <h1>🎬 CineMatch</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="search.php">Search</a>
        <a href="#">Genres</a>
        <a href="#">About</a>
    </nav>
</header>

<section class="results-hero">
    <h2>Search Results</h2>

    <p>
        Movies ranked using your filters and custom recommendation weights.
    </p>
</section>

<section class="results-layout">

    <aside class="filter-panel">
        <h3>Your Search</h3>

        <p>
            <strong>Title:</strong>
            <?= htmlspecialchars($title !== "" ? $title : "Any") ?>
        </p>

        <p>
            <strong>Actor:</strong>
            <?= htmlspecialchars($actor !== "" ? $actor : "Any") ?>
        </p>

        <p>
            <strong>Genre:</strong>
            <?= htmlspecialchars($genre !== "" ? $genre : "Any") ?>
        </p>

        <p>
            <strong>Preferred year:</strong>
            <?= $year > 0 ? htmlspecialchars((string) $year) : "Any" ?>
        </p>

        <p>
            <strong>Preferred decade:</strong>
            <?= $decade > 0
                ? htmlspecialchars((string) $decade) . "s"
                : "Any"
            ?>
        </p>

        <p>
            <strong>Minimum rating:</strong>
            <?= $minimumRating > 0
                ? htmlspecialchars((string) $minimumRating) . "+"
                : "Any"
            ?>
        </p>

        <p>
            <strong>Minimum votes:</strong>
            <?= $minimumVotes > 0
                ? number_format($minimumVotes) . "+"
                : "Any"
            ?>
        </p>

        <p>
            <strong>Preferred runtime:</strong>
            <?= $runtime > 0
                ? htmlspecialchars((string) $runtime) . " minutes"
                : "Any"
            ?>
        </p>

        <a class="new-search-button" href="index.php">
            Start New Search
        </a>
    </aside>

    <main class="results-content">

        <div class="results-header">
            <h2>Recommended Movies</h2>

            <p>
                <?= count($movies) ?> result(s) found
            </p>
        </div>

        <div class="results-grid">

            <?php if (count($movies) === 0): ?>

                <div class="no-results">
                    <h3>No movies found</h3>

                    <p>
                        Try lowering the minimum rating or minimum vote count.
                    </p>
                </div>

            <?php else: ?>

                <?php foreach ($movies as $movie): ?>

                    <?php
                    $storedPoster = trim(
                        (string) ($movie["poster_url"] ?? "")
                    );

                    $rawRecommendationScore =
    (float) ($movie["recommendation_score"] ?? 0);

$matchPercentage = $maximumRecommendationScore > 0
    ? ($rawRecommendationScore / $maximumRecommendationScore) * 100
    : 0;

$matchPercentage = (int) round(
    max(0, min(100, $matchPercentage))
);


                    $posterUrl = "images/poster-placeholder.jpg";

                    if (
                        $storedPoster !== "" &&
                        $storedPoster !== '\N' &&
                        $storedPoster !== "POSTER_NOT_FOUND"
                    ) {
                        if (
                            filter_var(
                                $storedPoster,
                                FILTER_VALIDATE_URL
                            )
                        ) {
                            $posterUrl = $storedPoster;
                        } elseif (str_starts_with($storedPoster, "/")) {
                            $posterUrl =
                                "https://image.tmdb.org/t/p/w500" .
                                $storedPoster;
                        }
                    }
                    ?>

                    <article class="result-card">

                        <div class="result-poster">
                            <img
                                src="<?= htmlspecialchars($posterUrl) ?>"
                                alt="<?= htmlspecialchars(
                                    $movie["title"]
                                ) ?> poster"
                                onerror="this.onerror=null; this.src='images/poster-placeholder.jpg';"
                            >
                        </div>

                        <div class="result-information">

                            <h3>
                                <?= htmlspecialchars($movie["title"]) ?>
                            </h3>

                            <p class="result-meta">
                                ⭐ <?= htmlspecialchars(
                                    (string) (
                                        $movie["rating"] ?? "N/A"
                                    )
                                ) ?>
                                •
                                <?= htmlspecialchars(
                                    (string) (
                                        $movie["release_year"] ?? "Unknown"
                                    )
                                ) ?>
                                •
                                <?= htmlspecialchars(
                                    $movie["genres"] ?? "Unknown genre"
                                ) ?>
                            </p>

                            <p>
                                Runtime:
                                <?= htmlspecialchars(
                                    (string) (
                                        $movie["runtime"] ?? "Unknown"
                                    )
                                ) ?>
                                minutes
                            </p>

                            <p>
                                Votes:
                                <?= number_format(
                                    (int) ($movie["votes"] ?? 0)
                                ) ?>
                            </p>

                            <p class="recommendation-score">
    🎯 Match: <?= $matchPercentage ?>%
</p>

                            <a
                                href="movie.php?id=<?= urlencode(
                                    $movie["tconst"]
                                ) ?>"
                            >
                                View Details
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</section>

<footer>
    <p>
        © 2026 CineMatch |
        Database-Driven Movie Recommendation System
    </p>
</footer>

</body>
</html>
'''

out = Path("/mnt/data/search_updated.php")
out.write_text(updated, encoding="utf-8")
print(f"Created {out}")
