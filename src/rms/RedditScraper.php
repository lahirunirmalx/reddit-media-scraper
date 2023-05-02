<?php

/*
	Reddit.com Scraper PHP Class
*/
namespace rms;
class RedditScraper{
CONST BASE_URL = 'http://www.reddit.com';
CONST HTTP_HEADERS = array(
    'authority: www.reddit.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: en-US,en;q=0.9',
    'cache-control: max-age=0',
    'sec-ch-ua: "Chromium";v="112", "Google Chrome";v="112", "Not:A-Brand";v="99"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Linux"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: none',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1',
    'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36'
);

    /*
        function: scrape
        returns an array of titles,article urls
    */
    function scrape($base_url, $max_pages){
        $entries = array();
        for ($i = 1; $i <= $max_pages; $i++) {

            $scrape_url = ($i == 0) ? $base_url : $base_url . '?after=' . $after;

            $data = json_decode($this->processRequest($scrape_url), true);
            $after = $data['data']['after'];

            foreach ($data['data']['children'] as $child) {
                list($url, $title, $author) = array(
                    $child['data']['url'],
                    $child['data']['title'],
                    $child['data']['author']
                );
                array_push($entries, array('url' => $url, 'title' => $title, 'author' => $author));
            }
        }

        if (count($entries) > 0) {
            return $entries;
        }

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

    function processRequest($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, self::HTTP_HEADERS);
        $response = curl_exec($ch);

        curl_close($ch);
        return $response;
    }

    /*
        function: processImgurLink
        processes imgur urls and downloads pictures to specified directory
    */
    function processImgurLink($url, $savedir, $username = '')
    {
        if (strstr($url, 'i.imgur')) {
            // this is a single picture, grab the location
            $imgname = explode(DIRECTORY_SEPARATOR, $url);
            $imgname = end($imgname);
            $this->createFolder($savedir, $username);
            $img = sprintf("%s%s%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR,
                $this->cleanFileName($imgname));
            // save the image locally
            if (file_put_contents($img, $this->processRequest($url))) {
                if (file_put_contents($img, $this->processRequest($url))) {
                    if ($this->isFilePNG($img)) {
                        $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                    }
                    return $img;
                }
            }
            return false;
        } elseif (strstr($url, 'imgur.com/a')) {
            // this is an album
            $url .= '/noscript';
            $urls = $this->getImgurAlbum($url);
            foreach ($urls as $url) {
                $imgname = explode(DIRECTORY_SEPARATOR, $url);
                $imgname = end($imgname);
                $this->createFolder($savedir, $username);
                $img = sprintf("%s%s%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR,
                    $this->cleanFileName($imgname));
                // save the image locally
                if (file_put_contents($img, $this->processRequest($url))) {
                    if ($this->isFilePNG($img)) {
                        $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                    }
                    return true;
                }
            }
            return false;
        } else {
            // this is a single picture
            $imgname = explode(DIRECTORY_SEPARATOR, $url);
            $imgname = end($imgname);
            $url = 'http://imgur.com/download/' . $imgname;
            $this->createFolder($savedir, $username);
            $img = sprintf("%s%s%s%s%s.jpg", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR, $imgname);
            //save the image locally
            if (file_put_contents($img, $this->processRequest($url))) {
                if ($this->isFilePNG($img)) {
                    $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                }
                return $img;
            }
            return false;
        }
    }

    function processImgurLinkGifv($url, $savedir, $username = '')
    {
        if (strstr($url, 'i.imgur')) {
            $url = str_replace('.gifv', '.mp4', $url);
            $imgname = explode(DIRECTORY_SEPARATOR, $url);
            $imgname = end($imgname);
            $this->createFolder($savedir, $username);
            $img = sprintf("%s%s%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR,
                $this->cleanFileName($imgname));
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
        if (strstr($url, 'redgifs.com')) {

            $imgname = explode(DIRECTORY_SEPARATOR, $url);
            $imgname = end($imgname);
            $this->createFolder($savedir, $username);

            $img = $savedir . DIRECTORY_SEPARATOR . $username . '/redgifs.txt';
            $save_file_loc = $img;
            $fp = fopen($save_file_loc, 'a');

            fwrite($fp, $url . "\n");

            $img = sprintf("%s%s%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR,
                $this->cleanFileName($imgname));
            // save the image locally
            $ch = curl_init($url);
            $save_file_loc = $img;


            $fp = fopen($save_file_loc, 'wb');
            curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            //curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, array('User-Agent : Mozilla/5.0'));
            $data = curl_exec($ch);
            $urlParts = explode('contentUrl":"', $data)[1];
            $urlPart = explode(',"creator', $urlParts)[0];
            $urlPart = str_replace('"', '', $urlPart);
            $urlPart = str_replace('amp;', '', $urlPart);
            curl_close($ch);

            $ch = curl_init($urlPart);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, array('User-Agent : Mozilla/5.0'));
            var_dump(curl_getinfo($ch));
            curl_close($ch);
            fclose($fp);
        }
    }

    function processLinks($url, $savedir, $username = '')
    {
        $imgname = explode(DIRECTORY_SEPARATOR, $url);
        $imgname = end($imgname);
        $this->createFolder($savedir, $username);

        $img = sprintf("%s%s%s/links.txt", $savedir, DIRECTORY_SEPARATOR, $username);
        $save_file_loc = $img;
        $fp = fopen($save_file_loc, 'a');

        fwrite($fp, $url . "\n");
        fclose($fp);


    }


    function processIreddit($url, $savedir, $username = '')
    {
        if (strstr($url, 'i.redd.it')) {
            // this is a single picture, grab the location
            $imgname = explode(DIRECTORY_SEPARATOR, $url);
            $imgname = end($imgname);
            $this->createFolder($savedir, $username);
            $img = sprintf("%s%s%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username, DIRECTORY_SEPARATOR,
                $this->cleanFileName($imgname));
            // save the image locally
            if (file_put_contents($img, $this->processRequest($url))) {
                if (file_put_contents($img, $this->processRequest($url))) {
                    if ($this->isFilePNG($img)) {
                        $this->png2jpg($img, str_replace('.jpg', '.jpeg', $img), 100);
                    }
                    return $img;
                }
            }
            return false;
        }
    }

    /*
        function: getImgurAlbum
        returns a list of images in an imgur album link
    */
    function getImgurAlbum($url)
    {
        $data = file_get_contents($url);
        preg_match_all('/http\:\/\/i\.imgur\.com\/(.*)\.jpg/', $data, $matches);
        return $matches = array_unique($matches[0]);
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
        $folderPath = sprintf("%s%s%s", $savedir, DIRECTORY_SEPARATOR, $username);
        $this->mkdir($folderPath);
    }

    /**
     * @param $folderPath
     * @return void
     */
    public function mkdir($folderPath)
    {
        if (!is_dir($folderPath)) {
            mkdir($folderPath);
        }
    }


}


