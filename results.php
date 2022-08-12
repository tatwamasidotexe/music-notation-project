<?php
/*
 * This example shows how external variables can be bound to XQuery expressions.
 *
 * Documentation: https://docs.basex.org/wiki/Clients
 *
 * (C) BaseX Team 2005-22, BSD License
 */
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

            //the input string format has to be specified. example: "title= ami chini go chini & taal_name= ek taal"
            parse_str($input, $inputArray);

            // a list of all search parameters we can work with for now
            $search_parameters = ['title', 'genre', 'taal_name', 'raag_name', 'thaat'];

            // a list of all the parameters the user is searching for
            $input_parameters = array_keys($inputArray);

            // need a way to locate the separate xml tags to formulate the queries
            $addresses = ["info/title", "taal/taal_name"];


            // create query instance
            $query_str = "for \$song in collection(\"swarabitan\")/swaralipixml 
            where \$song/{$addresses[0]}/text() = \"{$inputArray[$input_parameters[0]]}\" and 
            \$song/{$addresses[1]}/text() = \"{$inputArray[$input_parameters[1]]}\" 
            let \$song_lines := \$song/sheet 
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