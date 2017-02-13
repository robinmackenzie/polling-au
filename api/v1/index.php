<?php
// intialise slim
require(__DIR__.'/../../vendor/autoload.php');
use \Slim\App;
$app = new App();

// require db service
require_once(__DIR__.'/dbservice.php');
// require request manager
require_once(__DIR__.'/requestmanager.php');

// rate-limit middleware
$rateLimit = function($request, $response, $next) {
  // get request manager and client ip
  $manager = new apiRequestManager();
  $ip = $manager->getIp();

  // head of middleware - check count of todays requests
  $dailyLimitCount = $manager::DAILY_REQUEST_LIMIT;
  $dailyRequestCounter = $manager->countRequestsForDay($ip)[0];
 
  // continue with route logic if not exceeded daily limit
  if ($dailyRequestCounter <= $dailyLimitCount) {
    // middle of middleware - allow route logic to run 
    $response = $next($request, $response);
    // json decode the output as array
    $result = json_decode($response->getBody(), true);
    // increment api request
    $manager->logRequest($ip, time(), $request->getUri());
  } else {
    $response = $response->withStatus(429);
    $result = array();
    $result['warning'] = $manager::LIMIT_WARNING.$ip;
  }
  
  // tail of middleware
  // add request count for day
  $result['dailyRequestCounter'] = $dailyRequestCounter;

  // return response
  $response = $response->withJson($result);
    
  return $response;

};

// polling places data
$app->get('/pollingplaces', function ($request, $response, $args) {
  try {   
    // api output
    $result = array();
    $status = 0;

    // get database access layer
    $db = new dbservice();
    
    // query data
    $result['data'] = $db->getPollingPlaceData();
 
    // http status = all good
    $status = 200;
    
  } catch (Exception $e) {
    // error handler
    $status = 500;
    $result['error'] = $e->getMessage();
    
  } finally {
    // return response
    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    $response = $response->withStatus($status);
    $response = $response->write(json_encode($result));
    return $response;   
  }
})->add($rateLimit);

// votes by polling place
$app->get('/votes/{year}/{id}', function ($request, $response, $args) {
  try {   
    // api output
    $result = array();
    $status = 0;

    // get database access layer
    $db = new dbservice();
    
    // query data
    $result['data'] = $db->getVotesByPollingPlace($args['id'], $args['year']);
     
    // http status = all good
    $status = 200;
    
  } catch (Exception $e) {
    // error handler
    $status = 500;
    $result['error'] = $e->getMessage();
    
  } finally {
    // return response
    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    $response = $response->withStatus($status);
    $response = $response->write(json_encode($result));
    return $response;   
  }
})->add($rateLimit);

// run slim
$app->run();
