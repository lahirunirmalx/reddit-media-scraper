<?php

/**
 * Reddit Media Scraper PHP Class
 * 
 * Production-ready class for scraping images and videos from Reddit, Imgur, and RedGifs.
 * 
 * @package rms
 * @author lahirunirmalx
 * @license MIT
 */
namespace rms;

class RedditScraper
{
    private $lastRequestTime = 0;
    private static $redgifsAuthToken = '';
    
    private const SLEEP_TIME = 2000; // 2 seconds in milliseconds
    private const REDDIT_USER_AGENT = 'RipMe:github.com/RipMeApp/ripme:1.0 (by /u/metaprime and /u/ineedmorealts)';
    const BASE_URL = 'https://www.reddit.com';
    private const IMGUR_CLIENT_ID = '546c25a59c58ad7';
    private const REDGIFS_AUTH_ENDPOINT = 'https://api.redgifs.com/v2/auth/temporary';
    private const REDGIFS_GIF_ENDPOINT = 'https://api.redgifs.com/v2/gifs/%s';
    
    private const MAX_REDIRECTS = 10;
    private const REQUEST_TIMEOUT = 30;
    private const DOWNLOAD_TIMEOUT = 60;
    private const MAX_FILENAME_LENGTH = 255;

    /**
     * Scrape Reddit posts from a given base URL
     * 
     * @param string $base_url Base URL to scrape
     * @param int $max_pages Maximum number of pages to scrape
     * @return array|false Array of posts with url, title, author, id or false on failure
     */
    public function scrape($base_url, $max_pages)
    {
        if (!is_string($base_url) || empty($base_url)) {
            return false;
        }
        
        if (!is_int($max_pages) || $max_pages < 0) {
            $max_pages = 1;
        }
        
        $entries = array();
        $after = null;
        
        for ($i = 0; $i <= $max_pages; $i++) {
            $scrape_url = ($i == 0) ? $base_url : $base_url . '?after=' . urlencode($after);
            
            $data = json_decode($this->get_j($scrape_url), true);
            
            if (!isset($data['data']) || !isset($data['data']['children'])) {
                break;
            }
            
            $after = null;
            if (isset($data['data']['after']) && !empty($data['data']['after'])) {
                $after = $data['data']['after'];
            }

            foreach ($data['data']['children'] as $child) {
                if (!isset($child['kind']) || $child['kind'] !== 't3') {
                    continue;
                }
                
                $childData = $child['data'];
                
                // Skip self posts unless they have gallery data
                if (isset($childData['is_self']) && $childData['is_self'] && 
                    (!isset($childData['gallery_data']) || empty($childData['gallery_data']))) {
                    continue;
                }
                
                // Handle gallery posts
                if (isset($childData['gallery_data']) && isset($childData['media_metadata'])) {
                    $galleryItems = $childData['gallery_data']['items'];
                    $mediaMetadata = $childData['media_metadata'];
                    $title = isset($childData['title']) ? $this->sanitizeString($childData['title']) : '';
                    $author = isset($childData['author']) ? $this->sanitizeString($childData['author']) : '';
                    $id = isset($childData['id']) ? $this->sanitizeString($childData['id']) : '';
                    
                    foreach ($galleryItems as $item) {
                        if (!isset($item['media_id'])) {
                            continue;
                        }
                        $mediaId = $item['media_id'];
                        if (isset($mediaMetadata[$mediaId])) {
                            $media = $mediaMetadata[$mediaId];
                            if (isset($media['s'])) {
                                $url = isset($media['s']['gif']) ? $media['s']['gif'] : $media['s']['u'];
                                $url = str_replace('&amp;', '&', $url);
                                if ($this->isValidUrl($url)) {
                                    $entries[] = array(
                                        'url' => $url,
                                        'title' => $title,
                                        'author' => $author,
                                        'id' => $id
                                    );
                                }
                            }
                        }
                    }
                } elseif (isset($childData['url']) && isset($childData['title']) && isset($childData['author'])) {
                    $url = $childData['url'];
                    if ($this->isValidUrl($url)) {
                        $entries[] = array(
                            'url' => $url,
                            'title' => $this->sanitizeString($childData['title']),
                            'author' => $this->sanitizeString($childData['author']),
                            'id' => isset($childData['id']) ? $this->sanitizeString($childData['id']) : ''
                        );
                    }
                }
            }
            
            if ($after === null) {
                break;
            }
        }
        
        return !empty($entries) ? $entries : false;
    }

