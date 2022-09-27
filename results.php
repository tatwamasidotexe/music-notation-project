<?php
include_once 'load.php';

use BaseXClient\BaseXException;
use BaseXClient\Session;

try {
    // create session
    $session = new Session("localhost", 1984, "admin", "admin");
    
    // data pre-processing function
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    try {
        if($_POST["search"]) {

            $input = (string)test_input($_POST["search"]);

            /* SAMPLE QUERIES */
            // title starts_with "a" and no_of_lines > 15
            // thaat starts_with "a" and taal = "ektaal"
            // title = "chini go chini" and no_of_lines > 15 =======> USING THIS ONE AS SAMPLE INPUT
            
            $attribute_set = ["title", "thaat", "no_of_lines", "taal"];
            $conjunction_set = ["and", "or"];
            $operator_set = ["starts_with", "=", ">", "ends_with"];
            
            $attributes = [];
            $operators = [];
            $conjunctions = [];
            $values = []; // this will store the values of the attributes on which the operation will be performed

            // STEP 1: EXTRACT ALL VALUES WITHIN QUOTES FROM INPUT STR, REPLACE VALUES WITH NULL STR
            $i = 0;
            while($i < sizeof($input)) {
                $i = strpos($input, "\""); // starting " index
                $j = strpos(substr($input, $i+1), "\""); // ending " index
                $value = substr($input, $i+1, $j - $i); // value str b/w "..."
                array_push($values, $value);
                str_replace("\"{$value}\"", "", $input); // replacing the stored "value" with null in the input str
                // substr_replace($input,"",$i);
            }

            // output:
            // values = [chini go chini, 15]
            // input => title = "" and no_of_lines > ""
            

            // STEP 2: BREAK STRING ON AND
            
            function splitOnAnd($string){
                if(strpos($string, " and ")) {
                    $array = str_split(" and ", $string); // returns an array of queries that were separated by and in $input
                    array_push($conjunctions, "and")
                    return $array;
                }
                return $string;
            }

            $queries = splitOnAnd($input);

            // result = [title = "", no_of_lines > ""]

            // STEP 3: GET THE ATTRIBUTES AND OPERATORS FROM EACH $queries[i]
            // 1. separate by space, put in array
            foreach($queries as $q) {
                $input_terms = explode(" ", $q);
                // input_terms = [title, =] or [no_of_lines, >]
                foreach($input_terms as $term) {
                    if(in_array($term, $attribute_set)) {
                        // $i = array_search($term, $input_terms);
                        array_push($attributes, $term);
                    } elseif (in_array($term, $operator_set)) {
                        array_push($operators, $term);
                    }
                }
            }

            // result => attributes = [title, no_of_lines], operators = [=, >], values = [chini go chini, 15], conjunctions = [and]

            // STEP 4: FORMING THE XQUERY

            // 4.1: form xpaths for each attribute to be searched
            $paths = array(
                ["title", "author", "lyric_language", "notation_system", "note_font_name", "lyric_font_name", "composition_year", "genre"] => "info",
                ["taal_name", "bibhaga", "maatra", "avartana", "beat_pattern", "taali_count", "khaali_count", "taali_index", "khaali_index"] => "taal",
                ["raag_name", "thaat", "arohana", "avarohana", "vadi", "samvadi", "jaati", "pakad"] => "raag"
            );

            function formPaths() {
                $attr_path = [];
                $path_keys = array_keys($paths);
                for($i = 0; $i < sizeof($attributes); ++$i) {
                    $j = 0;
                    while($j < sizeof($path_keys)) {
                        if(in_array($attributes[$i], $path_keys[$j])) {
                            array_push($attr_path, $path_keys[$j]);
                            break;
                        }
                        ++$j;
                    }
                }
                return $attr_path;
            }

            $xpaths = formPaths();
            // xpaths = [info, taal]


            // 4.2: forming the xquery using attributes[], operators[], conjunctions[], values[]
            // 4.2.1: form all the where clauses depending on sizeof(attributes)
            $where_clauses = "";
            $i = $j = 0;
            if (sizeof($attributes) % sizeof($conjunctions) == 1) {
                for($i = 0; $i < sizeof($attributes); ++$i) {
                    $clause = "\$song/{$xpaths[$i]}/{$attributes[$i]} = {$value[$i]}"; // different syntax for num and str
                    if($i == 0) { 
                        $where_clauses = $clause + " and ";
                    }
                    elseif ($i < sizeof($conjunctions)) {
                        $where_clauses += $clause + " and ";
                    }
                    else {
                        $where_clauses += $clause;
                    }
                }
            } else {
                echo "invalid search input";
            }

            // xpaths = [info/title, taal/no_of_lines]
            // where_clauses = $song/info/title = chini go chini and $song/taal/no_of_lines = 15
            
            // 4.2.2 integrate where clause and form final query
            $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml 
            where {$where_clauses} 
            let \$song_lines := \$song/sheet 
            return <result>{\$song_lines}</result>";
            

            // $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml 
            // where \$song/{$addresses[0]}/text() = \"{$inputArray[$input_parameters[0]]}\" and 
            // \$song/{$addresses[1]}/text() = \"{$inputArray[$input_parameters[1]]}\" 
            // let \$song_lines := \$song/sheet 
            // return <result>{\$song_lines}</result>";
            

            // im going to create methods for the operators.
            function starts_with($before, $after){
                $query_str = "\$song/WHEREVERTHISTAGISLOCATED-1/{$before}[0] = {$after}" ;// FIND OUT IF WE CAN GET AN ADDRESS THROUGH XQUERY
                return $query_str;
            }

            function and($before, $after) {
                $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml
                where " + $before + " and " + $after " 
                let \$song_lines := \$song/sheet
                return <result>{\$song_lines}</result>" ; // FIND OUT IF WE CAN GET AN ADDRESS THROUGH XQUERY
                return $query_str;
            }

            function equals($before, $after) {
                $query_str = "\$song/WHEREVERTHISTAGISLOCATED-1/{$before} = " + $after;
                return $query_str;
            } 

            function greater_than($before, $after) {
                $query_str = "\$song/WHEREVERTHISTAGISLOCATED-1/{$before} > " + $after;
                return $query_str;
            }

            function ends_with($before, $after) {
                $query_str = "\$song/WHEREVERTHISTAGISLOCATED-1/{$before} " + $after;
                return $query_str;
            }

            $query = $session->query($query_str);
            
            // printing each query result
            foreach($query as $result) {
                echo $result."<br>";
            }
    
            // close query instance
            $query->close();

            //the input string format has to be specified. example: "title= ami chini go chini & taal_name= ek taal"
            // parse_str($input, $inputArray);

            // // a list of all search parameters we can work with for now
            // $search_parameters = ['title', 'genre', 'taal_name', 'raag_name', 'thaat'];

            // // a list of all the parameters the user is searching for
            // $input_parameters = array_keys($inputArray);

            // // need a way to locate the separate xml tags to formulate the queries
            // $addresses = ["info/title", "taal/taal_name"];


            // // create query instance
            // $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml 
            // where \$song/{$addresses[0]}/text() = \"{$inputArray[$input_parameters[0]]}\" and 
            // \$song/{$addresses[1]}/text() = \"{$inputArray[$input_parameters[1]]}\" 
            // let \$song_lines := \$song/sheet 
            // return <result>{\$song_lines}</result>";

            
        }
        

        
    } catch (BaseXException $e) {
        // print exception
        print $e->getMessage();
    }

    // close session
    $session->close();
} catch (BaseXException $e) {
    // print exception
    print $e->getMessage();
}