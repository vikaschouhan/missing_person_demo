<?php

class SQLiteBackend{
    // PRIVATE MEMBERS
    private $db_handle = NULL;
    // arg names to sql symbols
    private static $args_to_sqlsym_map;
    private static $args_list;
    private static $args_help;
    private static $primary_key;
    private static $args_empty;
    private static $initialized = False;


    // static functions
    static function quote($v){
        return '"' . $v . '"';
    }
    static function identity($v){
        return $v;
    }
    static function s_init(){
        // Static Member Initialization
        self::$args_to_sqlsym_map = array(
                                             "name"               => array( "sym" => "NAME",          "type" => "TEXT",  "fmt" => "%s" ),
                                             "pic_name"           => array( "sym" => "PIC_NAME",      "type" => "TEXT",  "fmt" => "%s" ),
                                             "gender"             => array( "sym" => "GENDER",        "type" => "CHAR",  "fmt" => "%s" ),
                                             "age"                => array( "sym" => "AGE",           "type" => "INT",   "fmt" => "%u" ),
                                             "missing_since"      => array( "sym" => "MISSING_SINCE", "type" => "TEXT",  "fmt" => "%s" ),
                                             "contact_name"       => array( "sym" => "CONTACT_NAME",  "type" => "TEXT",  "fmt" => "%s" ),
                                             "contact_phone"      => array( "sym" => "CONTACT_PHONE", "type" => "TEXT",  "fmt" => "%s" ),
                                             "misc_info"          => array( "sym" => "MISC_INFO",     "type" => "TEXT",  "fmt" => "%s" )
                                         );
        self::$args_list   = array_keys(SQLiteBackend::$args_to_sqlsym_map);
        self::$args_help   = array_combine(self::$args_list, self::$args_list);
        self::$primary_key = "name";
        self::$args_empty  = array_fill_keys(SQLiteBackend::$args_list, NULL);
    }
    static function help_str(){
        return json_encode(self::$args_help);
    }


    // CONSTRUCTOR & DESTRUCTOR
    function __construct($db_name){
        // Initialize static elements if not initialized
        if (self::$initialized == False){
            self::s_init();
            self::$initialized = True;
        }

        $this->db_handle = new SQLite3($db_name);
        $sym_map   = self::$args_to_sqlsym_map;

        // hardcoding table for time being !!
        // FIXME: This has to be fixed !!.
        //        Secondly having primary key as one of the existing keys sometimes
        //        creates issues. So we should insert another key called 'serial_no'
        //        and make that as the primary key.
        $query_str = sprintf('CREATE TABLE IF NOT EXISTS FACE_DB (
                                                  %s   %s              NOT NULL,
                                                  %s   %s PRIMARY KEY  NOT NULL,
                                                  %s   %s              NOT NULL,
                                                  %s   %s              NOT NULL,
                                                  %s   %s              NOT NULL,
                                                  %s   %s              NOT NULL,
                                                  %s   %s              NOT NULL,
                                                  %s   %s              NOT NULL
                                              );',
                            $sym_map["name"]["sym"],           $sym_map["name"]["type"],
                            $sym_map["pic_name"]["sym"],       $sym_map["pic_name"]["type"],
                            $sym_map["gender"]["sym"],         $sym_map["gender"]["type"],
                            $sym_map["age"]["sym"],            $sym_map["age"]["type"],
                            $sym_map["missing_since"]["sym"],  $sym_map["missing_since"]["type"],
                            $sym_map["contact_name"]["sym"],   $sym_map["contact_name"]["type"],
                            $sym_map["contact_phone"]["sym"],  $sym_map["contact_phone"]["type"],
                            $sym_map["misc_info"]["sym"],      $sym_map["misc_info"]["type"]
                        );

