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
        //$data = stripslashes($data);
        //$data = htmlspecialchars($data);
        return $data;
    }

    function splitOnAnd($string, $conjunctions){
         
        $array = explode(" and ", $string); // returns an array of queries that were separated by and in $input
        array_push($conjunctions, "and");
        return $array;
        
    }
    function formPaths($paths, $attributes) {
        $attr_path = [];
        $path_values = array_values($paths);
        for($i = 0; $i < sizeof($attributes); ++$i) {
            $j = 0;
            while($j < sizeof($path_values)) {
                if(in_array($attributes[$i], $path_values[$j])) {
                    array_push($attr_path, array_search($path_values[$j], $paths));
                    break;
                }
                ++$j;
            }
        }
        return $attr_path;
    }

    try {
        if($_POST["search"]) {

            $input = test_input($_POST["search"]);

            /* SAMPLE QUERIES */
            // title starts_with "a" and no_of_lines > 15
            // thaat starts_with "a" and taal = "ektaal"
            // title = "ami chini go chini" and maatra > 15 =======> USING THIS ONE AS SAMPLE INPUT
            
            $attribute_set = ["title", "thaat", "no_of_lines", "maatra", "taal"];
            $conjunction_set = ["and"];
            $operator_set = ["starts_with", "=", ">", "ends_with"];
            
            $attributes = [];
            $operators = [];
            $conjunctions = [];
            $values = []; // this will store the values of the attributes on which the operation will be performed


            // STEP 1: EXTRACT ALL VALUES WITHIN QUOTES FROM INPUT STR, REPLACE VALUES WITH NULL STR
            $i = 0;
            while ($i < strlen($input)) {

                $i = strpos($input, "\""); // starting " index 
                if(!$i) {
                    break;
                } else {
                    $j = strpos($input, "\"", $i+1); // ending " index
                    $value = substr($input, $i+1, $j - $i - 1); // value str b/w "..."

                    if(is_numeric($value)) {
                        array_push($values, number_format($value));
                    } else {
                        array_push($values, $value);
                    }
                    $input = str_replace("\"{$value}\"", "", $input); // replacing the stored "value" with null in the input str
                }

                
            }

            // output:
            // values = [ami chini go chini, 15]
            // input => title = "" and maatra > ""
            


            // STEP 2: BREAK STRING ON AND   
            array_push($conjunctions, "and"); 
            $queries = splitOnAnd($input, $conjunctions);


            // STEP 3: GET THE ATTRIBUTES AND OPERATORS FROM EACH $queries[i]
            // 1. separate by space, put in array
            foreach($queries as $q) {
                $input_terms = explode(" ", $q);
                // input_terms = [title, =] or [maatra, >]
                foreach($input_terms as $term) {
                    if(in_array($term, $attribute_set)) {
                        // $i = array_search($term, $input_terms);
                        array_push($attributes, $term);
                    } elseif (in_array($term, $operator_set)) {
                        array_push($operators, $term);
                    }
                }
            }
            // result => attributes = [title, maatra], operators = [=, >], values = [chini go chini, 15], conjunctions = [and]


            
            // STEP 4: FORMING THE XQUERY
            // 4.1: form xpaths for each attribute to be searched           
            $paths = array(
                "info" => ["title", "author", "lyric_language", "notation_system", "note_font_name", "lyric_font_name", "composition_year", "genre"],
                "taal" => ["taal_name", "bibhaga", "maatra", "avartana", "beat_pattern", "taali_count", "khaali_count", "taali_index", "khaali_index"],
                "raag" => ["raag_name", "thaat", "arohana", "avarohana", "vadi", "samvadi", "jaati", "pakad"]
            ); 

            $xpaths = formPaths($paths, $attributes);
            // xpaths = [info, taal]


            // 4.2: forming the xquery using attributes[], operators[], conjunctions[], values[]
            // 4.2.1: form all the where clauses depending on sizeof(attributes)
            $where_clauses = "";
            $i = $j = 0;
            if (sizeof($conjunctions) % sizeof($attributes) == 1) {
                for($i = 0; $i < sizeof($attributes); ++$i) {
                    $clause = "";
                    if(is_numeric($values[$i])) {
                        $clause = "\$song/{$xpaths[$i]}/{$attributes[$i]} {$operators[$i]} {$values[$i]}"; // num
                    } else {
                        $clause = "\$song/{$xpaths[$i]}/{$attributes[$i]}/text() {$operators[$i]} \"{$values[$i]}\""; // str
                    }

                    if($i == 0) { 
                        $where_clauses = $clause." and ";
                    }
                    elseif ($i < sizeof($conjunctions)) {
                        $where_clauses .= $clause." and ";
                    }
                    else {
                        $where_clauses .= $clause;
                    }
                }
            } else {
                echo "invalid search input";
            }

            // RESULT   
            // xpaths = [info/title, taal/maatra]
            // where_clauses = $song/info/title/text() = ami chini go chini and $song/taal/maatra = 15


            // 4.2.2 integrate where clause and form final query
            $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml 
            where {$where_clauses} 
            let \$song_lines := \$song/sheet/total_line
            return <result>{\$song_lines}</result>";

            $query = $session->query($query_str);
            
            // printing each query result
            foreach($query as $result) {
                echo $result."<br>";
            }
    
            // close query instance
            $query->close();
            
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