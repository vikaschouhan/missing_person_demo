<?php

// Imports
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/sql/SQLiteBackend.php';

use Aws\Credentials\CredentialProvider;
use Aws\Rekognition\Errors\InvalidParameterException as InvalidParameterException;
use Aws\Rekognition\Exception\RekognitionException as RekognitionException;
use Abraham\TwitterOAuth\TwitterOAuth as TwitterOAuth;
use SQLiteBackend as SQLiteBackend;

// Get Rekogniton Client handle
function rekognitionClient(){
    $client = Aws\Rekognition\RekognitionClient::factory([
                  "region"  => "us-west-2",
                  "version" => "latest",
                  "credentials" => [
                          "key"     => "AKIAI4YM4HDJYM6DSDPQ",
                          "secret"  => "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"
                      ],
              ]);
    return $client;
}

// Returns a file size limit in bytes based on the PHP upload_max_filesize
// and post_max_size
function file_upload_max_size() {
    static $max_size = -1;

    if ($max_size < 0) {
        // Start with post_max_size.
        $max_size = parse_size(ini_get('post_max_size'));

        // If upload_max_size is less, then reduce. Except if upload_max_size is
        // zero, which indicates no limit.
        $upload_max = parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
    }
    return $max_size;
}

function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
    $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
    if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    else {
        return round($size);
    }
}

 // get image Bytes
function imageBytes($img){
    return file_get_contents($path);
}

// Return status code for result obtained by Rekognition API
function statusCode($result){
    return $result["@metadata"]["statusCode"];
}

