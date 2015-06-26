
<?php  

require_once DIR_BASE . 'libraries/php-mf2/Mf2/Parser.php';
require_once DIR_BASE . 'libraries/link-rel-parser-php/src/IndieWeb/link_rel_parser.php';
require_once DIR_BASE . 'libraries/indieauth-client-php/src/IndieAuth/Client.php';

class Indieauthregster {

    function __construct($me) {
        $scope = 'register';
        
        $me = $this->normalize_url($me);

        $fail_url = $this->here();

        //look up user's auth provider
        $auth_endpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);

            if(!$auth_endpoint){
                $_SESSION['error'] = 'No Auth Endpoint Found';
                header( "Location: $fail_url" );
                die();
            } else {

                $redir_url = $this->url->link('auth/login/callback', ($controller ? 'c='.$controller : ''), '');
                if($scope){
                    // if a scope is given we are actually looking to get a token
                    $redir_url = $this->url->link('auth/login/tokencallback', ($controller ? 'c='.$controller : ''), '');
                }

                //build our get request
                $trimmed_me = trim($me, '/'); //in case we get it back without the /
                $data_array = array(
                    'me' => $me,
                    'redirect_uri' => $redir_url,
                    'response_type' => 'id',
                    'state' => substr(md5($trimmed_me.$this->url->link('')),0,8),
                    'client_id' => $this->url->link('')
                );
                if($scope){
                    $data_array['scope'] = $scope;
                    $data_array['response_type'] = 'code';
                }

                $get_data = http_build_query($data_array);

                //redirect to their provider
                $redir_url = $auth_endpoint . (strpos($auth_endpoint, '?') === false ? '?' : '&') . $get_data;
                header( "Location: $redir_url" );
                die();
            }
            
    }

	public function callback() {

        // first figure out where we are going after we process
        $url = $this->url->link('');
        if(isset($_GET['c']) && !empty($_GET['c'])){
            $url = $this->url->link($_GET['c']);
        } elseif(isset($_SESSION['auth_redir']) && !empty($_SESSION['auth_redir'])){
            $url = $_SESSION['auth_redir'];
            unset($_SESSION['auth_redir']);
        }

        //recalculate the callback url
        $redir_url = $this->url->link('auth/login/callback','','');
        if(isset($_GET['c']) && !empty($_GET['c'])){
            $redir_url = $this->url->link('auth/login/callback', 'c='.$_GET['c'], '');
        }

        $me = $this->normalize_url($_GET['me']);
        $code = $_GET['code'];
        $state = (isset($_GET['state']) ? $_GET['state'] : null); 


        $result = $this->confirm_auth($me, $code, $redir_url, $state);

        if($result){

            //lets try and see if they have an MP endpoint and if so, so they offer up the actions they have
            $mp_endpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
            if($mp_endpoint){
                $ch = curl_init($mp_endpoint.'?q=actions');
                //$ch = curl_init($mp_endpoint.'?q=actions');

                //if(!$ch){$this->log->write('error with curl_init');}

                //curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                $response = curl_exec($ch);
                $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if($result == 200){
                    $_SESSION['mp-config'] = $response;
                }
            }

            // we successfullly confirmed auth
            $_SESSION['user_site'] = $_GET['me'];
            $_SESSION['success'] = "You are now logged in as ".$me;

            $token_user = str_replace(array('http://', 'https://'),array('',''), $me);

            $myself = trim($this->normalize_url(HTTP_SERVER),'/');
            $myself = trim(str_replace(array('http://', 'https://'),array('',''), $myself), '/');

            if($token_user == $myself) {
                $_SESSION['is_owner'] = true;
            }
        } else {
            $_SESSION['error'] = 'Authorization Failed.';
        }

        header( "Location: $url" );
        die();
    }


	public function tokencallback() {
        // first figure out where we are going after we process
        $url = $this->url->link('');
        if(isset($_GET['c']) && !empty($_GET['c'])){
            $url = $this->url->link($_GET['c']);
        } elseif(isset($_SESSION['auth_redir']) && !empty($_SESSION['auth_redir'])){
            $url = $_SESSION['auth_redir'];
            unset($_SESSION['auth_redir']);
        }

        //recalculate the callback url
        $redir_url = $this->url->link('auth/login/tokencallback', '', '');
        if(isset($_GET['c']) && !empty($_GET['c'])){
            $redir_url = $this->url->link('auth/login/tokencallback', 'c='.$_GET['c'], '');
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

            //TODO token stuff
            $token_results = $this->get_token($me, $code, $redir_url, $state);

            $_SESSION['token'] = $token_results['access_token'];
            $_SESSION['scope'] = $token_results['scope'];
            if($mp_endpoint){
                //$ch = curl_init($mp_endpoint.'?q=actions');
                $ch = curl_init($mp_endpoint.'?q=actions');

                //if(!$ch){$this->log->write('error with curl_init');}

                //curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json'));
                //if(isset($token_results['access_token'])){
                    //curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Authorization: Bearer '. $token_results['access_token']));
                //}
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                $response = curl_exec($ch);
                $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if($result == 200){
                    $_SESSION['mp-config'] = $response;
                }
            }

            $token_user = str_replace(array('http://', 'https://'),array('',''), $me);

            $myself = trim($this->normalize_url(HTTP_SERVER),'/');
            $myself = trim(str_replace(array('http://', 'https://'),array('',''), $myself), '/');

            if($token_user == $myself) {
                $_SESSION['is_owner'] = true;
            }

            $_SESSION['success'] = "You are now logged in as ".$_GET['me'];
        } else {
            $_SESSION['error'] = 'Authorization Step Failed.';
        }

        header( "Location: $url" );
        die();
    }

    private function confirm_auth( $me, $code, $redir, $state = null ) {
        
        $client_id = $this->url->link('');

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
        
        $client_id = $this->url->link('');

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
