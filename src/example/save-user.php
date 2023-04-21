<?php
require __DIR__.'/../../vendor/autoload.php';

$reddit = new rms\RedditScraper();

// reddit/imgur scraper
$users = array(
    'andrewrimanic' => 1 // username and number of pages you need to scan
);

$saveDir = 'users'; // base dir where your files will be saved sub folders will be created with author/username (deleted users may get an issue)
$reddit->mkdir($saveDir);
echo "Fetching pages...\n";
$data = array();


foreach($users as  $section=> $max_pages) {
    // scrape the list of posts
    $posts = $reddit->scrapeUser($section,$max_pages);
    if(is_array($posts)) {
        $data = array_merge($data, $posts);
    }else{
        echo "Error  ".$section." ...\n";
    }


    }


// total number of links returned, initialize counter for percentages
$totalItems = count($data);
$counter = 1;

echo "Parsing ".$totalItems." total items...\n\n";

// process the links we are left with
foreach($data as $item) {


    if(strstr($item['url'],'imgur.com') && (strstr($item['url'],'.jpg') ||  strstr($item['url'],'.png') )) {
        $reddit->processImgurLink($item['url'],$saveDir,$item['author']);
    }else if(strstr($item['url'],'imgur.com') && strstr($item['url'],'.gifv') ) {
        $reddit->processImgurLinkGifv($item['url'],$saveDir,$item['author']);
    }else if (strstr($item['url'],'i.redd.it')){
        $reddit->processIreddit($item['url'],$saveDir,$item['author']);
    } else{
        $reddit->processLinks($item['url'],$saveDir,$item['author']); // other links that not contain images and redgif urls will be save to links.txt
    }

    // display progress
    $completed = round(($counter/$totalItems)*100);
    echo ($completed<>$last_completion) ? $completed."% complete\n" : '';
    $last_completion = $completed;
    $counter++;

}