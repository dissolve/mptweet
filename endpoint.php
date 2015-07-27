<?php

require_once( __DIR__ . '/libraries/relmeauth/lib/relmeauth.php');
require_once( __DIR__ . '/mp.php');
require_once( __DIR__ . '/cache.php');

$me = 'http://relmeauth.thatmustbe.me';
$mp_endpoint = $me .'/endpoint.php';

$relmeauth = new relmeauth();
$error = false;

$token = $_POST['access_token'];
if(!$token){
    $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
    $token = $parts[1];
}
if(!$token){
    $parts = explode(' ', $headers['Authorization']);
    $token = $parts[1];
}

if(!$token){
      header('HTTP/1.1 401 Unauthorized');
      exit();
}


$storage = new storage();
$token_data = $storage->get_data('token.'.$token);

$relmeauth->create_from_data($token_data['provider'], $token_data['user_token'], $token_data['user_secret']) ;

if (isset($_POST['content'])) { 
  $tmhOAuth = $relmeauth->tmhOAuth;
  $tmhOAuth->request('POST', $tmhOAuth->url('1.1/statuses/update'), array(
      'status' => $_POST['content'] . ' ' . $_POST['url']
  ));

  if ($tmhOAuth->response['code'] == 200) {

      $response_obj = json_decode($tmhOAuth->response['response']);
      $permalink =  "https://twitter.com/".$response_obj->user->id."/status/".$response_obj->id;

      header('HTTP/1.1 201 Created');
      header('Location: '. $permalink);
      echo $permalink;
  } else {
      header('HTTP/1.1 401 Unauthorized');
      exit();
  }
} // /user posted

?>
