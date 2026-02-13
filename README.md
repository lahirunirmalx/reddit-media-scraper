# RMS Reddit Media Scraper - PHP Library

Reddit Media Scraper is a lightweight, fast, and reliable PHP library that allows you to easily scrape images and videos from Reddit. It supports multiple media sources including Reddit's native hosting, Imgur, and RedGifs.

## Features

- **Reddit Scraping**: Scrape images and videos from subreddits and user profiles
- **Imgur Support**: Full support for Imgur albums and single images via API v3
- **RedGifs Support**: Download videos from RedGifs using API v2
- **Gallery Support**: Handles Reddit gallery posts with multiple images
- **Rate Limiting**: Built-in 2-second delay between requests to respect API limits
- **File Validation**: Automatic validation of downloaded files (prevents HTML/error pages)
- **Binary Downloads**: Direct binary file downloads with proper format verification
- **Error Handling**: Robust error handling with automatic cleanup of invalid files
- **PHP Compatibility**: Supports PHP 7.1+ and 8.x

## Installation

### Using Composer

```bash
composer require lahirunirmalx/rms
```

### Manual Installation

1. Download or clone this repository
2. Include the `RedditScraper.php` file in your project:

```php
require __DIR__.'/src/rms/RedditScraper.php';
```

## Requirements

- PHP 7.1 or higher
- cURL extension
- GD extension (for PNG to JPG conversion)

## Usage

### Basic Example - Scrape a Subreddit

```php
require __DIR__.'/vendor/autoload.php';

$reddit = new rms\RedditScraper();

// Scrape r/EarthPorn (1 page = ~25 posts)
$posts = $reddit->scrapeSubReddit('EarthPorn', 1);

foreach ($posts as $post) {
    echo $post['title'] . "\n";
    echo $post['url'] . "\n";
    echo "Author: " . $post['author'] . "\n\n";
}
```

### Download Images from Subreddit

```php
require __DIR__.'/vendor/autoload.php';

$reddit = new rms\RedditScraper();

$baseDir = 'images';
$reddit->mkdir($baseDir);

// Scrape posts
$posts = $reddit->scrapeSubReddit('pics', 1);

foreach ($posts as $item) {
    $saveDir = $baseDir . '/' . $item['author'];
    
    // Process different URL types
    if (strstr($item['url'], 'imgur.com')) {
        if (strstr($item['url'], '.gifv')) {
            $reddit->processImgurLinkGifv($item['url'], $saveDir);
        } else {
            $reddit->processImgurLink($item['url'], $saveDir);
        }
    } else if (strstr($item['url'], 'i.redd.it')) {
        $reddit->processIreddit($item['url'], $saveDir);
    } else if (strstr($item['url'], 'redgifs.com')) {
        $reddit->processRedGiff($item['url'], $saveDir);
    } else {
        $reddit->processLinks($item['url'], $saveDir);
    }
}
```

### Scrape User Profile

```php
require __DIR__.'/vendor/autoload.php';

$reddit = new rms\RedditScraper();

$posts = $reddit->scrapeUser('username', 1);

foreach ($posts as $post) {
    // Process posts...
}
```

## API Methods

### Scraping Methods

- `scrapeSubReddit($section, $max_pages = 1)` - Scrape a subreddit
- `scrapeUser($section, $max_pages = 1)` - Scrape a user's posts
- `scrape($base_url, $max_pages)` - Low-level scraping method

### Processing Methods

- `processImgurLink($url, $savedir, $username = '')` - Download Imgur images/albums
- `processImgurLinkGifv($url, $savedir, $username = '')` - Download Imgur GIFV videos
- `processIreddit($url, $savedir, $username = '')` - Download i.redd.it images
- `processRedGiff($url, $savedir, $username = '')` - Download RedGifs videos
- `processLinks($url, $savedir, $username = '')` - Save non-image links to text file

### Utility Methods

- `mkdir($folderPath)` - Create directory with proper permissions
- `createFolder($savedir, $username)` - Create user-specific folder
- `cleanFileName($name)` - Sanitize filenames
- `isFilePNG($filename)` - Check if file is PNG format
- `png2jpg($originalFile, $outputFile, $quality)` - Convert PNG to JPG

## Supported Media Sources

### Reddit (i.redd.it)
- Direct image hosting
- Automatic format detection
- PNG to JPG conversion support

### Imgur
- Single images via API v3
- Albums via API v3 with fallback to /noscript
- GIFV videos (converted to MP4)
- Automatic URL normalization

### RedGifs
- Video downloads via API v2
- HD quality preferred
- Automatic authentication handling

## Features & Improvements

### Rate Limiting
The scraper automatically waits 2 seconds between requests to respect Reddit's API limits and avoid rate limiting.

### File Validation
All downloaded files are validated to ensure they are actual images/videos and not HTML error pages:
- JPEG signature verification (`FF D8 FF`)
- PNG signature verification
- HTML content detection
- Automatic cleanup of invalid files

### Error Handling
- Graceful handling of missing data
- Automatic retry for authentication failures
- Proper HTTP status code checking
- Detailed error reporting

### Gallery Support
Full support for Reddit gallery posts with multiple images, extracting all images from the gallery metadata.

## Examples

Check out the `src/example/` folder for complete working examples:

- `save-subreddit.php` - Scrape and save images from multiple subreddits
- `save-user.php` - Scrape and save images from user profiles

## Technical Details

### API Integration

- **Reddit**: Uses Reddit's JSON API with proper User-Agent headers
- **Imgur**: Uses Imgur API v3 with Client-ID authentication
- **RedGifs**: Uses RedGifs API v2 with temporary token authentication

### File Downloads

All file downloads use direct binary transfer (`CURLOPT_FILE`) for efficiency and proper handling of large files. Files are validated after download to ensure they are valid media files.

## Requirements

- PHP 7.1+ or PHP 8.x
- cURL extension
- GD extension (for PNG conversion)
- Composer (optional, for autoloading)

## License

Reddit Media Scraper is licensed under the MIT License. See `LICENSE` for more information.

## Contributing

Contributions are welcome! If you find a bug or have a feature request, please open an issue on the [GitHub repository](https://github.com/lahirunirmalx/reddit-media-scraper).

## Changelog

### Recent Updates
- Added Imgur API v3 integration for reliable album/image downloads
- Added RedGifs API v2 integration for video downloads
- Implemented binary file downloads with validation
- Added file format verification to prevent HTML/error pages
- Added rate limiting to respect API limits
- Improved error handling and automatic cleanup
- Added support for Reddit gallery posts
- Updated to use HTTPS for all requests
- Enhanced URL normalization for all media sources
