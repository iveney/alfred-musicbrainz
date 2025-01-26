<?php


use Alfred\Workflows\Workflow;
use GuzzleHttp\Client;
use MusicBrainz\Artist;
use MusicBrainz\Release;
use MusicBrainz\ReleaseGroup;
use MusicBrainz\Tag;
use MusicBrainz\Label;
use MusicBrainz\Filters\ArtistFilter;
use MusicBrainz\Filters\ReleaseFilter;
use MusicBrainz\Filters\ReleaseGroupFilter;
use MusicBrainz\HttpAdapters\GuzzleHttpAdapter;
use MusicBrainz\MusicBrainz;

require 'vendor/autoload.php';

const ICON = '50B0CD10-4D52-4898-9C05-DD3A34A829C3.png';
const LIMIT = 15;
const ENDPOINT = "https://musicbrainz.org";
const SEARCH_PATH = ENDPOINT . "/search";

$workflow = new Workflow();
$workflow->logger()->setPrefix('DEBUG');

$query = $query ?? $workflow->env('query');
$type = $type ?? $workflow->env('type');

// Can also run as script using env variables, e.g.,
// query="Highway to hell" type="release" php musicbrainz.php | jq

# for debug
// $raw_query = "pink floyd AND country=US";
// $raw_query = "Highway to hell";

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
        if (!$result instanceof Artist) {
            $workflow->logger()->info("Cast error");
            continue;
        }
        // Generate descriptive title, this should look like 
        // Artist Name (primary alias1, primary alias2, ..., dismbiguation)
        $name = $result->getName();
        $disambiguation = $result->disambiguation;
        $title = $name;

        $primaryAliases = $result->getPrimaryAliasesString();
        $desc = implode(', ', array_filter([$primaryAliases, $disambiguation]));
        if (!empty($desc)) {
            $title = $title . " ($desc)";
        }

        $id = $result->getId();
        $url = $path . "/$id";
        $workflow->item()
            ->title($title)
            ->subtitle($id)
            ->arg($url)
            ->autocomplete($name)
            ->copy($name)
            ->icon(ICON)
            ->cmd(fn($mod) => $mod->subtitle("Score: " . $result->getScore())) // Cmd to show score
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
        if (!$result instanceof Release) {
            $workflow->logger()->info("Cast error");
            continue;
        }
        $releaseTitle = $result->title;
        $artistNames = Artist::arrayToString($result->artists);
        $title = "$artistNames - $releaseTitle";
        if (!empty($result->date)) {
            $title .= " ($result->date)";
        }

        $labels = Label::arrayToString($result->labels);
        $trackCount = $result->trackCount;
        $subtitle = implode(" | ", array_filter([
            $labels, 
            "$trackCount tracks",
        ]));

        $id = $result->id;
        $url = $path . "/$id";
        $workflow->item()
            ->title($title)
            ->subtitle($subtitle)
            ->arg($url)
            ->autocomplete($releaseTitle)
            ->copy($title)
            ->icon(ICON)
            ->cmd(fn($mod) => $mod->subtitle("Score: " . $result->getScore())) // Cmd to show score
            ->action($id)
            ->quickLookUrl($url);
    }
} else if ($type === "release-group") {
    $args = array(
        "releasegroup" => $query,
    );
    $filter = new ReleaseGroupFilter($args);
    $results = $brainz->search($filter, LIMIT);

    foreach ($results as $result) {
        if (!$result instanceof ReleaseGroup) {
            $workflow->logger()->info("Cast error");
            continue;
        }
        $title = $result->getTitle();
        $artistNames = Artist::arrayToString($result->getArtists());
        $firstReleaseDate = $result->getFirstReleaseDate();
        $id = $result->id;
        $url = $path . "/$id";

        // addtional info for subtitle
        $numReleases = $result->getCount();
        $tags = Tag::arrayToString($result->getTags());

        $subtitles = [
            $result->getPrimaryType(),
            "$numReleases releases",
            $tags,
        ];

        $workflow->item()
            ->title("$artistNames - $title ($firstReleaseDate)")
            ->subtitle(implode(" | ", array_filter($subtitles)))
            ->arg($url)
            ->autocomplete($title)
            ->copy($title)
            ->icon(ICON)
            ->cmd(fn($mod) => $mod->subtitle("Score: " . $result->getScore())) // Cmd to show score
            ->action($id)
            ->quickLookUrl($url);
    }
}

// Add search url
// Note that for release-group, the search type is underscore, while URL uses dash
$query_type = $type === "release-group" ? "release_group" : $type;
$params = array('query' => $query, 'type' => $query_type);
$query_string = http_build_query($params);
$search_url = SEARCH_PATH . '?' . $query_string;
$workflow->item()
    ->title("Search MusicBrainz for '$query'")
    ->subtitle($type)
    ->arg($search_url)
    ->icon(ICON)
    ->copy($query);


$workflow->output();