    /**
     * Scrape a subreddit
     * 
     * @param string $section Subreddit name (without r/)
     * @param int $max_pages Maximum number of pages to scrape
     * @return array|false Array of posts or false on failure
     */
    public function scrapeSubReddit($section, $max_pages = 1)
    {
        if (!is_string($section) || empty($section)) {
            return false;
        }
        
        $section = $this->sanitizeString($section);
        $base_url = self::BASE_URL . '/r/' . urlencode($section) . '.json';
        return $this->scrape($base_url, $max_pages);
    }

    /**
     * Scrape a user's posts
     * 
     * @param string $section Username
     * @param int $max_pages Maximum number of pages to scrape
     * @return array|false Array of posts or false on failure
     */
    public function scrapeUser($section, $max_pages = 1)
    {
        if (!is_string($section) || empty($section)) {
            return false;
        }
        
        $section = $this->sanitizeString($section);
        $base_url = self::BASE_URL . '/user/' . urlencode($section) . '.json';
        return $this->scrape($base_url, $max_pages);
    }

    /**
     * Make HTTP GET request with rate limiting
     * 
     * @param string $url URL to request
     * @return string|false Response body or false on failure
     */
    private function get_j($url)
    {
        if (!$this->isValidUrl($url)) {
            return false;
        }
        
        // Rate limiting: wait 2 seconds between requests
        $timeDiff = (microtime(true) * 1000) - $this->lastRequestTime;
        if ($timeDiff < self::SLEEP_TIME) {
            $sleepTime = (self::SLEEP_TIME - $timeDiff) / 1000;
            usleep((int)($sleepTime * 1000000));
        }
        $this->lastRequestTime = microtime(true) * 1000;

        $curl = curl_init();
        if ($curl === false) {
            return false;
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'User-Agent: ' . self::REDDIT_USER_AGENT,
                'Accept: application/json, text/html, */*',
                'Accept-Language: en-US,en;q=0.9'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error || $httpCode !== 200) {
            return false;
        }
        
        return $response;
    }

    /**
     * Backward compatibility alias
     * 
     * @param string $url URL to request
     * @return string|false Response body or false on failure
     */
    public function processRequest($url)
    {
        return $this->get_j($url);
    }

    /**
     * Download binary file (images, videos) directly to file path
     * 
     * @param string $url URL to download
     * @param string $filePath Path where file should be saved
     * @return bool True on success, false on failure
     */
    private function downloadBinaryFile($url, $filePath)
    {
        if (!$this->isValidUrl($url) || !$this->isValidFilePath($filePath)) {
            return false;
        }
        
        // Rate limiting
        $timeDiff = (microtime(true) * 1000) - $this->lastRequestTime;
        if ($timeDiff < self::SLEEP_TIME) {
            $sleepTime = (self::SLEEP_TIME - $timeDiff) / 1000;
            usleep((int)($sleepTime * 1000000));
        }
        $this->lastRequestTime = microtime(true) * 1000;

        $fp = @fopen($filePath, 'wb');
        if ($fp === false) {
            return false;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            fclose($fp);
            @unlink($filePath);
            return false;
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => self::DOWNLOAD_TIMEOUT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'User-Agent: ' . self::REDDIT_USER_AGENT,
                'Accept: image/*, video/*, */*',
                'Accept-Language: en-US,en;q=0.9'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BINARYTRANSFER => true
        ));

        $success = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($fp);

        if ($error || !$success || $httpCode !== 200) {
            @unlink($filePath);
            return false;
        }

