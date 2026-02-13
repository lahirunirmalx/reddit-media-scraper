<?php

/*
	Reddit.com Scraper PHP Class
*/
namespace rms;
class RedditScraper{
    private $lastRequestTime = 0;
    private static $redgifsAuthToken = '';
    private const SLEEP_TIME = 2000; // 2 seconds in milliseconds
    private const REDDIT_USER_AGENT = 'RipMe:github.com/RipMeApp/ripme:1.0 (by /u/metaprime and /u/ineedmorealts)';
    CONST BASE_URL = 'https://www.reddit.com';
    private const IMGUR_CLIENT_ID = '546c25a59c58ad7';
    private const REDGIFS_AUTH_ENDPOINT = 'https://api.redgifs.com/v2/auth/temporary';
    private const REDGIFS_GIF_ENDPOINT = 'https://api.redgifs.com/v2/gifs/%s';

    /*
        function: scrape
        returns an array of titles,article urls
    */
    function scrape($base_url, $max_pages){
        $entries = array();
        $after = null;
        for($i=0;$i<=$max_pages;$i++) {
            
            $scrape_url = ($i==0) ? $base_url : $base_url.'?after='.$after;
            
            $data = json_decode($this->get_j($scrape_url),true);
            
            if (!isset($data['data']) || !isset($data['data']['children'])) {
                break;
            }
            
            $after = null;
            if (isset($data['data']['after']) && !empty($data['data']['after'])) {
                $after = $data['data']['after'];
            }

            foreach($data['data']['children'] as $child) {
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
                    $title = isset($childData['title']) ? $childData['title'] : '';
                    $author = isset($childData['author']) ? $childData['author'] : '';
                    $id = isset($childData['id']) ? $childData['id'] : '';
                    
                    foreach($galleryItems as $item) {
                        $mediaId = $item['media_id'];
                        if (isset($mediaMetadata[$mediaId])) {
                            $media = $mediaMetadata[$mediaId];
                            if (isset($media['s'])) {
                                $url = isset($media['s']['gif']) ? $media['s']['gif'] : $media['s']['u'];
                                $url = str_replace('&amp;', '&', $url);
                                array_push($entries, array('url'=>$url, 'title'=>$title, 'author'=>$author, 'id'=>$id));
                            }
                        }
                    }
                } else if (isset($childData['url']) && isset($childData['title']) && isset($childData['author'])) {
                    $url = $childData['url'];
                    $title = $childData['title'];
                    $author = $childData['author'];
                    $id = isset($childData['id']) ? $childData['id'] : '';
                    array_push($entries, array('url'=>$url, 'title'=>$title, 'author'=>$author, 'id'=>$id));
                }
            }
            
            if ($after === null) {
                break;
            }
        }
        
        if(count($entries)>0) return $entries;
            
