<?php

/* 
 * class to handle all db operations
 * all the CRUD operations on database
 */

class DbHandler {
    
    private $conn;
    
    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
    
    //-----------------------------------------------------------------
    // TABLE: user-regular
    
    /*
     * creating new user
     */
    
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = [];
        
        // check if user email alrready exists
        if ($this->isUserExists($email)) {
            return USER_ALREADY_EXISTED;
        } else {
            // generate password hash
            $password_hash = PassHash::hash($password);
            
            // generate api key
            $api_key = $this->generateApiKey();
            
            // insert query 
            $stmt = $this->conn->prepare("INSERT INTO user_regular 
                (name, email, password_hash, api_key)
                VALUES (?, ?, ?, ?);");
            
            $stmt->bind_param("ssss", $name, $email, $password_hash, $api_key);
            
            $result = $stmt->execute();
            
            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            } else {
                return USER_CREATED_FAILED;
            }
        }
        
        return $response;
    }
    
    /*
     * check if user exists
     */
    public function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT * FROM user_regular WHERE email = ?;");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows>0;
    }
    
    /*
     * generate api key
     */
    public function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
    
    /*
     * checking user login
     */
    public function checkLogin($email, $password) {
        $stmt = $this->conn->prepare('SELECT password_hash FROM user_regular WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            
            if (PassHash::check_password($password_hash, $password)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            $stmt->close();
            return FALSE;
        }
    }
    
    /*
     * get user id 
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM user_regular WHERE email = ?;");
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
    
    /*
     *  get all tvseries
     *  should also give back whether in collection or not
     */
    public function getAllTvSeries() {
        $this->conn->prepare("SELECT name, id_tv_series, count_like, count_rating, id_default_image FROM tv_series;");
    }
}

