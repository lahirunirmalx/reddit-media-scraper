<?php
require __DIR__.'/../../src/rms/RedditScraper.php';

$reddit = new rms\RedditScraper();

// reddit/imgur scraper
$users = array(
    'pics' => 1 , // subreddit and number of pages you need to scan
    'nasa' => 1 ,
    'EarthPorn' => 1 ,
);

$baseDir = 'images'; // base dir where your files will be saved sub folders will be created with author/username (deleted users may get an issue)
$reddit->mkdir($baseDir);
echo "Fetching pages...\n";
$data = array();


foreach($users as  $section=> $max_pages) {
    // scrape the list of posts
    $posts = $reddit->scrapeSubReddit($section,$max_pages);
    if(is_array($posts)) {
        $data = array_merge($data, $posts);
    }else{
        echo "Error  ".$section." ...\n";
    }


}


// total number of links returned, initialize counter for percentages
$totalItems = count($data);
$counter = 1;
$last_completion = 0;

echo "Parsing ".$totalItems." total items...\n\n";

// process the links we are left with
foreach($data as $item) {
    $saveDir = $baseDir.DIRECTORY_SEPARATOR.$item['author'];

    if(strstr($item['url'],'imgur.com')) {
        if(strstr($item['url'],'.gifv')) {
            $reddit->processImgurLinkGifv($item['url'],$saveDir);
        } else {
            $reddit->processImgurLink($item['url'],$saveDir);
        }
    }else if (strstr($item['url'],'i.redd.it')){
        $reddit->processIreddit($item['url'],$saveDir);
    }else if (strstr($item['url'],'redgifs.com')){
        $reddit->processRedGiff($item['url'],$saveDir);
    } else{
        $reddit->processLinks($item['url'],$saveDir); // other links that not contain images and redgif urls will be save to links.txt
    }

    // display progress
    $completed = round(($counter/$totalItems)*100);
    echo ($completed<>$last_completion) ? $completed."% complete\n" : '';
    $last_completion = $completed;
    $counter++;

}