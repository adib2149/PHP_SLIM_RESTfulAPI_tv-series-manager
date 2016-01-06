<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require_once '../libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$user_id = NULL;

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = [];
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json');
 
    echo json_encode($response);
}

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
 
    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
 
        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL) {
                $user_id = $user["id_user_regular"];
            }
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));
 
            $response = array();
 
            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
 
            // validating email address
            validateEmail($email);
 
            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);
 
            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
                echoRespnse(201, $response);
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
                echoRespnse(200, $response);
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
                echoRespnse(200, $response);
            }
        });
        
/*
 * User Login
 */        
$app->post('/login', function() use ($app) {
    verifyRequiredParams(array('email', 'password'));
    
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $response = [];
    
    $db = new DbHandler();
    
    if ($db->checkLogin($email, $password)) {
        $user = $db->getUserByEmail($email);
        
        if ($user != NULL) {
            $response["error"] = FALSE;
            $response["name"] = $user["name"];
            $response['email'] = $user['email'];
            $response['apiKey'] = $user['api_key'];
            $response['createdAt'] = $user['created_at'];
        } else {
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }
    
    echoRespnse(200, $response);
});

/*
 * all tv series
 */
$app->get('/allseries/', 'authenticate', function() {
    global $user_id;
    $response = [];
    $db = new DbHandler();
    
    $result = $db->getAllTvSeries($user_id);
    
    $response["error"] = FALSE;
    $response["tv_series_list"] = [];
    
    while ($tv_series = $result->fetch_assoc()) {
        $tmp = [];
        $tmp["id_tv_series"] = $tv_series["id_tv_series"];
        $tmp["name"] = $tv_series["tv_series_name"];
        $tmp["likes"] = $tv_series["count_like"];
        $tmp["rating"] = $tv_series["count_rating"];
        $tmp["image"] = $tv_series["default_image"];
        $tmp["is_in_collection"] = $tv_series["status"];
        array_push($response["tv_series_list"], $tmp);
    }
    
    echoRespnse(200, $response);
});

/*
 * collection of a single user
 */
$app->get('/collection/', 'authenticate', function() {
    global $user_id;
    $response = [];
    $db = new DbHandler();
    
    $result = $db->getCollection($user_id);
    
    $response["error"] = FALSE;
    $response["tv_series_list"] = [];
    
    while ($tv_series = $result->fetch_assoc()) {
        $tmp = [];
        $tmp["id_tv_series"] = $tv_series["id_tv_series"];
        $tmp["name"] = $tv_series["tv_series_name"];
        $tmp["likes"] = $tv_series["count_like"];
        $tmp["rating"] = $tv_series["count_rating"];
        $tmp["image"] = $tv_series["default_image"];
        array_push($response["tv_series_list"], $tmp);
    }
    
    echoRespnse(200, $response);
});

/*
 * adding tv_series to collection
 */
$app->post('/collection/', 'authenticate', function() use ($app) {
    
    verifyRequiredParams(array('id_tv_series'));
    
    $id_tv_series = $app->request()->post('id_tv_series');
    
    global $user_id;
    $response = [];
    $db = new DbHandler();
    
    if ($db->addTvSeriesToCollection($id_tv_series ,$user_id)) {
        $response['error'] = FALSE;
    } else {
        $response['error'] = TRUE;
    }
    
    echoRespnse(200, $response);
});

/*
 * get single tv_series
 */
$app->get('/collection/:id', 'authenticate', function($id_tv_series) {
            global $user_id;
            $response = [];
            $db = new DbHandler();
 
            // fetch task
            $result = $db->getSingleTvSeries($id_tv_series);
 
            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id_tv_series"];
                $response["name"] = $result["tv_series_name"];
                $response["imdb_link"] = $result["imdb_link"];
                $response["likes"] = $result["count_like"];
                $response["rating"] = $result["count_rating"];
                $response["image"] = $result["default_image"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                echoRespnse(404, $response);
            }
        });

$app->run();