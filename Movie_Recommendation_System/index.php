<?php

require_once "db.php";

$sql = "
    SELECT
        m.tconst,
        m.primarytitle AS title,
        m.startyear AS release_year,
        m.genres,
        m.poster_url,
        r.averagerating AS rating,
        r.numvotes AS votes,
        ROUND(
            (r.averagerating * LN(r.numvotes + 1))::NUMERIC,
            2
        ) AS popularity_score
    FROM movies m
    INNER JOIN ratings r
        ON m.tconst = r.tconst
    WHERE m.titletype = 'movie'
      AND m.isadult = 0
      AND r.numvotes >= 100000
    ORDER BY popularity_score DESC
    LIMIT 8
";

$statement = $pdo->query($sql);
$popularMovies = $statement->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>CineMatch | Find Movies Your Way</title>

    <link rel="stylesheet" href="css/style.css?v=6">
</head>

<body>

<header class="navbar">

    <h1>
        <a href="index.php">🎬 CineMatch</a>
    </h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="#movie-search">Search</a>
        <a href="#algorithm">Algorithm</a>
        <a href="#trending">Trending</a>
        <a href="#genres">Genres</a>
    </nav>

</header>

<main>

    <!-- ================================
         HERO AND SEARCH
    ================================= -->

    <section class="hero" id="movie-search">

        <div class="hero-content">

            <p class="tagline">
                Powered by your preferences
            </p>

            <h2>
                Find Movies Your Way
            </h2>

            <p class="hero-text">
                Search by title, actor, genre, rating, year, and runtime.
                Then control how CineMatch ranks your recommendations.
            </p>

        </div>

        <form
            id="recommendation-form"
            class="search-box"
            action="search.php"
            method="GET"
        >

            <div class="input-group">

                <label for="title">
                    Movie Title
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="Search by title..."
                    maxlength="255"
                >

            </div>

            <div class="input-group">

                <label for="actor">
                    Actor Name
                </label>

                <input
                    type="text"
                    id="actor"
                    name="actor"
                    placeholder="Search by actor..."
                    maxlength="255"
                >

            </div>

            <div class="filter-row three-columns">

    <div class="input-group">
        <label for="genre">Genre</label>

        <select id="genre" name="genre">
            <option value="">Any Genre</option>
            <option value="Action">Action</option>
            <option value="Adventure">Adventure</option>
            <option value="Animation">Animation</option>
            <option value="Biography">Biography</option>
            <option value="Comedy">Comedy</option>
            <option value="Crime">Crime</option>
            <option value="Documentary">Documentary</option>
            <option value="Drama">Drama</option>
            <option value="Family">Family</option>
            <option value="Fantasy">Fantasy</option>
            <option value="History">History</option>
            <option value="Horror">Horror</option>
            <option value="Music">Music</option>
            <option value="Mystery">Mystery</option>
            <option value="Romance">Romance</option>
            <option value="Sci-Fi">Sci-Fi</option>
            <option value="Sport">Sport</option>
            <option value="Thriller">Thriller</option>
            <option value="War">War</option>
            <option value="Western">Western</option>
        </select>
    </div>

    <div class="input-group">
        <label for="year">Preferred Year</label>

        <input
            type="number"
            id="year"
            name="year"
            min="1888"
            max="2030"
            placeholder="Example: 2014"
        >
    </div>

    <div class="input-group">
        <label for="rating">Minimum Rating</label>

        <select id="rating" name="rating">
            <option value="">Any Rating</option>
            <option value="5">5.0+</option>
            <option value="6">6.0+</option>
            <option value="7">7.0+</option>
            <option value="8">8.0+</option>
            <option value="9">9.0+</option>
        </select>
    </div>

</div>

<div class="filter-row two-columns">

    <div class="input-group">
        <label for="decade">Preferred Decade</label>

        <select id="decade" name="decade">
            <option value="">Any Decade</option>
            <option value="2020">2020s</option>
            <option value="2010">2010s</option>
            <option value="2000">2000s</option>
            <option value="1990">1990s</option>
            <option value="1980">1980s</option>
            <option value="1970">1970s</option>
            <option value="1960">1960s</option>
            <option value="1950">1950s</option>
            <option value="1940">1940s</option>
            <option value="1930">1930s</option>
            <option value="1920">1920s</option>
        </select>
    </div>

    <div class="input-group">
        <label for="votes">Minimum Votes on Movie</label>

        <select id="votes" name="votes">
            <option value="0">Any Amount</option>
            <option value="1000">1,000+</option>
            <option value="5000">5,000+</option>
            <option value="10000" selected>10,000+</option>
            <option value="25000">25,000+</option>
            <option value="50000">50,000+</option>
            <option value="100000">100,000+</option>
        </select>
    </div>

</div>


            <div class="input-group">

                <label for="runtime">
                    Preferred Runtime
                </label>

                <select id="runtime" name="runtime">

                    <option value="">
                        Any Runtime
                    </option>

                    <option value="60">
                        About 60 minutes
                    </option>

                    <option value="90">
                        About 90 minutes
                    </option>

                    <option value="120">
                        About 2 hours
                    </option>

                    <option value="150">
                        About 2.5 hours
                    </option>

                    <option value="180">
                        About 3 hours
                    </option>

                </select>

            </div>

            <div class="input-group">

            



    <label for="sort">
        Sort By
    </label>

    <select id="sort" name="sort">

        <option value="popular">
            Most Popular
        </option>

        <option value="rating_high">
            Highest Rated
        </option>

        <option value="rating_low">
            Lowest Rated
        </option>

        <option value="newest">
            Newest First
        </option>

        <option value="oldest">
            Oldest First
        </option>

        <option value="runtime_short">
            Shortest Runtime
        </option>

        <option value="runtime_long">
            Longest Runtime
        </option>

    </select>

