<?php

require_once( __DIR__ . '/libraries/relmeauth/lib/relmeauth.php');
require_once( __DIR__ . '/mp.php');
require_once( __DIR__ . '/cache.php');

$me = 'http://relmeauth.thatmustbe.me';
$mp_endpoint = $me .'/endpoint.php'

$relmeauth = new relmeauth();
$error = false;

$token = $this->request->post['access_token'];
if(!$token){
    $parts = explode(' ', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    $token = $parts[1];
}
if(!$token){
    $parts = explode(' ', $headers['Authorization']);
    $token = $parts[1];
}
$token_data = $this->storage->get_data('token.'.$token);

$relmeauth->create_from_data($token_data['provider'], $token, $token_data['user_secret']) {

if (isset($_POST['content'])) { 
  $tmhOAuth = $relmeauth->tmhOAuth;
  $tmhOAuth->request('POST', $tmhOAuth->url('1.1/statuses/update'), array(
      'status' => $_POST['content'] . ' ' . $_POST['url']
  ));

  if ($tmhOAuth->response['code'] == 200) {
        //$this->response->addHeader('HTTP/1.1 200 OK');

      print_r(json_decode($tmhOAuth->response['response']));
  } else {
        //$this->response->addHeader('HTTP/1.1 500 Error');
      print_r($tmhOAuth->response['response']);
  }
} // /user posted

?>
