<?php


use Alfred\Workflows\Workflow;
use GuzzleHttp\Client;
use MusicBrainz\HttpAdapters\GuzzleHttpAdapter;
use MusicBrainz\MusicBrainz;
use MusicBrainz\Release;

require 'vendor/autoload.php';

const ICON = '50B0CD10-4D52-4898-9C05-DD3A34A829C3.png';
const LIMIT = 10;
const ENDPOINT = "https://musicbrainz.org";
const SEARCH_PATH = ENDPOINT . "/search";

$workflow = new Workflow();
$workflow->logger()->setPrefix('DEBUG');

$brainz = new MusicBrainz(new GuzzleHttpAdapter(new Client()));
$brainz->setUserAgent('Musicbrainz Alfred workflow', '1.0', 'me@ivanxiao.com');

$mbid = $query;

$includes = array('labels', 'recordings', 'artist-credits');

try {
    $type = 'artist';
    $results = $brainz->browseRelease($type, $mbid, $includes, LIMIT);
    $path = ENDPOINT . "/release";
    foreach ($results['releases'] as $release) {
        $result = new Release($release, $brainz);
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
} catch (Exception $e) {
    // Add search url
    $params = array('query' => $query, 'type' => $type);
    $query_string = http_build_query($params);
    $search_url = SEARCH_PATH . '?' . $query_string;
    $workflow->item()
        ->title("Search MusicBrainz for '$query'")
        ->subtitle("Wrong type? Currently: $type")
        ->arg($search_url)
        ->icon(ICON)
        ->copy($query);
}

$workflow->output();