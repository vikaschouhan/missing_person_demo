<html>

<head>
    

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Find a Missing Person - Results</title>

    <!-- Bootstrap Core CSS -->
    <link href="css/bootstrap-3.3.4/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Josefin+Slab:100,300,400,600,700,100italic,300italic,400italic,600italic,700italic" rel="stylesheet" type="text/css">

</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-default" role="navigation">
        <div class="container">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <!-- navbar-brand is hidden on larger screens, but visible when the menu is collapsed -->
                <a class="navbar-brand" href="index.html">Missing Person Demo</a>
            </div>
            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <li>
                        <a href="index.html">Home</a>
                    </li>
                    <li>
                        <a href="add_person.html">Add new Person</a>
                    </li>
                    <li>
                        <a href="search_missing.html">Search Missing Person</a>
                    </li>
                    <li>
                        <a href="delete.html">Delete database</a>
                    </li>
                </ul>
            </div>
            <!-- /.navbar-collapse -->
        </div>
        <!-- /.container -->
    </nav>


    <div class="container">
        <div class="box">
            <div class="row">

    <?php
        // Imports
        require $_SERVER['DOCUMENT_ROOT'] . "/libs/aws/aws-autoloader.php";
        require $_SERVER['DOCUMENT_ROOT'] . "/libs/sql/SQLiteBackend.php";

        use Aws\Credentials\CredentialProvider;
        use Aws\Rekognition\Errors\InvalidParameterException as InvalidParameterException;
        use SQLiteBackend as SQLiteBackend;

        // Get Rekogniton Client handle
        function rekognitionClient(){
            $client = Aws\Rekognition\RekognitionClient::factory([
                          "region"  => "us-west-2",
                          "version" => "latest",
                          "credentials" => [
                                  "key"     => "AKIAJZNQ22VSKLSURLPA",
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
            $pic_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $src);
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
    
        // get client handle
        $client             = rekognitionClient();
        $collection_id      = "faces0";
        $index_dir          = $_SERVER['DOCUMENT_ROOT'] . '/indexed';
        $upload_dir         = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
        $db_name            = $_SERVER['DOCUMENT_ROOT'] . '/database/person_info.db';
        $index_dir_img_src  = "indexed";
    
        // actions
        try{
            // Insert some newlines
            echo "<br><br><br>";

            // SUBMIT action
            if (isset($_POST["submit_delete"]))       { deleteCollectionAction($_POST); }
            elseif(isset($_POST["src_submit"]))       { addPersonAction($_POST, $_FILES); }
            elseif(isset($_POST["submit_search"]))    { searchPersonAction($_POST, $_FILES); }
        } catch (InvalidParameterException $e){
            echo "Caught exception: " . $e->getMessage() . "\n";
        } catch (Exception $e){
            echo "Caught exception: " . $e->getMessage() . "\n";
        }
    
    ?>

    <div class="col-md-12"> 
    <form role="form" action="" method="post">
        <div class="row">
            <div class="form-group2 text-center">
                <button class="btn btn-default" type="submit" name="return_index"> Return to Main Page </button>
            </div>
        </div> 
    </form>
    </div>

    <?php
        if(isset($_POST["return_index"])){
            // return to index page
            $host  = $_SERVER['HTTP_HOST'];
            $extra = 'index.html';
            header("Location: http://$host/$extra");
            exit;
        }
    ?>

            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap -->
    <script src="js/jquery-1.11.2/jquery.min.js"></script>
    <script src="js/bootstrap-3.3.4/bootstrap.min.js"></script>

</body>
</html>
