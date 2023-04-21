# RMS Reddit Media Scraper - PHP Library



Reddit Media Scraper is a lightweight, fast, and free PHP library that allows you to easily scrape images and videos from Reddit web sites. It supports all PHP versions and is designed to be easy to use and integrate into your existing PHP projects.

## Features

- Scrapes images and videos from Reddit web sites
- Lightweight and fast
- Free to use
- Supports all PHP versions

## Installation

To install Reddit Media Scraper, simply download the latest release and include the `RedditScraper.php` file in your PHP project.

## Usage

Using Reddit Media Scraper is easy. Simply include the `ms\RedditScraper.php` file in your PHP project and use the `scrapeSubReddit()` ot `scrapeUser()` function to scrape images and videos from Reddit web sites. Here's an example:

```php
require __DIR__.'/../../vendor/autoload.php';

$reddit = new rms\RedditScraper();
$posts = $reddit->scrapeSubReddit('EarthPorn',10);
 
foreach ($posts as $result) {
  echo '<img src="' . $result['image'] . '">';
  echo '<video src="' . $result['video'] . '"></video>';
}
..

```

Fot more example check out the `example` folder 

## Contributions

Contributions to Reddit Media Scraper are welcome and encouraged! If you find a bug or have a feature request, please open an issue on our [GitHub repository](https://github.com/lahirunirmalx/reddit-media-scraper).

## License

Reddit Media Scraper is licensed under the MIT License. See `LICENSE` for more information.