        // Verify file is not empty
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            @unlink($filePath);
            return false;
        }

        // Validate file content
        if (!$this->validateFileContent($filePath, $contentType)) {
            @unlink($filePath);
            return false;
        }

        return true;
    }

    /**
     * Process Imgur link and download images
     * 
     * @param string $url Imgur URL
     * @param string $savedir Directory to save files
     * @param string $username Optional username for subdirectory
     * @return string|bool File path on success, false on failure
     */
    public function processImgurLink($url, $savedir, $username = '')
    {
        if (!$this->isValidUrl($url) || !$this->isValidFilePath($savedir)) {
            return false;
        }
        
        $dir = $this->getSaveDirectory($savedir, $username);
        if (!$dir) {
            return false;
        }

        // Normalize URL
        $url = $this->sanitizeImgurUrl($url);

        // Handle direct i.imgur.com links
        if (strpos($url, 'i.imgur.com') !== false) {
            $imgname = basename(parse_url($url, PHP_URL_PATH));
            if (empty($imgname)) {
                return false;
            }
            $img = $dir . '/' . $this->cleanFileName($imgname);
            if ($this->downloadBinaryFile($url, $img)) {
                if ($this->isFilePNG($img)) {
                    $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                }
                return $img;
            }
            return false;
        }

        // Handle albums (imgur.com/a/ or imgur.com/gallery/)
        if (preg_match('/imgur\.com\/(?:a|gallery)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $albumId = $matches[1];
            $imageUrls = $this->getImgurAlbumImages($albumId, $url);
            if (!empty($imageUrls)) {
                $success = false;
                foreach ($imageUrls as $imageUrl) {
                    if (!$this->isValidUrl($imageUrl)) {
                        continue;
                    }
                    $imgname = basename(parse_url($imageUrl, PHP_URL_PATH));
                    if (empty($imgname)) {
                        continue;
                    }
                    $img = $dir . '/' . $this->cleanFileName($imgname);
                    if ($this->downloadBinaryFile($imageUrl, $img)) {
                        if ($this->isFilePNG($img)) {
                            $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                        }
                        $success = true;
                    }
                }
                return $success;
            }
            return false;
        }

        // Handle single image (imgur.com/XXXXX format)
        if (preg_match('/imgur\.com\/([a-zA-Z0-9]{5,})/', $url, $matches)) {
            $imageId = $matches[1];
            $imageUrl = $this->getImgurSingleImage($imageId);
            if ($imageUrl && $this->isValidUrl($imageUrl)) {
                $imgname = basename(parse_url($imageUrl, PHP_URL_PATH));
                if (empty($imgname)) {
                    return false;
                }
                $img = $dir . '/' . $this->cleanFileName($imgname);
                if ($this->downloadBinaryFile($imageUrl, $img)) {
                    if ($this->isFilePNG($img)) {
                        $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                    }
                    return $img;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Process Imgur GIFV link and download as MP4
     * 
     * @param string $url Imgur GIFV URL
     * @param string $savedir Directory to save files
     * @param string $username Optional username for subdirectory
     * @return string|false File path on success, false on failure
     */
    public function processImgurLinkGifv($url, $savedir, $username = '')
    {
        if (!$this->isValidUrl($url) || strpos($url, 'i.imgur') === false) {
            return false;
        }
        
        if (!$this->isValidFilePath($savedir)) {
            return false;
        }
        
        $url = str_replace('.gifv', '.mp4', $url);
        $imgname = basename(parse_url($url, PHP_URL_PATH));
        if (empty($imgname)) {
            return false;
        }
        
        $dir = $this->getSaveDirectory($savedir, $username);
        if (!$dir) {
            return false;
        }
        
        $img = $dir . '/' . $this->cleanFileName($imgname);
        
        if ($this->downloadBinaryFile($url, $img)) {
            return $img;
        }
        
        return false;
    }

    /**
     * Process RedGifs link and download video
     * 
     * @param string $url RedGifs URL
     * @param string $savedir Directory to save files
     * @param string $username Optional username for subdirectory
     * @return string|false File path on success, false on failure
     */
    public function processRedGiff($url, $savedir, $username = '')
    {
        if (strpos($url, 'redgifs.com') === false && strpos($url, 'gifdeliverynetwork.com') === false) {
            return false;
        }

        if (!$this->isValidFilePath($savedir)) {
            return false;
        }

        // Normalize URL
        $url = $this->sanitizeRedgifsUrl($url);
        
        // Extract gif ID from URL
        $gifId = $this->extractRedgifsId($url);
        if (!$gifId) {
            return false;
        }

        // Get video URL from API
        $videoUrl = $this->getRedgifsVideoUrl($gifId);
        if (!$videoUrl || !$this->isValidUrl($videoUrl)) {
            return false;
        }

        // Prepare directory
        $dir = $this->getSaveDirectory($savedir, $username);
        if (!$dir) {
            return false;
        }

        // Determine filename
        $ext = '.mp4';
        if (preg_match('/\.([a-zA-Z0-9]+)(?:\?|$)/', $videoUrl, $matches)) {
            $ext = '.' . $matches[1];
        }
        
        $filename = $this->cleanFileName($gifId) . $ext;
        if (strlen($filename) > self::MAX_FILENAME_LENGTH) {
            $filename = substr($filename, 0, self::MAX_FILENAME_LENGTH - strlen($ext)) . $ext;
        }
        
        $save_file_loc = $dir . '/' . $filename;

        if ($this->downloadBinaryFile($videoUrl, $save_file_loc)) {
            return $save_file_loc;
        }
        
        return false;
    }

    /**
     * Process links and save to text file
     * 
     * @param string $url URL to save
     * @param string $savedir Directory to save file
     * @param string $username Optional username for subdirectory
     * @return bool True on success, false on failure
     */
    public function processLinks($url, $savedir, $username = '')
    {
        if (!$this->isValidUrl($url) || !$this->isValidFilePath($savedir)) {
            return false;
        }
        
        $dir = $this->getSaveDirectory($savedir, $username);
        if (!$dir) {
            return false;
        }

        $filePath = $dir . '/links.txt';
        $fp = @fopen($filePath, 'a');
        if ($fp === false) {
            return false;
        }

        $result = fwrite($fp, $url . "\n");
        fclose($fp);
        
        return $result !== false;
    }

    /**
     * Process i.redd.it image link
     * 
     * @param string $url i.redd.it URL
     * @param string $savedir Directory to save file
     * @param string $username Optional username for subdirectory
     * @return string|false File path on success, false on failure
     */
    public function processIreddit($url, $savedir, $username = '')
    {
        if (strpos($url, 'i.redd.it') === false) {
            return false;
        }
        
        if (!$this->isValidUrl($url) || !$this->isValidFilePath($savedir)) {
            return false;
        }
        
        $imgname = basename(parse_url($url, PHP_URL_PATH));
        if (empty($imgname)) {
            return false;
        }
        
        $dir = $this->getSaveDirectory($savedir, $username);
        if (!$dir) {
            return false;
        }
        
        $img = $dir . '/' . $this->cleanFileName($imgname);
        
        if ($this->downloadBinaryFile($url, $img)) {
            if ($this->isFilePNG($img)) {
                $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
            }
            return $img;
        }
        
        return false;
    }

    /**
     * Alias for backward compatibility (typo in original)
     * 
     * @param string $url i.redd.it URL
     * @param string $savedir Directory to save file
     * @param string $username Optional username for subdirectory
     * @return string|false File path on success, false on failure
     */
    public function processIredit($url, $savedir, $username = '')
    {
        return $this->processIreddit($url, $savedir, $username);
    }

    /**
     * Sanitize Imgur URL
     * 
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeImgurUrl($url)
    {
        // Remove fragment
        $url = strtok($url, '#');
        // Normalize gallery to album
        $url = preg_replace('/imgur\.com\/gallery\//', 'imgur.com/a/', $url);
        // Normalize mobile and i.imgur to imgur.com
        $url = preg_replace('/https?:\/\/(?:m|i)\.imgur\.com/', 'https://imgur.com', $url);
        return $url;
    }

    /**
     * Get Imgur album images using API v3, fallback to /noscript
     * 
     * @param string $albumId Album ID
     * @param string $originalUrl Original URL for fallback
     * @return array Array of image URLs
     */
    public function getImgurAlbumImages($albumId, $originalUrl = '')
    {
        if (empty($albumId) || !preg_match('/^[a-zA-Z0-9]+$/', $albumId)) {
            return array();
        }
        
        // Try API v3 first
        $apiUrl = 'https://api.imgur.com/3/album/' . $albumId;
        $response = $this->getImgurApiData($apiUrl);
        
        if ($response && isset($response['data']['images']) && is_array($response['data']['images'])) {
            $imageUrls = array();
            foreach ($response['data']['images'] as $image) {
                if (isset($image['link']) && $this->isValidUrl($image['link'])) {
                    $imageUrls[] = $image['link'];
                }
            }
            if (!empty($imageUrls)) {
                return $imageUrls;
            }
        }

        // Fall back to /noscript method
        if (!empty($originalUrl) && $this->isValidUrl($originalUrl)) {
            $noscriptUrl = rtrim($originalUrl, '/') . '/noscript';
            return $this->getImgurAlbumFromNoscript($noscriptUrl);
        }
        
        return array();
    }

    /**
     * Get single Imgur image URL using API v1
     * 
     * @param string $imageId Image ID
     * @return string|false Image URL or false on failure
     */
    private function getImgurSingleImage($imageId)
    {
        if (empty($imageId) || !preg_match('/^[a-zA-Z0-9]+$/', $imageId)) {
            return false;
        }
        
        $apiUrl = 'https://api.imgur.com/post/v1/media/' . $imageId . '?include=media,adconfig,account';
        $response = $this->getImgurApiData($apiUrl);
        
        if ($response && isset($response['media']) && is_array($response['media']) && !empty($response['media'])) {
            $media = $response['media'][0];
            if (isset($media['ext']) && isset($media['id'])) {
                $ext = $media['ext'];
                if (strpos($ext, '.') !== 0) {
                    $ext = '.' . $ext;
                }
                // Prefer MP4 for GIFs if available
                if ($ext === '.gif' && isset($media['mp4']) && $this->isValidUrl($media['mp4'])) {
                    return $media['mp4'];
                }
                $imageUrl = 'https://i.imgur.com/' . $media['id'] . $ext;
                if ($this->isValidUrl($imageUrl)) {
                    return $imageUrl;
                }
            }
        }
        
        return false;
    }

    /**
     * Get data from Imgur API
     * 
     * @param string $url API URL
     * @return array|false API response data or false on failure
     */
    private function getImgurApiData($url)
    {
        if (!$this->isValidUrl($url)) {
            return false;
        }
        
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Client-ID ' . self::IMGUR_CLIENT_ID,
                'User-Agent: ' . self::REDDIT_USER_AGENT
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        return false;
    }

    /**
     * Get Imgur album images from /noscript page (fallback)
     * 
     * @param string $url /noscript URL
     * @return array Array of image URLs
     */
    private function getImgurAlbumFromNoscript($url)
    {
        if (!$this->isValidUrl($url)) {
            return array();
        }
        
        $data = $this->get_j($url);
        if (!$data) {
            return array();
        }
        
        $imageUrls = array();
        preg_match_all('/(?:https?:)?\/\/i\.imgur\.com\/([a-zA-Z0-9]+)(\.[a-zA-Z0-9]+)?/', $data, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                // Normalize to https://
                if (strpos($match, 'http') === 0) {
                    $imageUrl = str_replace('http://', 'https://', $match);
                } else {
                    $imageUrl = 'https:' . $match;
                }
                // Remove query parameters
                $imageUrl = strtok($imageUrl, '?');
                if ($this->isValidUrl($imageUrl)) {
                    $imageUrls[] = $imageUrl;
                }
            }
        }
        
        return array_unique($imageUrls);
    }

    /**
     * Get Imgur album (backward compatibility)
     * 
     * @param string $url Album URL
     * @return array Array of image URLs
     */
    public function getImgurAlbum($url)
    {
        if (!$this->isValidUrl($url)) {
            return array();
        }
        
        // Extract album ID from URL
        if (preg_match('/imgur\.com\/(?:a|gallery)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $albumId = $matches[1];
            return $this->getImgurAlbumImages($albumId, $url);
        }
        
        // Fall back to old method if URL doesn't match
        return $this->getImgurAlbumFromNoscript($url);
    }

    /**
     * Sanitize RedGifs URL
     * 
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeRedgifsUrl($url)
    {
        $url = str_replace('thumbs.', '', $url);
        $url = str_replace('/gifs/detail', '', $url);
        $url = str_replace('/amp', '', $url);
        $url = str_replace('gifdeliverynetwork.com', 'redgifs.com/watch', $url);
        return $url;
    }

    /**
     * Extract RedGifs ID from URL
     * 
     * @param string $url RedGifs URL
     * @return string|false GIF ID or false on failure
     */
    private function extractRedgifsId($url)
    {
        if (preg_match('/redgifs\.com\/watch\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $id = $matches[1];
            $parts = explode('-', $id);
            return $parts[0];
        }
        return false;
    }

    /**
     * Get RedGifs video URL from API
     * 
     * @param string $gifId GIF ID
     * @return string|false Video URL or false on failure
     */
    private function getRedgifsVideoUrl($gifId)
    {
        if (empty($gifId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $gifId)) {
            return false;
        }
        
        // Fetch auth token if needed
        if (empty(self::$redgifsAuthToken)) {
            $this->fetchRedgifsAuthToken();
        }

        // Get gif details from API
        $apiUrl = sprintf(self::REDGIFS_GIF_ENDPOINT, urlencode($gifId));
        $response = $this->getRedgifsApiData($apiUrl);
        
        if ($response && isset($response['gif'])) {
            $gif = $response['gif'];
            
            // Check if it's a gallery (multiple images)
            if (isset($gif['gallery']) && !empty($gif['gallery'])) {
                return false; // Gallery support not implemented
            }
            
            // Get HD URL
            if (isset($gif['urls']['hd']) && $this->isValidUrl($gif['urls']['hd'])) {
                return $gif['urls']['hd'];
            }
            // Fallback to SD URL
            if (isset($gif['urls']['sd']) && $this->isValidUrl($gif['urls']['sd'])) {
                return $gif['urls']['sd'];
            }
        }
        
        return false;
    }

    /**
     * Fetch RedGifs authentication token
     * 
     * @return void
     */
    private function fetchRedgifsAuthToken()
    {
        $response = $this->getRedgifsApiData(self::REDGIFS_AUTH_ENDPOINT, false);
        if ($response && isset($response['token']) && is_string($response['token'])) {
            self::$redgifsAuthToken = $response['token'];
        }
    }

    /**
     * Get data from RedGifs API
     * 
     * @param string $url API URL
     * @param bool $useAuth Whether to use authentication
     * @return array|false API response data or false on failure
     */
    private function getRedgifsApiData($url, $useAuth = true)
    {
        if (!$this->isValidUrl($url)) {
            return false;
        }
        
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        
        $headers = array(
            'User-Agent: ' . self::REDDIT_USER_AGENT,
            'Accept: application/json'
        );
        
        if ($useAuth && !empty(self::$redgifsAuthToken)) {
            $headers[] = 'Authorization: Bearer ' . self::$redgifsAuthToken;
        }
        
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        // If 401, try refreshing token once
        if ($httpCode === 401 && $useAuth) {
            self::$redgifsAuthToken = '';
            $this->fetchRedgifsAuthToken();
            if (!empty(self::$redgifsAuthToken)) {
                return $this->getRedgifsApiData($url, true);
            }
        }
        
        return false;
    }

    /**
     * Clean filename - remove special characters
     * 
     * @param string $name Filename to clean
     * @return string Cleaned filename
     */
    public function cleanFileName($name)
    {
        if (!is_string($name)) {
            return '';
        }
        $name = preg_replace('/[^a-zA-Z0-9.]/', '', $name);
        if (strlen($name) > self::MAX_FILENAME_LENGTH) {
            $name = substr($name, 0, self::MAX_FILENAME_LENGTH);
        }
        return $name;
    }

    /**
     * Check if file is PNG format
     * 
     * @param string $filename File path
     * @return bool True if PNG, false otherwise
     */
    public function isFilePNG($filename)
    {
        if (!is_string($filename) || !file_exists($filename)) {
            return false;
        }
        
        $png_header = array(137, 80, 78, 71, 13, 10, 26, 10);
        $f = @fopen($filename, 'rb');
        if ($f === false) {
            return false;
        }
        
        for ($i = 0; $i < 8; $i++) {
            $byte = @fread($f, 1);
            if ($byte === false || strlen($byte) === 0) {
                fclose($f);
                return false;
            }
            if (ord($byte) !== $png_header[$i]) {
                fclose($f);
                return false;
            }
        }
        fclose($f);
        return true;
    }

    /**
     * Convert PNG to JPG
     * 
     * @param string $originalFile Source PNG file
     * @param string $outputFile Destination JPG file
     * @param int $quality JPEG quality (0-100)
     * @return bool True on success, false on failure
     */
    public function png2jpg($originalFile, $outputFile, $quality)
    {
        if (!file_exists($originalFile) || !is_readable($originalFile)) {
            return false;
        }
        
        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
            return false;
        }
        
        $quality = max(0, min(100, (int)$quality));
        
        $image = @imagecreatefrompng($originalFile);
        if ($image === false) {
            return false;
        }
        
        $result = @imagejpeg($image, $outputFile, $quality);
        imagedestroy($image);
        
        if ($result && file_exists($outputFile)) {
            @unlink($originalFile);
            return true;
        }
        
        return false;
    }

    /**
     * Create folder for saving files
     * 
     * @param string $savedir Base directory
     * @param string $username Username for subdirectory
     * @return void
     */
    public function createFolder($savedir, $username)
    {
        $dir = $this->getSaveDirectory($savedir, $username);
        // Directory creation is handled in getSaveDirectory
    }

    /**
     * Create directory with proper permissions
     * 
     * @param string $folderPath Directory path
     * @return bool True on success, false on failure
     */
    public function mkdir($folderPath)
    {
        if (!is_string($folderPath) || empty($folderPath)) {
            return false;
        }
        
        if (!is_dir($folderPath)) {
            return @mkdir($folderPath, 0755, true);
        }
        return true;
    }

    /**
     * Get save directory path, creating if necessary
     * 
     * @param string $savedir Base directory
     * @param string $username Optional username
     * @return string|false Directory path or false on failure
     */
    private function getSaveDirectory($savedir, $username = '')
    {
        if (!is_string($savedir) || empty($savedir)) {
            return false;
        }
        
        $dir = (!empty($username) && is_string($username)) 
            ? rtrim($savedir, '/') . '/' . $this->sanitizeString($username) 
            : rtrim($savedir, '/');
        
        if (!$this->isValidFilePath($dir)) {
            return false;
        }
        
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }
        
        return $dir;
    }

    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidUrl($url)
    {
        if (!is_string($url) || empty($url)) {
            return false;
        }
        
        // Basic URL validation
        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        if ($filtered === false) {
            return false;
        }
        
        // Only allow http/https
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, array('http', 'https'), true);
    }

    /**
     * Validate file path for security
     * 
     * @param string $filePath File path to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidFilePath($filePath)
    {
        if (!is_string($filePath) || empty($filePath)) {
            return false;
        }
        
        // Prevent directory traversal
        if (strpos($filePath, '..') !== false) {
            return false;
        }
        
        // Prevent null bytes
        if (strpos($filePath, "\0") !== false) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate downloaded file content
     * 
     * @param string $filePath File path
     * @param string $contentType Content-Type header
     * @return bool True if valid, false otherwise
     */
    private function validateFileContent($filePath, $contentType = '')
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }
        
        $firstBytes = @fread($handle, 512);
        fclose($handle);
        
        if ($firstBytes === false || strlen($firstBytes) < 4) {
            return false;
        }
        
        // Check for HTML/XML markers
        if (preg_match('/^\s*<(!DOCTYPE|html|HTML|xml|XML)/i', $firstBytes)) {
            return false;
        }
        
        // Check for common image/video file signatures
        $isValid = false;
        
        // JPEG: FF D8 FF
        if (substr($firstBytes, 0, 3) === "\xFF\xD8\xFF") {
            $isValid = true;
        }
        // PNG: 89 50 4E 47
        elseif (substr($firstBytes, 0, 4) === "\x89\x50\x4E\x47") {
            $isValid = true;
        }
        // GIF: 47 49 46 38
        elseif (substr($firstBytes, 0, 4) === "GIF8") {
            $isValid = true;
        }
        // WebP: RIFF...WEBP
        elseif (substr($firstBytes, 0, 4) === "RIFF" && strpos($firstBytes, "WEBP") !== false) {
            $isValid = true;
        }
        // MP4: ftyp
        elseif (substr($firstBytes, 4, 4) === "ftyp") {
            $isValid = true;
        }
        // Check Content-Type if signature doesn't match
        elseif (!empty($contentType) && 
                (strpos($contentType, 'image/') !== false || strpos($contentType, 'video/') !== false)) {
            $isValid = true;
        }
        
        return $isValid;
    }

    /**
     * Sanitize string input
     * 
     * @param string $input String to sanitize
     * @return string Sanitized string
     */
    private function sanitizeString($input)
    {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove null bytes and control characters
        $input = str_replace("\0", '', $input);
        $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        
        return trim($input);
    }
}
