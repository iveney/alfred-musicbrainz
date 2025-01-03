<?php


use Alfred\Workflows\Workflow;
use GuzzleHttp\Client;
use MusicBrainz\Filters\ArtistFilter;
use MusicBrainz\Filters\ReleaseFilter;
use MusicBrainz\HttpAdapters\GuzzleHttpAdapter;
use MusicBrainz\MusicBrainz;

require 'vendor/autoload.php';

const ICON = '50B0CD10-4D52-4898-9C05-DD3A34A829C3.png';
const LIMIT = 10;
const ENDPOINT = "https://musicbrainz.org";
const SEARCH_PATH = ENDPOINT . "/search";

$workflow = new Workflow();
$workflow->logger()->setPrefix('DEBUG');

$query = $workflow->env('query');
$type = $workflow->env('type');

# for debug
// $raw_query = "pink floyd AND country=US";
// $raw_query = "世纪青年招待所";

// parse and handle two types of search
// 1. (Default) Input is read from previous search and selecting type correctly
if ($query === null || $type === null) {
    // 2. Call this directly and provide raw query as "mbs [type] [query]
    // such as `mbs artist artist="pink floyd" AND country=US`.
    // In this type, $query and $type would be null and $raw_query is non-null
    // TODO: parse the raw string
    $type = "artist";
    $query = $raw_query;
}

// TODO: some handling to figure out if this is simple or advanced query.
// Simple: treat the whole query string as query
// Advanced: parse lucene syntax as key1=value1 [AND key2=value2...]

// Create new MusicBrainz object
$brainz = new MusicBrainz(new GuzzleHttpAdapter(new Client()));
$brainz->setUserAgent('Musicbrainz Alfred workflow', '1.0', 'me@ivanxiao.com');

$path = ENDPOINT . "/$type";
if ($type === "artist") {
    $args = array(
        "artist" => $query,
    );
    $filter = new ArtistFilter($args);
    $results = $brainz->search($filter, LIMIT);

    foreach ($results as $result) {
        $name = $result->getName();
        $result_type = $result->getType();
        $id = $result->getId();
        $url = $path . "/$id";
        $workflow->item()
            ->title("$name ($result_type)")
            ->subtitle($id)
            ->arg($url)
            ->autocomplete($name)
            ->copy($name)
            ->icon(ICON)
            ->cmd(fn($mod) => $mod->subtitle($result->getScore())) // Cmd to show score
            ->action($id) // Pass id to Universal Action so that it can be searched
            ->quickLookUrl($url);
    }
} else if ($type === "release") {
    $args = array(
        "release" => $query,
    );
    $filter = new ReleaseFilter($args);
    $results = $brainz->search($filter, LIMIT);

    foreach ($results as $result) {
        $title = $result->title;
        $date = $result->date;
        $artist_names = array_map(fn($artist) => $artist->name, $result->artists);
        $labels = array_map(fn($label) => $label->name, $result->labels);
        $label_names = implode(", ", $labels);
        $id = $result->id;
        $url = $path . "/$id";
        $workflow->item()
            ->title("$title - $date - $label_names")
            ->subtitle(implode(", ", $artist_names))
            ->arg($url)
            ->autocomplete($title)
            ->copy($title)
            ->icon(ICON)
            ->action($id)
            ->quickLookUrl($url);
    }
}

// Add search url
$params = array('query' => $query, 'type' => $type);
$query_string = http_build_query($params);
$search_url = SEARCH_PATH . '?' . $query_string;
$workflow->item()
    ->title("Search MusicBrainz for '$query'")
    ->subtitle($type)
    ->arg($search_url)
    ->icon(ICON)
    ->copy($query);


$workflow->output();
