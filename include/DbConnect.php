<?php

/**
 * Handling Database Connection
 */

class DbConnect {
    private $conn;
    
    function __construct() {
    }
    
    function connect() {
        include_once dirname(__FILE__) . '/Config.php';
        
        // connecting to mysql database
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        // check for database connection error
        if (mysqli_connect_errno()) {
            echo 'Failed to connect to MySql '. mysqli_connect_error();
        }
        
        return $this->conn;
    }
}