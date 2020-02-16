<?php

include_once('Util.php');

header('Content-Type: application/json;charset=utf-8');

try {
  $boardID = $_GET["BoardID"];
  $clientID = NULL;
  $beginDate = NULL;
  
  if (isset($_GET["ClientID"])) {
    $clientID = $_GET["ClientID"];  
  }
  
  if (isset($_GET["BeginDate"])) {
    $beginDate = $_GET["BeginDate"];
  }
  
  $pdo = getPDO();
  if ($clientID === NULL) {
    $data = getAllSegments($pdo, $boardID);
  } else {
    $data = getSegmentsFromOtherClients($pdo, $boardID, $clientID, $beginDate);  
  }
  
  $retArr = array('status' => 'success',
                  'segments' => $data);
  $j = json_encode($retArr);
  echo $j;
  // HACK to adjust for difference in encoding PHP 5.2 and 5.5. 
  // echo fixEscaping($j); 
} catch (Exception $e) {
  $retArr = array('status' => 'error', 
                  'message' => $e->getMessage());
  echo json_encode($retArr);
}

function getSegmentsFromOtherClients($pdo, $boardID, $clientID, $beginDate) {
  $sql = "select data, creationDate from segments where boardID = ? and clientID <> ? and creationDate > ? order by creationDate";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array($boardID, $clientID, $beginDate));
  $rows = $stmt->fetchAll();
  
  $dataArr = array();
  foreach ($rows as $row) {
    array_push($dataArr, $row['Data']);
  }
  // Add the latest segment date
  if (count($rows) > 0) {
    array_push($dataArr, $row['CreationDate']);
  }
  
  return $dataArr;
}

function getAllSegments($pdo, $boardID) {
  $sql = "select data, CreationDate from segments where boardID = ? order by creationDate";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array($boardID));
  $rows = $stmt->fetchAll();
  
  $dataArr = array();
  foreach ($rows as $row) {
    array_push($dataArr, $row['Data']);
  }
  // Add the latest segment date
  if (count($rows) > 0) {
    array_push($dataArr, $row['CreationDate']);
  }
  
  return $dataArr;
}

?>
