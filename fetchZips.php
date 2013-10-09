<?php

$accessTokenFilename = 'accessToken.php';

if (file_exists($accessTokenFilename) == false) {
    echo "Please create ".$accessTokenFilename." with your Git access token.";
    exit(-1);
}

require_once $accessTokenFilename;

/**
 * The list of github repositories to archive.
 */
$repos = array(
    "Danack/Jig",
    "Danack/Auryn",
    "php-fig/log",
    "necolas/normalize.css",
    "Seldaek/monolog",
    "sebastianbergmann/phpunit",
    "sebastianbergmann/php-code-coverage",
    "sebastianbergmann/php-file-iterator",
    "sebastianbergmann/php-text-template",
    "sebastianbergmann/php-timer",
    "sebastianbergmann/phpunit-mock-objects",
    "sebastianbergmann/php-code-coverage",
    "sebastianbergmann/php-token-stream",

    "Danack/PHP-to-Javascript", //base-reality/php-to-javascript
    "Danack/intahwebz-core", //intahwebz/core
    "Danack/Configurator",// intahwebz/configurator
    "Danack/FlickrGuzzle", //intahwebz/flickrguzzle
    "Danack/Jig",//intahwebz/jig dev-master
    "Danack/mb_extra",//intahwebz/mb_extra
    "Danack/intahwebz-utils",//intahwebz/utils
    "guzzle/guzzle",
    
    "maximebf/php-debugbar",//maximebf/debugbar
    "flack/UniversalFeedCreator",//openpsa/universalfeedcreator
    "nrk/predis",//predis/predis
    "rdlowrey/Amp", //rdlowrey/amp
    "rdlowrey/Alert",
    "nikic/php-parser",

    "symfony/EventDispatcher",
    
    "zendframework/Component_ZendStdlib",
    "zendframework/Component_ZendInputFilter", //zendframework/zend-inputfilter
    "zendframework/Component_ZendFilter", //zendframework/zend-filter
    "zendframework/Component_ZendValidator", //zendframework/zend-validator
    "zendframework/Component_ZendPermissionsAcl", //zendframework/zend-permissions-acl

    "symfony/yaml",
);

$zipsDirectory = "zips/";

foreach ($repos as $repo) {
    cacheRepo($repo, $zipsDirectory, $accessToken);
}

/**
 * Downloads all the zipball of all the tagged versions in Github. 
 * 
 * @param $repo
 * @param $zipsDirectory
 * @param null $accessToken
 * @throws Exception
 */
function cacheRepo($repo, $zipsDirectory, $accessToken = null) {

    $ignoreList = array();
    
    if (file_exists("ignoreList.txt") == true) {
        $ignoreList = file("ignoreList.txt", FILE_IGNORE_NEW_LINES);
    }
    
    $tagPath = "https://api.github.com/repos/".$repo."/tags";
    
    if ($accessToken) {
        $tagPath .= '?access_token='.$accessToken;
    }

    $fileContents = file_get_contents($tagPath);
    
    if (!$fileContents) {
        echo "Failed to get tag list for repo ".$repo;
        exit(0);
    }

    $tagContentArray = json_decode($fileContents, true);

    $count = 0;

    foreach ($tagContentArray as $tagContent) {

        $tagName = $tagContent['name'];
        $zendReleasePrefix = 'release-';
        
        if (strpos($tagName, $zendReleasePrefix) === 0) {
            $tagName = substr($tagName, strlen($zendReleasePrefix));
        }
        
        $filename = str_replace("/", "_", $repo);
        $filename .= '_'.$tagName.'.zip';

        $filename = $zipsDirectory.$filename;

        if (in_array($filename, $ignoreList) == true) {
            //echo "File $filename is in the ignore list, skipping.\n";
            continue;
        }

        if (file_exists($filename) == false) {
            $url = $tagContent['zipball_url'];

            if ($accessToken) {
                $url .= '?access_token='.$accessToken;
            }

            downloadFile($url, $filename);
        }

        try {
            modifyZipfile($filename, $tagName, $repo);
        }
        catch(\Exception $e) {
            throw new \Exception("Error processing $filename: ".$e->getMessage(), $e->getCode(), $e);
        }

        //Hack to only do a few files at once.
        $count++;
        if ($count > 10) {
            return;
        }
    }
}


