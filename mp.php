
<?php  

require_once __DIR__ . '/libraries/php-mf2/Mf2/Parser.php';
require_once __DIR__ . '/libraries/link-rel-parser-php/src/IndieWeb/link_rel_parser.php';
require_once __DIR__ . '/libraries/indieauth-client-php/src/IndieAuth/Client.php';
require_once __DIR__ . '/cache.php';


class indieAuthRegister {
    public function __construct(){
        $this->storage = new storage();
    }
    public function startReg($me, $redir_url, $fail_url = false) {
        $scope = 'register';
        
        $me = $this->normalize_url($me);

        if(!$fail_url){
            $fail_url = $this->here();
        }

        //look up user's auth provider
        $auth_endpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);

        if(!$auth_endpoint){
            $_SESSION['error'] = 'No Auth Endpoint Found';
            header( "Location: $fail_url" );
            die();
        } else {

            //build our get request
            $trimmed_me = trim($me, '/'); //in case we get it back without the /
            $data_array = array(
                'me' => $me,
                'redirect_uri' => $redir_url,
                'response_type' => 'id',
                'state' => substr(md5($trimmed_me.$this->here()),0,8),
                'client_id' => $this->here()
            );
            if($scope){
                $data_array['scope'] = $scope;
                $data_array['response_type'] = 'code';
            }

            $get_data = http_build_query($data_array);

            //redirect to their provider
            $auth_redir = $auth_endpoint . (strpos($auth_endpoint, '?') === false ? '?' : '&') . $get_data;
            header( "Location: $auth_redir" );
            die();
        }
            
    }

    public function dataFromToken($token){
        $token_data = $this->storage->get_data('token.'.$token);
        if($token_data && $token_data['active']){
            return $token_data;
        } else {
            return array();
        }

    }
    /*
    public function tokenEndpoint(){
        if(isset($_POST['code']) && 
            isset($_POST['me']) &&
            isset($_POST['redirect_uri'])){

            $data = $this->storage->get_data('auth.'.$_POST['code']);


            if($data['me'] == $_POST['me'] && $data['redirect_uri'] == $_POST['redirect_uri']){

                $token_data = $this->storage->get_data('token.'.$data['token']);
                if(!$token_data){
                    header('HTTP/1.1 500 Server Error');
                    exit();
                }
                $this->storage->delete_data('auth.'.$data['auth']);
                $token_data['active'] = true;
                $this->storage->save_data('token.'.$data['token'], $token_data);


                $this->response->setOutput(http_build_query(array(
                    'access_token' => $token_data['token'],
                    'scope' => $token_data['scope'],
                    'me' => $token_data['me'])));
            } else {
                header('HTTP/1.1 400 Bad Request');
                exit();
            }
        } else {
            header('HTTP/1.1 400 Bad Request');
            exit();
        }

    }
*/

	public function tokencallback( $me, $redir_url, $success_url = false, $fail_url = false) {
        // first figure out where we are going after we process
        if(!$success_url){
            $success_url = $this->here();
        }

        $me = $this->normalize_url($_GET['me']);
        $code = $_GET['code'];
        $state = (isset($_GET['state']) ? $_GET['state'] : null); 

        $result = $this->confirm_auth($me, $code, $redir_url, $state);

        if($result){

            //lets try and see if they have an MP endpoint and if so, so they offer up the actions they have
            $mp_endpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
            // we successfullly confirmed auth
            $_SESSION['user_site'] = $_GET['me'];

            $token_results = $this->get_token($me, $code, $redir_url, $state);

            $_SESSION['token'] = $token_results['access_token'];
            $_SESSION['scope'] = $token_results['scope'];

            if($mp_endpoint){
                //$ch = curl_init($mp_endpoint.'?q=actions');
                //$code = md5(uniqid(rand()));
                $token = md5(uniqid(rand(), TRUE));

                $data = array('token' => $token,
                              //'active' => false,
                              //'scope' => 'write',
                              //'client_id' => $me,
                              //'me' => $this->here(),
                              //'redirect_uri' => $this->here(),
                              'provider' => $_SESSION['relmeauth']['provider'],
                              'user_token' => $_SESSION['relmeauth']['access']['oauth_token'],
                              'user_secret' => $_SESSION['relmeauth']['access']['oauth_token_secret']);

                //$this->storage->save_data('auth.'.$code, $data);
                $this->storage->save_data('token.'.$token, $data);
                //save data to date

                $ch = curl_init($mp_endpoint.'?register='.$this->here() . '&register_token='.$token);

                //if(!$ch){$this->log->write('error with curl_init');}

                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '. $_SESSION['token']));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                $response = curl_exec($ch);
                $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if($result == 200){
                    $_SESSION['mp-config'] = $response;
                }

                $_SESSION['success'] = "You are now logged in as ".$_GET['me'];
                header( "Location: $success_url" );
            } else {
                $_SESSION['error'] = 'Did not find your Micropub Endpoint.';
                header( "Location: $fail_url" );
                die();
            }
        } else {
            $_SESSION['error'] = 'Authorization Step Failed.';
            header( "Location: $fail_url" );
            die();
        }

    }

    private function confirm_auth( $me, $code, $redir, $state = null ) {
        
        $client_id = $this->here();

        //look up user's auth provider
        $auth_endpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);

        $post_array = array(
            'code'          => $code,
            'redirect_uri'  => $redir,
            'client_id'     => $client_id
        );
        if($state){
            $post_array['state'] = $state;
        }

        $post_data = http_build_query($post_array);

        $ch = curl_init($auth_endpoint);

        //if(!$ch){$this->log->write('error with curl_init');}

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $response = curl_exec($ch);

        $results = array();
        parse_str($response, $results);

        $results['me'] = $this->normalize_url($results['me']);

        $trimmed_me = trim($me, '/');
        $trimmed_result_me = trim($results['me'], '/');

        if($state){
            return ($trimmed_result_me == $trimmed_me && $state == substr(md5($trimmed_me.$client_id),0,8));
        } else {
            return $trimmed_result_me == $trimmed_me ;
        }

	}


    private function get_token( $me, $code, $redir, $state = null ) {
        
        $client_id = $this->here();

        //look up user's token provider
        $token_endpoint = IndieAuth\Client::discoverTokenEndpoint($me);


        $post_array = array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redir,
            'client_id'     => $client_id,
            'me'            => $me
        );
        if($state){
            $post_array['state'] = $state;
        }

        $post_data = http_build_query($post_array);

        $ch = curl_init($token_endpoint);

        //if(!$ch){$this->log->write('error with curl_init');}

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $response = curl_exec($ch);

        $results = array();
        parse_str($response, $results);

        
        return $results;
    }


    private function normalize_url($url) {
            $url = trim($url);
            if(strpos($url, 'http') !== 0){
                $url = 'http://'.$url;
            }
            return $url;
    }

  private function here($withqs=false) {
     $url = sprintf('%s://%s%s',
       $_SERVER['SERVER_PORT'] == 80 ? 'http' : 'https',
       $_SERVER['SERVER_NAME'],
       $_SERVER['REQUEST_URI']
     );
     $parts = parse_url($url);
     $url = sprintf('%s://%s%s',
       $parts['scheme'],
       $parts['host'],
       $parts['path']
     );
     if ($withqs) {
       $url .= '?' . $url['query'];
     }
     return $url;
   }
}
?>
