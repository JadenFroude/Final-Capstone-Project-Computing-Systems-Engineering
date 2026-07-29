<?php

require_once "config.php";

function getMoviePoster($imdbID)
{
    $url =
        "https://api.themoviedb.org/3/find/" .
        $imdbID .
        "?api_key=" .
        TMDB_API_KEY .
        "&external_source=imdb_id";

    $json = file_get_contents($url);

    if (!$json) {
        return null;
    }

    $data = json_decode($json, true);

    if (
        isset($data["movie_results"][0]["poster_path"])
    ) {
        return
            "https://image.tmdb.org/t/p/w500" .
            $data["movie_results"][0]["poster_path"];
    }

    return null;
}