/**
 * downloadFile - amazingly, downloads a file.
 * @param $url
 * @param $filename
 */
function downloadFile($url, $filename) {

    echo "Downloading $url to  $filename \n";
    $fp = fopen($filename, 'w');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $data = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}


/**
 * Opens a zip file and modifies the root composer.json to put the version's
 * tag in there, so that it's a valid zip artifact.
 * @param $zipFilename
 * @param $tag
 * @throws Exception
 */
function modifyZipfile($zipFilename, $tag, $repoName) {

    $fileToModify = 'composer.json';

    $zip = new ZipArchive;

    if ($zip->open($zipFilename) === TRUE) {
        $shortestIndex = -1;
        $shortestIndexLength = -1;
        $fileToReplace = null;

        for( $i = 0; $i < $zip->numFiles; $i++ ){
            $stat = $zip->statIndex( $i );

            if (basename($stat['name']) == 'composer.json'){
                $length = strlen($stat['name']);
                if ($shortestIndex == -1 || $length < $shortestIndexLength) {
                    $shortestIndex = $i;
                    $shortestIndexLength = $length;
                    $fileToReplace = $stat['name'];
                }
            }
        }

        if ($shortestIndex == -1) {
            //echo "Failed to find the composer.json file, delete $zipFilename \n";
            //markFileToSkip($zipFilename);
            //$zip->close();
            //return ;
            echo "Failed to find the composer.json file, creating it for $zipFilename \n";
            $contents = generateComposerJSON($repoName, $tag);
            $zip->addFromString("composer.json", $contents);
        }
        else {
            //echo "Found the file at $shortestIndex\n";
            $contents = $zip->getFromIndex($shortestIndex);
    
            $modifiedContents = modifyJson($contents, $tag);
    
            if ($modifiedContents) {
                echo "Adding version tag $tag to file $zipFilename.\n";
                $zip->deleteName($fileToReplace);    //Delete the old...
                $zip->addFromString($fileToReplace, $modifiedContents); //Write the new...
            }
            else{
                //File already contained a composer.json with a version entry in it
            }
        }
        
        $zip->close();//And write back to the filesystem.
    }
    else {
        echo 'failed to open';
        throw new \Exception("Failed to open $zipFilename to check version info.");
    }
}


/**
 * Inserts the version into a json data string.
 * @param $contents
 * @param $version
 * @return bool|string
 * @throws Exception
 */
function modifyJson($contents, $version) {

    $contentsInfo = json_decode($contents, true);
    
    if (is_array($contentsInfo) == false) {
        throw new \Exception("Json_decode failed for contents [".$contents."]");
    }
    
    if (array_key_exists('version', $contentsInfo) == false) {
        $contentsInfo['version'] = $version;
        return json_encode($contentsInfo);
    }

    return false;
}


/**
 * Generate a very basic composer.json for a zipball.
 * @param $repoName
 * @param $tag
 * @return string
 */
function generateComposerJSON($repoName, $tag) {

    $contents = <<< END
{
 "name": "$repoName",
 "version": "$tag"
}

END;

    return $contents;
}

/**
 * If a zipball doesn't have a composer.json in it's root, it is unprocessiable and
 * always will be. Mark it to be skipped for all future runs, to avoid being re-downloaded.
 * 
 * This is now moot with the added functionality to add composer.json to a zipball that
 * doesn't have one.
 * 
 * @param $zipFilename
 */
function markFileToSkip($zipFilename) {
    unlink($zipFilename);
    file_put_contents("ignoreList.txt", $zipFilename."\n", FILE_APPEND);
}

