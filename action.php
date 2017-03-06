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
        require_once 'action_base.php';
        // actions
        try{
            // Insert some newlines
            echo "<br><br><br>";

            // SUBMIT action
            if (isset($_POST["submit_delete"]))       { deleteCollectionAction($_POST); }
            elseif(isset($_POST["src_submit"]))       { addPersonAction($_POST, $_FILES); }
            elseif(isset($_POST["submit_search"]))    { searchPersonAction($_POST, $_FILES); }
            elseif(isset($_POST["submit_tw_start"]))  { startTwitterCallback(); }
            elseif(isset($_POST["submit_tw_stop"]))   { killTwitterCallback(); }
            elseif(isset($_POST["submit_tw_status"])) { inquireTwitterCallbackProcess(); }
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