// display image
function displayImage($src){
    $pic_path = str_replace(__DIR__, '', $src);
    echo '<br>';
    echo '<hr><h2 class="intro-text text-center"><strong> Uploaded Image </strong></h2><hr>';
    echo '<div class="col-md-12">';
    echo '<div class="col-md-4 col-md-offset-4">';
    echo '<div class="thumbnail">';
    echo '<img src=' . $pic_path . ' class="img-responsive img-border" alt="" style="width:auto">';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// report errors
function errorReportMessage($msg){
    echo '<br>';
    echo '<h2 class="intro-text text-center">' . $msg . '</h2>';
    echo '<br>';
}

// display face search results as table
function displayFaceSearchResultsAsTable($result){
    // DEBUG
    //echo $result["FaceMatches"][0]["Face"]["ExternalImageId"];
    //displayImage($index_dir . "/" . $result["FaceMatches"][0]["Face"]["ExternalImageId"]);
    //dumpVar($result["FaceMatches"][0]["Face"]["ExternalImageId"]);
    //
    global $index_dir_img_src;

    // Get databse handle
	global $db_name;
	createDbDir($db_name);
    $dbase_backend = new SQLiteBackend($db_name);

    // emit html for table header
    $n_matches = count($result["FaceMatches"]);
    echo '<hr>';
    echo '<h2 class="intro-text text-center"><strong> Search Results </strong></h2>';
    echo '<h5 class="text-center">' . '('. $n_matches . " Matches found.)" . '</h5>';
    echo '<hr>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover table-condensed">';
    echo '<tr>';
    echo '<th> Match    </th> <th> Similarity </th>  <th> Confidence </th> ';
    echo '<th> Gender   </th> <th> Age        </th>  <th> Missing Since </th> ';
    echo '<th> Contact Name </th> <th> Contact Phone </th> <th> Misc Info </th> ';
    echo '</tr>';

    // emit html for rows
    for ($indx = 0; $indx < $n_matches; $indx++){
        $pic_name     = $result["FaceMatches"][$indx]["Face"]["ExternalImageId"];
        $pic_name_rel = $index_dir_img_src . '/' . $pic_name;

        // search sql database
        $sql_results = $dbase_backend->fetch_records(["pic_name" => $pic_name]);

        // emit html
        echo '<tr>';
        echo '<td>' . '<img class="img-responsive" src="' . $pic_name_rel . '" alt="" style="width:140px; height:auto;">' . '</td>';  // Match
        echo '<td>' . $result["FaceMatches"][$indx]["Similarity"] . '</td>';                 // Similarity
        echo '<td>' . $result["FaceMatches"][$indx]["Face"]["Confidence"] . '</td>';         // Confidence
        echo '<td>' . $sql_results["GENDER"] . '</td>';
        echo '<td>' . $sql_results["AGE"] . '</td>';
        echo '<td>' . $sql_results["MISSING_SINCE"] . '</td>';
        echo '<td>' . $sql_results["CONTACT_NAME"] . '</td>';
        echo '<td>' . $sql_results["CONTACT_PHONE"] . '</td>';
        echo '<td>' . $sql_results["MISC_INFO"] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
}

// dump variables
function dumpVar($var){
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

// check errors for file upload
function checkUploadFileErrors($f_ptr){
    // check for any upload errors
    switch ($f_ptr["error"]){
        case UPLOAD_ERR_OK:
             $message = NULL;
             break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
             $message = 'Image file too large (limit of '.file_upload_max_size().' bytes).';
             break;
        case UPLOAD_ERR_PARTIAL:
             $message = 'Image file upload was not completed.';
             break;
        case UPLOAD_ERR_NO_FILE:
             $message = 'Image file of zero-length uploaded.';
             break;
        default:
             $message = 'Internal error #'. $f_ptr["error"];
             break;
    }
    if ($message){
        errorReportMessage($message);
        return NULL;
    }
    return True;
}

// check for all vars passed via $v_p
function validatePersonForm(array $post_arr, array $file_arr){
    $req_params = array();
    $sql_darray = array();
    //dumpVar($file_arr);

    // check for upload errors
    if (checkUploadFileErrors($file_arr["src_file"]) == NULL){
        return -1;
    }

    // File
    if ($file_arr["src_file"]["size"] == 0)    { array_push($req_params, "Facial image"); }
    // Name
    if ($post_arr["src_name"] == NULL)         { array_push($req_params, "Name"); }
    else                                       { $sql_darray["name"] = $post_arr["src_name"]; }
    // Age
    if ($post_arr["src_age"] == NULL)          { array_push($req_params, "Age"); }
    else                                       { $sql_darray["age"] = $post_arr["src_age"]; }
    // Contact Name
    if ($post_arr["src_contact_name"] == NULL) { array_push($req_params, "Contact Person's name"); }
    else                                       { $sql_darray["contact_name"] = $post_arr["src_contact_name"]; }
    // Contact Phone
    if ($post_arr["src_contact_phone"] == NULL){ array_push($req_params, "Contact Phone no"); }
    else                                       { $sql_darray["contact_phone"] = $post_arr["src_contact_phone"]; }
    // Date
    if ($post_arr["src_missing_since"] == NULL){ array_push($req_params, "Missing Date"); }
    else                                       { $sql_darray["missing_since"] = $post_arr["src_missing_since"]; }
    // Misc Info
    // FIXME: Just add Newline in case there was no misc info, since
    //        php flags empty strings as NULL
    if ($post_arr["src_misc_info"] == NULL)    { $sql_darray["misc_info"] = "\r\n"; }
    else                                       { $sql_darray["misc_info"] = $post_arr["src_misc_info"]; }
    // gender
    $sql_darray["gender"] = $post_arr["src_gender"];

    // Return -1 if some parameter missing
    if (count($req_params) > 0){
        errorReportMessage('(' . implode(", ", $req_params) . ')'  . ' required !!');
        return -1;
    }
    return $sql_darray;
}

// try to upload a file and return parameters
function tryFileUpload($f_ptr, $mode, $display=False){
    // check for any upload errors
    if (checkUploadFileErrors($f_ptr) == NULL){
        return NULL;
    }

    // check modes
    if ($mode == "I"){
        global $index_dir;
        $upload_dir_this = $index_dir;
    }elseif($mode == "S"){
        global $upload_dir;
        $upload_dir_this = $upload_dir;
    }else{
        dumpVar("Fatal error in server code !!\r\n");
        return;
	}

	if (!file_exists($upload_dir_this)) {
        mkdir($upload_dir_this, 0777, true);
    }

    $target_file_name = basename($f_ptr["name"]);
    $file_ext = pathinfo($target_file_name, PATHINFO_EXTENSION);
    $file_name = uniqid();
    $target_file = $upload_dir_this . "/" . $file_name;

    $source_file = basename($f_ptr["name"]);
    $check = getimagesize($f_ptr["tmp_name"]);
    if($check == false) {
        errorReportMessage("File is not an image !!");
        return NULL;
    }

    // try to upload
    if (!move_uploaded_file($f_ptr["tmp_name"], $target_file)) {
        errorReportMessage("Sorry, there was an error uploading your file.");
    }

    // try to open file contents
    $data = file_get_contents($target_file);
    if ($display == True){
        displayImage($target_file);
    }
    return array( "im_data" => $data, "im_name" => $target_file_name, "im_path" => $target_file, "im_unique_name" => $file_name );
}

// action to take on behalf of add_person.php
function addPersonAction($post_arr, $files_arr){
    // globals
    global $index_dir, $client, $collection_id;

    $sf_ptr          = $files_arr["src_file"];
    $name            = $post_arr["src_name"];
    $age             = $post_arr["src_age"];
    $gender          = $post_arr["src_gender"];
    $contact_name    = $post_arr["src_contact_name"];
    $contact_phone   = $post_arr["src_contact_phone"];
    $missing_since   = $post_arr["src_missing_since"];
    $misc_info       = $post_arr["src_misc_info"];

    // validate form data. Return if any inconsistency found
    $retv   = validatePersonForm($post_arr, $files_arr);
    if ($retv == -1){
        return;
    }

    // try uploading image file
    $data   = tryFileUpload($sf_ptr, "I");

    // add Few missing data to structure
    // This structure we are going to out metadata database
    $sql_darray                = $retv;
    $sql_darray["pic_name"]    = $data["im_unique_name"];

    // DEBUG
    //dumpVar($sql_darray);

    if ($data != NULL){
        // Add the entry to sqlite dbase
		global $db_name;
        createDbDir($db_name);
        $dbase_backend = new SQLiteBackend($db_name);
        $sql_result    = $dbase_backend->add_record($sql_darray);

        // Only try to index if a successfull record was added to the metadata database
        if ($sql_result){
            //$client = rekognitionClient();
            $result = $client->indexFaces(array(
                          "CollectionId"           => $collection_id,
                          "DetectionAttributes"    => ["ALL"],
                          "Image"                  => [ "Bytes" => $data["im_data"] ],
                          "ExternalImageId"        => $data["im_unique_name"]
                      ));
            //$result = $client->listCollections([]);
    
            if (statusCode($result) == 200){
                echo '<h2 class="intro-text text-center"> Data successfully sent for indexing. </h2>' . '<br>';
            }else{
                errorReportMessage('Unable to correctly index the data.');
            }
        }
    }
}

// action to take on behalf of search
function searchPersonAction($post_arr, $files_arr){
    // globals
    global $upload_dir, $index_dir, $client, $collection_id;

    $df_ptr = $files_arr["dst_file"];
    $data   = tryFileUpload($df_ptr, "S", $display=True);
    if ($data != NULL){
        //$client = rekognitionClient();
        $result = $client->searchFacesByImage(array(
                      "CollectionId"           => $collection_id,
                      "FaceMatchThreshold"     => 50.0,
                      "Image"                  => [ "Bytes" => $data["im_data"] ],
                  ));
        //$result = $client->listCollections([]);
    
        // check result and display any matches in tabular form
        if (statusCode($result) == 200){
            if (count($result["FaceMatches"]) > 0){
                // emit a header
                echo '<div class="col-xs-12" style="height:50px;"></div>';
                echo '<div class="col-lg-12">';
                
                displayFaceSearchResultsAsTable($result);
                echo '</div>';
            }else{
                errorReportMessage('No match found !!');
            }
        }else{
            errorReportMessage('Unable to complete the request !!');
        }
    }
}

// action to take on bahalf of delete_collection
function deleteCollectionAction($post_arr){
    // globals
    global $collection_id, $client, $db_name, $index_dir, $upload_dir;

    if (isset($post_arr["delete_yes"])){
        // delete everything in the collection
        $result = $client->deleteCollection(array(
                      "CollectionId" => $collection_id
                  ));
        // if deletion was successfull, create a new collection with same name
        if (statusCode($result) == 200){
            // since whole collection was delete, try to create another one with same name
            $result = $client->createCollection(array(
                          "CollectionId" => $collection_id
                      ));
            if (statusCode($result) == 200){
                // try to delete all local files
                // Delete database
                unlink($db_name);
                // Delete all uploaded indexed images
                array_map('unlink', glob("$index_dir/*"));
                // Delete all uploaded local images
                array_map('unlink', glob("$upload_dir/*"));

                // Print SUCCESS
                echo '<h2 class="intro-text text-center"> Collection cleared successfully. </h2>' . '<br>';
            }else{
                errorReportMessage('Error encountered during createCollection() !!');
            }
        }else{
            errorReportMessage('Error encountered during deleteCollection() !!');
        }
    }
}


// twitter callback
// NOTE: $tweet is generated by abraham/twitteroauth
function searchPersonTwitterLink($tweet, array $user_auth){
    // globals
    global $upload_dir, $index_dir, $client, $collection_id, $db_name, $index_dir_img_src;
    $ret_str = NULL;
    $match_found = false;
    $pics_arr = array();
    $pics_count = 0;
    $result = NULL;

    // get tweet's owner
    $tweet_owner = $tweet['user']['screen_name'];

    // FIXME : Right now, it is able to extract media urls only via direct tweets and not
    //         retweets. That would be added later on.
    // check for some attributes to take next set of steps
    if (array_key_exists('extended_entities', $tweet)){
        if (array_key_exists('media', $tweet['extended_entities'])){
            // pics uploaded via the tweet
            $pics_count = count($tweet['extended_entities']['media']);

            // store urls of all uploaded pics in pics_arr
            for ($i=0; $i < $pics_count; $i++){
                array_push($pics_arr, $tweet['extended_entities']['media'][$i]['media_url']);
            }
        }else{
            //echo 'text : ' . $tweet['text'] . PHP_EOL;
        }
    }else{
        //echo 'text : ' . $tweet['text'] . PHP_EOL;
    }

    // debug
    syslog(LOG_INFO, 'TwitterCallback: Got Tweet with ' . count($pics_arr)  . ' pics !!' . PHP_EOL);

    // iterate over each pic and try to find a match for it !!
    for ($indx=0; $indx<count($pics_arr); $indx++){
        // return results
        $ret_arr = array();
        $ret_str = NULL;

        // get file contents
        $data = file_get_contents($pics_arr[$indx]);

        // if null, continue
        if ($data == NULL){
            continue;
        }

        // otherwise
        try{
            // make a search request to aws rekognition client
            $result = $client->searchFacesByImage(array(
                          "CollectionId"           => $collection_id,
                          "FaceMatchThreshold"     => 50.0,
                          "Image"                  => [ "Bytes" => $data ],
                      ));
        }catch (RekognitionException $e){
            $ret_str = "Caught exception: " . $e->getMessage() . PHP_EOL;
            return NULL;
        }
        
        // check result and display any matches in tabular form
        if (statusCode($result) == 200){
            if (count($result["FaceMatches"]) > 0){
                // make db connection
                $dbase_backend = new SQLiteBackend($db_name);

                // Get number of matches
                $n_matches = count($result["FaceMatches"]);

                // debug
                syslog(LOG_INFO, 'Found ' . $n_matches . ' for ' . $pics_arr[$indx] . PHP_EOL);

                for ($indx = 0; $indx < $n_matches; $indx++){
                    $pic_name     = $result["FaceMatches"][$indx]["Face"]["ExternalImageId"];
                    $pic_name_rel = $index_dir_img_src . '/' . $pic_name;

                    // search sql database
                    $sql_results  = $dbase_backend->fetch_records(["pic_name" => $pic_name]);
                    // return array
                    $r_arr        = array(
                                        "image"           => $pic_name_rel,
                                        "similarity"      => $result["FaceMatches"][$indx]["Similarity"],                 // Similarity
                                        "confidence"      => $result["FaceMatches"][$indx]["Face"]["Confidence"],         // Confidence
                                        "gender"          => $sql_results["GENDER"],
                                        "age"             => $sql_results["AGE"],
                                        "missing_since"   => $sql_results["MISSING_SINCE"],
                                        "contact_name"    => $sql_results["CONTACT_NAME"],
                                        "contact_phone"   => $sql_results["CONTACT_PHONE"],
                                        "misc_info"       => $sql_results["MISC_INFO"]
                                    );

                    // push to array
                    array_push($ret_arr, $r_arr);
                    $match_found  = true;

                    // status string
                    $ret_str = 'Match found !!';
                }
            }else{
                $ret_str = 'No match found !!';
            }
        }else{
            $ret_str = 'Unable to complete the request !!';
        }

        syslog(LOG_INFO, 'TwitterCallback: Return string by seach operation: ' . $ret_str . PHP_EOL);

        // make a new connection 
        $tw_con = new TwitterOAuth($user_auth['consumer_key'],
                                   $user_auth['consumer_secret'],
                                   $user_auth['access_token'],
                                   $user_auth['access_token_secret']
                               );

        // Append time stamp to return string
        $ret_str = time() . PHP_EOL . $ret_str;

        // tweet back to user with some status message
        if ($match_found == true){
            $m_arr = array();
            for ($i=0; $i<count($ret_arr); $i++){
                $media = $tw_con->upload('media/upload', ['media' => $ret_arr[$i]["image"]]);
                array_push($m_arr, $media->media_id_string);
            }

            // debug
            syslog(LOG_INFO, 'TwitterCallback: Tweeting back to user ' . $tweet_owner . ' with success message' . PHP_EOL);

            $parameters = [
                'status'    => '@' . $tweet_owner . ' ' . $ret_str,
                'media_ids' => implode(',', $m_arr)
            ];
            $result = $tw_con->post('statuses/update', $parameters);
        }else{
            // debug
            syslog(LOG_INFO, 'TwitterCllback: Tweeting back to user ' . $tweet_owner . PHP_EOL);

            $parameters = [
                'status'    => '@' . $tweet_owner . ' ' . $ret_str,
            ];
            $result = $tw_con->post('statuses/update', $parameters);
        }
        syslog(LOG_INFO, 'TwitterCallback: Status returned by tw_con->post[status/update] is ' . print_r($result, true));
    }

    return $ret_str;
}

// twitter callback control
//
function startTwitterCallback(){
    global $tw_cb_file;

    // first kill all previous processes
    killTwitterCallback();
    
    // Run process and get pid
    $command = 'php ' . $tw_cb_file . ' > /dev/null 2>&1 & echo $!; ';
    $pid = exec($command, $output);
    echo '<h2 class="intro-text text-center">New Twitter callback created with pid : ' . $pid . '</h2>';
}

function killTwitterCallback(){
    global $tw_cb_file;

    // check and kill process(es)
    exec('pgrep -f ' . $tw_cb_file, $pid_arr);
    // FIXME FIXME FIXME FIXME
    // FIXME : For some reason this works only on AWS with PHP-7.0.14
    //         With my local PHP-7.1.0 it doesn't work. count($pid_arr) needs to be replaced with (count($pid_arr) - 1)
    if (count($pid_arr) > 0){         
        for($i=0; $i<count($pid_arr); $i++){
            echo '<h2 class="intro-text text-center">Killing running Twitter callback with pid : ' . $pid_arr[$i] . '</h2>';
            exec('kill -9 '. $pid_arr[$i]);
        }
    }else{
        echo '<h2 class="intro-text text-center">No Running Twitter callback instance found.</h2>';
    }
}

function inquireTwitterCallbackProcess(){
    global $tw_cb_file;

    exec('pgrep -f ' . $tw_cb_file, $pid_arr);
    // FIXME FIXME FIXME FIXME
    // FIXME : For some reason this works only on AWS with PHP-7.0.14
    //         With my local PHP-7.1.0 it doesn't work. count($pid_arr) needs to be replaced with (count($pid_arr) - 1)
    //         and implode(',', $pid_arr) needs to be replaced with implode(',', array_slice($pid_arr, 0, count($pid_arr)-1))
    if (count($pid_arr) > 0){
        echo '<h2 class="intro-text text-center">Twitter callback is running with pid : ' . implode(',', $pid_arr) . '</h2>';
    }else{
        echo '<h2 class="intro-text text-center">No Running Twitter callback instance found.</h2>';
    }
}

function createDbDir($db_name){
	$db_dir = dirname($db_name);
	if (!file_exists($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
}

// NOTE: NOTE: NOTE:
// Global variables !!
// get client handle
$client             = rekognitionClient();
$collection_id      = "faces0";
$index_dir          = __DIR__ . '/indexed';
$upload_dir         = __DIR__ . '/uploads';
$db_name            = __DIR__ . '/database/person_info.db';
$index_dir_img_src  = "indexed";
// twitter lock file and application file
$lock_file          = __DIR__ . '/database/p.pid';
$tw_cb_file         = 'twitter_cb.php';

?>
