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
            $title = (string)test_input($_POST["search"]);
            if(str_contains($title, "&&")) {
                // divide the string into before and after AND, check for both
            }
            // echo $title;
            // create query instance
            $input = 'for $song in collection("swarabitan")/swaralipixml
            where $song/info/title/text() = "'.$title.'" let $lines := $song/sheet return <result>{$song/info/title/text()},<br></br>{$lines}</result>';
            $query = $session->query($input);
            
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