        $results   = $this->db_handle->query($query_str);
    }
    function __destruct(){
        $this->db_handle->close();
        $this->db_handle = NULL;
    }

    // PRIVATE functions
    private function intersect_args(array $args){
        $defaults = self::$args_empty;
        return array_filter(array_merge($defaults, array_intersect_key($args, $defaults)));
    }
    private function gen_sql_key_value_pair(array $args){
        $key_arr = array_keys($args);

        $sqlk_arr = array_map(function ($k){ return self::$args_to_sqlsym_map[$k]["sym"]; }, $key_arr);
        $sqlv_arr = array();
        array_walk($args, function ($v, $k) use (&$sqlv_arr){
                              if (self::$args_to_sqlsym_map[$k]["fmt"] == "%s"){
                                  $v_m = self::quote($v);
                              } elseif (self::$args_to_sqlsym_map[$k]["fmt"] == "%u"){
                                  $v_m = self::quote($v);
                              }else{
                                  $v_m = $v;
                              }
                              array_push($sqlv_arr, $v_m);
                          });

        $key_str = '(' . implode(",", $sqlk_arr) . ')';
        $val_str = '(' . implode(",", $sqlv_arr) . ')';

        //print $key_str . "\r\n";
        //print $val_str . "\r\n";
        return array($key_str, $val_str);
    }
    private function gen_sql_key_value_interleaved(array $args){
        $key_arr    = array_keys($args);
        $sql_kv_arr = array();
        array_walk($args, function ($v, $k) use (&$sql_kv_arr){
                              if (self::$args_to_sqlsym_map[$k]["fmt"] == "%s"){
                                  $v_m = self::quote($v);
                              } elseif (self::$args_to_sqlsym_map[$k]["fmt"] == "%u"){
                                  $v_m = self::quote($v);
                              }else{
                                  $v_m = $v;
                              }
                              array_push($sql_kv_arr, $k . "=" . $v_m);
                          });

        $kv_str = implode(",", $sql_kv_arr);

        return $kv_str;
    }


    // PUBLIC FUNCTIONS
    public function add_record(array $args){
        $args = $this->intersect_args($args);
        if (count($args) != count(self::$args_empty)){
            print "args should be in this form :-\r\n";
            print self::help_str();
            print "\r\n";
            return;
        }
        // insert query string
        list($key_str, $val_str) = $this->gen_sql_key_value_pair($args);
        $q_str_ins = sprintf('INSERT INTO FACE_DB %s VALUES %s;', $key_str, $val_str);
        $kv_istr   = $this->gen_sql_key_value_interleaved($args);
        $q_str_upd = sprintf('UPDATE FACE_DB SET %s;', $kv_istr);
        

        // try to insert
        $sql_result = $this->db_handle->query($q_str_ins);
	// try to update if previous attempt was not successfull
	if (!$sql_result){
	    $sql_result = $this->db_handle->query($q_str_upd);
	}

        return $sql_result;
    }
    public function fetch_records(array $args){
        $args = $this->intersect_args($args);
        if (count($args) == 0){
            print "argument to fetch_records() should be in form " . serialize($defaults);
            print "\r\n";
            return NULL;
        }

        $key_list = array_keys($args);
        if ($key_list[0] != "pic_name"){
            print "Right now fetches can work only by pic_name !!\r\n";
            return NULL;
        }

        $query_str = sprintf('SELECT * FROM FACE_DB WHERE PIC_NAME LIKE "%%%s%%";', $args["pic_name"]);
        $result    = $this->db_handle->query($query_str);

        // if non-null was returned, print the result
        if ($result){
            //print_r($result->fetchArray(SQLITE3_ASSOC));
            return $result->fetchArray(SQLITE3_ASSOC);
        }
        return NULL;
    }
}


//// Usage
//$db_handle = new SQLiteBackend();
//$db_handle->add_record(array(
//                                "name"      => "Nillkanth",
//                                "pic_name"  => "Nillkanth.png",
//                                "gender"    => "M",
//                                "age"       => 87,
//                                "missing_since"  => "2001-04-05",
//                                "contact_name"   => "Giriraj Kumar",
//                                "contact_phone"  => "98453242423",
//                                "misc_info"      => "Left mole on eye"
//                            ));
//$db_handle->add_record(array(
//                                "name"           => "Vikas Chauhan",
//                                "pic_name"       => "Vikas.png",
//                                "gender"         => "M",
//                                "age"            => 25,
//                                "missing_since"  => "2016-01-01",
//                                "contact_name"   => "RB Chauhan",
//                                "contact_phone"  => "+91-9611663497",
//                                "misc_info"      => "Last seen wearing Red T-shirt"
//                            ));
//$db_handle->fetch_records([ "pic_name" => "Vikas" ]);

?>