</div>



            <button type="submit">
                Search Movies
            </button>

        </form>

    </section>

    <!-- ================================
         TRENDING MOVIES
    ================================= -->

    <section class="movie-section" id="trending">

    <h2>🔥 Most Popular Movies</h2>

    <p class="section-subtitle">
        Popular movies ranked by the number of IMDb user votes.
    </p>

    <div class="movie-grid">

    <?php foreach ($popularMovies as $movie): ?>

        <?php
        $posterUrl = !empty($movie["poster_url"])
            ? $movie["poster_url"]
            : "images/poster-placeholder.jpg";
        ?>

        <article class="movie-card">

            <a
                href="movie.php?id=<?= urlencode($movie["tconst"]) ?>"
                class="movie-card-link"
            >

                <img
                    src="<?= htmlspecialchars($posterUrl) ?>"
                    alt="<?= htmlspecialchars($movie["title"]) ?> poster"
                >

                <div class="movie-card-content">

                    <h3>
                        <?= htmlspecialchars($movie["title"]) ?>
                    </h3>

                    <p>
                        ⭐ <?= htmlspecialchars((string) $movie["rating"]) ?>
                        •
                        <?= htmlspecialchars((string) $movie["release_year"]) ?>
                    </p>

                    <p class="movie-genres">
                        <?= htmlspecialchars(
                            $movie["genres"] ?? "Unknown genre"
                        ) ?>
                    </p>

                    <p class="movie-votes">
                        <?= number_format((int) $movie["votes"]) ?>
                        IMDb votes
                    </p>

                </div>

            </a>

        </article>

    <?php endforeach; ?>

</div>

</section>

    <!-- ================================
         ALGORITHM SLIDERS
    ================================= -->

    <section class="algorithm-section" id="algorithm">

        <h2>
            ⚙️ Customize Your Algorithm
        </h2>

        <p>
            Adjust the importance of each category.
            Higher values have more influence on how your results are ranked.
        </p>

        <div class="slider-container">

            <div class="slider-card">

                <label for="genre-weight">
                    Genre Match
                    <span>80%</span>
                </label>

                <input
                    type="range"
                    id="genre-weight"
                    name="genre_weight"
                    min="0"
                    max="100"
                    step="25"
                    value="100"
                    form="recommendation-form"
                >

            </div>

            <div class="slider-card">

                <label for="actor-weight">
                    Actor Match
                    <span>90%</span>
                </label>

                <input
                    type="range"
                    id="actor-weight"
                    name="actor_weight"
                    min="0"
                    max="100"
                    step="25"
                    value="100"
                    form="recommendation-form"
                >

            </div>

            <div class="slider-card">

                <label for="rating-weight">
                    IMDb Rating
                    <span>70%</span>
                </label>

                <input
                    type="range"
                    id="rating-weight"
                    name="rating_weight"
                    min="0"
                    max="100"
                    step="25"
                    value="100"
                    form="recommendation-form"
                >

            </div>

            <div class="slider-card">

                <label for="year-weight">
                    Release Year
                    <span>40%</span>
                </label>

                <input
                    type="range"
                    id="year-weight"
                    name="year_weight"
                    min="0"
                    max="100"
                    step="25"
                    value="100"
                    form="recommendation-form"
                >

            </div>

            <div class="slider-card">

                <label for="runtime-weight">
                    Runtime
                    <span>30%</span>
                </label>

                <input
                    type="range"
                    id="runtime-weight"
                    name="runtime_weight"
                    min="0"
                    max="100"
                    step="25"
                    value="100"
                    form="recommendation-form"
                >

            </div>

        </div>

        <button
    type="submit"
    class="algorithm-submit"
    form="recommendation-form"
>
    ✨ Find My Movies
</button>

    </section>

    <!-- ================================
         GENRES
    ================================= -->

    <section class="genre-section" id="genres">

        <h2>
            🎭 Browse by Genre
        </h2>

        <p class="section-subtitle">
            Select a genre to quickly search the IMDb database.
        </p>

        <div class="genre-grid">

            <a href="search.php?genre=Action">
                Action
            </a>

            <a href="search.php?genre=Comedy">
                Comedy
            </a>

            <a href="search.php?genre=Drama">
                Drama
            </a>

            <a href="search.php?genre=Horror">
                Horror
            </a>

            <a href="search.php?genre=Sci-Fi">
                Sci-Fi
            </a>

            <a href="search.php?genre=Animation">
                Animation
            </a>

            <a href="search.php?genre=Crime">
                Crime
            </a>

            <a href="search.php?genre=Fantasy">
                Fantasy
            </a>

        </div>

    </section>

    <!-- ================================
         FEATURES
    ================================= -->

    <section class="features">

        <div>

            <h3>
                🎯 User-Controlled Search
            </h3>

            <p>
                Search using specific criteria instead of relying only on
                hidden recommendation systems.
            </p>

        </div>

        <div>

            <h3>
                ⚙️ Custom Ranking
            </h3>

            <p>
                Control how much genre, actors, ratings, release year,
                and runtime affect your results.
            </p>

        </div>

        <div>

            <h3>
                🗄 IMDb Database
            </h3>

            <p>
                Search millions of titles, ratings, people, and movie
                relationships stored in PostgreSQL.
            </p>

        </div>

    </section>

</main>

<footer>

    <p>
        © 2026 CineMatch |
        Database-Driven Movie Recommendation System
    </p>

</footer>

<script src="SliderForAlgorithmScript.js"></script>

</body>

</html>
```