        return false;
    }

    function scrapeSubReddit($section, $max_pages = 1)
    {
        $base_url = self::BASE_URL.'/r/' . $section . '.json';
        return $this->scrape($base_url, $max_pages);
    }

    function scrapeUser($section, $max_pages=1)
    {
        $base_url = self::BASE_URL.'/user/' . $section . '.json';
        return $this->scrape($base_url, $max_pages);
    }

    function get_j($url){
        // Rate limiting: wait 2 seconds between requests
        $timeDiff = (microtime(true) * 1000) - $this->lastRequestTime;
        if ($timeDiff < self::SLEEP_TIME) {
            $sleepTime = (self::SLEEP_TIME - $timeDiff) / 1000;
            usleep((int)($sleepTime * 1000000));
        }
        $this->lastRequestTime = microtime(true) * 1000;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
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
        
        if ($error) {
            return false;
        }
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return $response;
    }

    // Backward compatibility alias
    function processRequest($url){
        return $this->get_j($url);
    }

    /*
        function: downloadBinaryFile
        downloads binary file (images, videos) directly to file path
        returns true on success, false on failure
    */
    private function downloadBinaryFile($url, $filePath)
    {
        // Rate limiting: wait 2 seconds between requests
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
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
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

        if ($error || !$success) {
            @unlink($filePath);
            return false;
        }

        if ($httpCode !== 200) {
            @unlink($filePath);
            return false;
        }

        // Verify file is not empty and is actually binary (not HTML)
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            @unlink($filePath);
            return false;
        }

        // Check if file starts with HTML (common error case)
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            $firstBytes = fread($handle, 512);
            fclose($handle);
            
            // Check for HTML/XML markers
            if (preg_match('/^\s*<(!DOCTYPE|html|HTML|xml|XML)/i', $firstBytes)) {
                @unlink($filePath);
                return false;
            }
            
            // Check for common image file signatures
            $isImage = false;
            // JPEG: FF D8 FF
            if (substr($firstBytes, 0, 3) === "\xFF\xD8\xFF") {
                $isImage = true;
            }
            // PNG: 89 50 4E 47
            elseif (substr($firstBytes, 0, 4) === "\x89\x50\x4E\x47") {
                $isImage = true;
            }
            // GIF: 47 49 46 38
            elseif (substr($firstBytes, 0, 4) === "GIF8") {
                $isImage = true;
            }
            // WebP: RIFF...WEBP
            elseif (substr($firstBytes, 0, 4) === "RIFF" && strpos($firstBytes, "WEBP") !== false) {
                $isImage = true;
            }
            
            if (!$isImage && $contentType && strpos($contentType, 'image/') === false && strpos($contentType, 'video/') === false) {
                @unlink($filePath);
                return false;
            }
        }

        return true;
    }

    /*
        function: processImgurLink
        processes imgur urls and downloads pictures to specified directory
    */
    function processImgurLink($url, $savedir, $username = '')
    {
        $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Normalize URL
        $url = $this->sanitizeImgurUrl($url);

        // Handle direct i.imgur.com links
        if (strstr($url, 'i.imgur.com')) {
            $imgname = explode('/', $url);
            $imgname = end($imgname);
            // Remove query parameters
            if (strpos($imgname, '?') !== false) {
                $imgname = substr($imgname, 0, strpos($imgname, '?'));
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
                    $imgname = explode('/', $imageUrl);
                    $imgname = end($imgname);
                    if (strpos($imgname, '?') !== false) {
                        $imgname = substr($imgname, 0, strpos($imgname, '?'));
                    }
                    $img = $dir . '/' . $this->cleanFileName($imgname);
                    if ($this->downloadBinaryFile($imageUrl, $img)) {
                        if ($this->isFilePNG($img)) {
                            $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                        }
                        $success = true;
                    }
                }
                return $success ? true : false;
            }
            return false;
        }

        // Handle single image (imgur.com/XXXXX format)
        if (preg_match('/imgur\.com\/([a-zA-Z0-9]{5,})/', $url, $matches)) {
            $imageId = $matches[1];
            $imageUrl = $this->getImgurSingleImage($imageId);
            if ($imageUrl) {
                $imgname = explode('/', $imageUrl);
                $imgname = end($imgname);
                if (strpos($imgname, '?') !== false) {
                    $imgname = substr($imgname, 0, strpos($imgname, '?'));
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

    function processImgurLinkGifv($url, $savedir, $username = '')
    {
        if (strstr($url, 'i.imgur')) {
            $url = str_replace('.gifv', '.mp4', $url);
            $imgname = explode('/', $url);
            $imgname = end($imgname);
            $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $img = $dir . '/' . $this->cleanFileName($imgname);
            // save the image locally
            $ch = curl_init($url);
            $save_file_loc = $img;
            $fp = fopen($save_file_loc, 'wb');
            curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            //var_dump(curl_getinfo($ch));

            curl_close($ch);
            fclose($fp);
            return $save_file_loc;
        }
    }


    function processRedGiff($url, $savedir, $username = '')
    {
        if (!strstr($url, 'redgifs.com') && !strstr($url, 'gifdeliverynetwork.com')) {
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
        if (!$videoUrl) {
            return false;
        }

        // Prepare directory
        $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Determine filename using gif ID
        // Get extension from video URL or default to mp4
        $ext = '.mp4';
        if (preg_match('/\.([a-zA-Z0-9]+)(?:\?|$)/', $videoUrl, $matches)) {
            $ext = '.' . $matches[1];
        }
        
        $filename = $this->cleanFileName($gifId) . $ext;
        $save_file_loc = $dir . '/' . $filename;

        // Download video file
        $ch = curl_init($videoUrl);
        $fp = fopen($save_file_loc, 'wb');
        curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: ' . self::REDDIT_USER_AGENT
        ));
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($success && $httpCode === 200 && file_exists($save_file_loc) && filesize($save_file_loc) > 0) {
            return $save_file_loc;
        }

        // Clean up on failure
        if (file_exists($save_file_loc)) {
            @unlink($save_file_loc);
        }
        
        return false;
    }

    /*
        function: sanitizeRedgifsUrl
        normalizes redgifs URLs to standard format
    */
    private function sanitizeRedgifsUrl($url)
    {
        // Remove thumbs. prefix
        $url = str_replace('thumbs.', '', $url);
        // Remove /gifs/detail
        $url = str_replace('/gifs/detail', '', $url);
        // Remove /amp
        $url = str_replace('/amp', '', $url);
        // Convert gifdeliverynetwork.com to redgifs.com/watch
        $url = str_replace('gifdeliverynetwork.com', 'redgifs.com/watch', $url);
        return $url;
    }

    /*
        function: extractRedgifsId
        extracts gif ID from redgifs URL
    */
    private function extractRedgifsId($url)
    {
        // Pattern: redgifs.com/watch/ID or redgifs.com/watch/ID-title
        if (preg_match('/redgifs\.com\/watch\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $id = $matches[1];
            // Split on '-' and take first part (ID before title)
            $parts = explode('-', $id);
            return $parts[0];
        }
        return false;
    }

    /*
        function: getRedgifsVideoUrl
        fetches video URL from RedGifs API
    */
    private function getRedgifsVideoUrl($gifId)
    {
        // Fetch auth token if needed
        if (empty(self::$redgifsAuthToken)) {
            $this->fetchRedgifsAuthToken();
        }

        // Get gif details from API
        $apiUrl = sprintf(self::REDGIFS_GIF_ENDPOINT, $gifId);
        $response = $this->getRedgifsApiData($apiUrl);
        
        if ($response && isset($response['gif'])) {
            $gif = $response['gif'];
            
            // Check if it's a gallery (multiple images)
            if (isset($gif['gallery']) && !empty($gif['gallery'])) {
                // For galleries, we'd need to fetch gallery endpoint
                // For now, just get the first gif's URL
                // TODO: Handle galleries properly
                return false;
            }
            
            // Get HD URL
            if (isset($gif['urls']['hd'])) {
                return $gif['urls']['hd'];
            }
            // Fallback to SD URL
            if (isset($gif['urls']['sd'])) {
                return $gif['urls']['sd'];
            }
        }
        
        return false;
    }

    /*
        function: fetchRedgifsAuthToken
        fetches temporary auth token from RedGifs API
    */
    private function fetchRedgifsAuthToken()
    {
        $response = $this->getRedgifsApiData(self::REDGIFS_AUTH_ENDPOINT, false);
        if ($response && isset($response['token'])) {
            self::$redgifsAuthToken = $response['token'];
        }
    }

    /*
        function: getRedgifsApiData
        fetches data from RedGifs API with proper authentication
    */
    private function getRedgifsApiData($url, $useAuth = true)
    {
        $ch = curl_init($url);
        $headers = array(
            'User-Agent: ' . self::REDDIT_USER_AGENT,
            'Accept: application/json'
        );
        
        if ($useAuth && !empty(self::$redgifsAuthToken)) {
            $headers[] = 'Authorization: Bearer ' . self::$redgifsAuthToken;
        }
        
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
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

    function processLinks($url, $savedir, $username = '')
    {
        $imgname = explode('/', $url);
        $imgname = end($imgname);
        $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $img = $dir . '/links.txt';
        $save_file_loc = $img;
        $fp = @fopen($save_file_loc, 'a');
        if ($fp === false) {
            return false;
        }

        fwrite($fp, $url . "\n");
        fclose($fp);
    }


    function processIreddit($url, $savedir, $username = '')
    {
        if (strstr($url, 'i.redd.it')) {
            // this is a single picture, grab the location
            $imgname = explode('/', $url);
            $imgname = end($imgname);
            // Remove query parameters
            if (strpos($imgname, '?') !== false) {
                $imgname = substr($imgname, 0, strpos($imgname, '?'));
            }
            $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $img = $dir . '/' . $this->cleanFileName($imgname);
            // save the image locally
            if ($this->downloadBinaryFile($url, $img)) {
                if ($this->isFilePNG($img)) {
                    $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                }
                return $img;
            }
            return false;
        }
    }

    // Alias for backward compatibility (typo in original)
    function processIredit($url, $savedir, $username = '')
    {
        return $this->processIreddit($url, $savedir, $username);
    }

    /*
        function: sanitizeImgurUrl
        normalizes imgur URLs to standard format
    */
    private function sanitizeImgurUrl($url)
    {
        // Remove fragment
        if (strpos($url, '#') !== false) {
            $url = substr($url, 0, strpos($url, '#'));
        }
        // Normalize gallery to album
        $url = preg_replace('/imgur\.com\/gallery\//', 'imgur.com/a/', $url);
        // Normalize mobile and i.imgur to imgur.com
        $url = preg_replace('/https?:\/\/(?:m|i)\.imgur\.com/', 'https://imgur.com', $url);
        return $url;
    }

    /*
        function: getImgurAlbumImages
        returns a list of image URLs from an imgur album using API v3, falls back to /noscript
    */
    function getImgurAlbumImages($albumId, $originalUrl = '')
    {
        // Try API v3 first
        $apiUrl = 'https://api.imgur.com/3/album/' . $albumId;
        $response = $this->getImgurApiData($apiUrl);
        
        if ($response && isset($response['data']['images']) && is_array($response['data']['images'])) {
            $imageUrls = array();
            foreach ($response['data']['images'] as $image) {
                if (isset($image['link'])) {
                    $imageUrls[] = $image['link'];
                }
            }
            if (!empty($imageUrls)) {
                return $imageUrls;
            }
        }

        // Fall back to /noscript method
        if (!empty($originalUrl)) {
            $noscriptUrl = rtrim($originalUrl, '/') . '/noscript';
            return $this->getImgurAlbumFromNoscript($noscriptUrl);
        }
        
        return array();
    }

    /*
        function: getImgurSingleImage
        gets single image URL from imgur using API v1
    */
    private function getImgurSingleImage($imageId)
    {
        $apiUrl = 'https://api.imgur.com/post/v1/media/' . $imageId . '?include=media,adconfig,account';
        $response = $this->getImgurApiData($apiUrl);
        
        if ($response && isset($response['media']) && is_array($response['media']) && !empty($response['media'])) {
            $media = $response['media'][0];
            if (isset($media['ext'])) {
                $ext = $media['ext'];
                if (strpos($ext, '.') !== 0) {
                    $ext = '.' . $ext;
                }
                // Prefer MP4 for GIFs if available
                if ($ext === '.gif' && isset($media['mp4'])) {
                    return $media['mp4'];
                }
                return 'https://i.imgur.com/' . $media['id'] . $ext;
            }
        }
        
        return false;
    }

    /*
        function: getImgurApiData
        fetches data from Imgur API with proper authentication
    */
    private function getImgurApiData($url)
    {
        $ch = curl_init($url);
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

    /*
        function: getImgurAlbumFromNoscript
        fallback method: parses /noscript page for image URLs
    */
    private function getImgurAlbumFromNoscript($url)
    {
        $data = $this->get_j($url);
        if (!$data) {
            return array();
        }
        
        $imageUrls = array();
        // Match imgur image URLs - handle both http:// and // formats
        // Pattern matches: http://i.imgur.com/XXXXX.ext or //i.imgur.com/XXXXX.ext
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
                if (strpos($imageUrl, '?') !== false) {
                    $imageUrl = substr($imageUrl, 0, strpos($imageUrl, '?'));
                }
                $imageUrls[] = $imageUrl;
            }
        }
        
        return array_unique($imageUrls);
    }

    /*
        function: getImgurAlbum
        returns a list of images in an imgur album link (backward compatibility)
    */
    function getImgurAlbum($url)
    {
        // Extract album ID from URL
        if (preg_match('/imgur\.com\/(?:a|gallery)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $albumId = $matches[1];
            return $this->getImgurAlbumImages($albumId, $url);
        }
        
        // Fall back to old method if URL doesn't match (for /noscript URLs)
        $data = $this->get_j($url);
        if (!$data) {
            return array();
        }
        
        preg_match_all('/(?:https?:)?\/\/i\.imgur\.com\/([a-zA-Z0-9]+)(\.[a-zA-Z0-9]+)?/', $data, $matches);
        $urls = array();
        foreach ($matches[0] as $match) {
            if (strpos($match, 'http') === 0) {
                $imageUrl = str_replace('http://', 'https://', $match);
            } else {
                $imageUrl = 'https:' . $match;
            }
            // Remove query parameters
            if (strpos($imageUrl, '?') !== false) {
                $imageUrl = substr($imageUrl, 0, strpos($imageUrl, '?'));
            }
            $urls[] = $imageUrl;
        }
        return array_unique($urls);
    }

    /*
        function: cleanFileName
        returns a name with no special characters, only alphanumeric characters and periods.
    */
    function cleanFileName($name)
    {
        $name = preg_replace('/[^a-zA-Z0-9.]/', '', $name);
        //$name = substr($name,0,9);
        return $name;
    }

    /*
        function isFilePNG
        returns true or false whether file has png headers
    */
    function isFilePNG($filename)
    {
        if (!file_exists($filename)) {
            return false;
        }
        $png_header = array(137, 80, 78, 71, 13, 10, 26, 10);
        $f = fopen($filename, 'r');
        for ($i = 0; $i < 8; $i++) {
            $byte = ord(fread($f, 1));
            if ($byte !== $png_header[$i]) {
                fclose($f);
                return false;
            }
        }
        fclose($f);
        return true;
    }

    /*
        function: png2jpg
        converts a png image to a jpg image
    */
    function png2jpg($originalFile, $outputFile, $quality)
    {
        $image = imagecreatefrompng($originalFile);
        imagejpeg($image, $outputFile, $quality);
        imagedestroy($image);
    }

    /**
     * @param $savedir
     * @param $username
     * @return void
     */
    public function createFolder($savedir, $username)
    {
        $dir = (!empty($username)) ? $savedir . '/' . $username : $savedir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * @param $folderPath
     * @return void
     */
    public function mkdir($folderPath)
    {
        if (!is_dir($folderPath)) {
            @mkdir($folderPath, 0755, true);
        }
    }
}
