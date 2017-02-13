<?php

class apiRequestManager {
  
  const DSN = 'sqlite:requests.sqlite';
  const REQUEST_FACT = 'requests';
  const INSERT_SQL = 'insert into '.self::REQUEST_FACT.' (ip, ts, url) values (?, ?, ?)';
  const COUNT_SQL = 'select count(ip) from '.self::REQUEST_FACT.' where ip=:ip and ts between :start and :end';
  const DAILY_REQUEST_LIMIT = 250;
  const LIMIT_WARNING = 'Daily request limit reached for IP: ';
  
  public $db;
  
  public function __construct() {
    // db initialisation plus set error level
    $this->db = new PDO(self::DSN);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    
  }

  public function logRequest($ip, $ts, $url) {
    $sql = self::INSERT_SQL;
    $insert = $this->db->prepare($sql);
    $insert->execute(array($ip, $ts, $url));
  }
  
  public function countRequestsForDay($ip) {
    $requestCount = 0;
    $todayStartSeconds = strtotime('midnight', time());
    $todayEndSeconds = strtotime('tomorrow', $todayStartSeconds) - 1;
    $bindings = array();
    $bindings[':ip'] = $ip;
    $bindings[':start'] = $todayStartSeconds;
    $bindings[':end'] = $todayEndSeconds;
    $sql = self::COUNT_SQL;
    $query = $this->db->prepare($sql);
    $query->execute($bindings);
    $requestCount = $query->fetchAll(PDO::FETCH_COLUMN);
    return $requestCount;
  }

  public function getIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      //check ip from share internet
      $ip=$_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      //to check ip is pass from proxy
      $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip=$_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }  
 
}
