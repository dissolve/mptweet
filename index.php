<?php

require_once( __DIR__ . '/libraries/relmeauth/lib/relmeauth.php');
$relmeauth = new relmeauth();
$error = false;

if ( isset($_GET['logout']) ) {
  session_destroy();
  $url = $relmeauth->here();
  header( "Location: $url" );
  die();
}
elseif ( isset($_REQUEST['oauth_verifier'] ) ) {
  $ok = $relmeauth->complete_oauth( $_REQUEST['oauth_verifier'] );
  // error message on false!
  if($ok){
    $url = $relmeauth->here();
    header( "Location: $url" );
    die();
  }
}
elseif (isset($_REQUEST['denied'] ) ) {
  // user cancelled login
  $relmeauth->error('Sign in cancelled.');
}
elseif ( isset($_POST['url']) ) {
  $user_url = strip_tags( stripslashes( $_POST['url'] ) );

  $user_site = parse_url($user_url);
  if ($user_site['path']==='') { // fix-up domain only URLs with a path
    $user_url = $user_url . '/';
  }

  $_SESSION['relmeauth']['url'] = $user_url;

  // discover relme on the url
  $redir_url = $relmeauth->main( $user_url, true );
  if($redir_url){
    header( "Location: $redir_url" );
    die();
  }
}
elseif ( isset($_SESSION['token']) ) {
  echo 'token = ' . $_SESSION['token'];
}
elseif ( isset($_GET['conf']) ) {
  $me = 'https://relmeauth.thatmustbe.me';
  $relmeauth = new indieAuthRegister();
  $relmeauth->tokencallback($me,$me.'?conf=tok');
}
elseif ($relmeauth->is_loggedin()) {
  //$relmeauth->create_from_session();
  $me = 'https://relmeauth.thatmustbe.me';
  $relmeauth = new indieAuthRegister();
  $relmeauth->startReg($me,$me.'?conf=tok');
}

function _e($content) {
  echo htmlentities($content);
}

?><!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8" />
  <title>RelMeAuth prototype</title>
  <script src="cassis/cassis.js" type="text/javascript" charset="utf-8"></script>
  <style type="text/css" media="all">
    body {
      max-width: 960px;
      margin: 5em auto;
      padding:0 2em;
      font-size: 22px;
      font-family: Helvetica Neue, Helvetica, sans-serif;
    }
    form {
      text-align: center;
    }
    input[name="url"] {
      width: 10em;
      font-size: 100%;
    }
    button {
      font-size: 100%;
    }
    div#error {
      color: red;
      margin: 0.5em 0;
    }
    p.intro {
      font-size: 0.8em;
    }
    pre {
      font-size: 0.5em;
    }
    textarea { font:inherit; font-weight:normal; display:block }
    label#for-post { display:block; font-weight:bold }
  </style>
</head>

<body>
  <h1>RelMeAuth prototype</h1>
<?php if ($relmeauth->is_loggedin()) { ?>
  <p>You are logged in as <?php _e($_SESSION['relmeauth']['url']) ?> using
  <?php _e($_SESSION['relmeauth']['provider']) ?>. <a href="?logout=1">logout?</a></p>


  <form action="" method="POST">
    <label id="for-post" for="post">What's happening?</label>
    <textarea cols="40" rows="2" id="post" name="post" autofocus="autofocus"></textarea>
    <button type="submit">Tweet</button>
  </form>

<?php   if (isset($_POST['post'])) { // user posted ?>
        <p>Tweeting...</p>
<?php       $tmhOAuth = $relmeauth->tmhOAuth;
            $tmhOAuth->request('POST', $tmhOAuth->url('1.1/statuses/update'), array(
              'status' => $_POST['post']
            ));
?>
        <p>Twitter's API says:</p>
<?php     if ($tmhOAuth->response['code'] == 200) {
              print_r(json_decode($tmhOAuth->response['response']));
          } else {
              print_r($tmhOAuth->response['response']);
          }
        } // /user posted

    } else { // not logged in
      $relmeauth->printError(); ?>
        <p>This is a working prototype of <a href="http://microformats.org/wiki/RelMeAuth">RelMeAuth</a>.</p>
        <p>This is purely a test user interface. If this had been an actual user interface,
          you wouldn't be wondering what the hell is going on, what is "my domain",
          who am I, and why do I exist.</p>
        <p>This is only a test.</p>
        <p>Enter your personal web address, click Sign In, and see what happens.</p>

        <form action="" method="POST">
          <label for="url">Your domain:</label>
          <input type="url" required="required" name="url" id="url" style="width:17em"
            autofocus="autofocus"
            value="<?php echo @$_SESSION['relmeauth']['url'] ?>" />
          <button type="submit">Sign In</button>
        </form>

  <p>This checkbox is a metaphorical big red button you're supposed to try ignoring, or dare to click. It's only here because the OAuth allow permission page of the presumed destination <a href="http://blog.benward.me/post/968515729">doesn't have it<a>, which is really where it should be (so we don't have to <em>presume</em> the destination).</p>

  <p>It is likely there are still errors and any issues should be reported on the
  <a href="http://github.com/themattharris/RelMeAuth">GitHub Project Page</a>. This code is written by
  @<a href="http://twitter.com/themattharris" rel="me">themattharris</a> and @<a href="http://twitter.com/t">t</a>. It
  uses a modified OAuth PHP library.</p>
<?php } /*endif;*/ ?>

</body>
<script type="text/javascript" charset="utf-8">
  document.forms[0].onsubmit = function() {
    $input = document.getElementById('url');
    if ($input.value.replace(/^\s+|\s+$/g,"") == 'http://yourdomain.com') {
      $input.value = '';
    }
    else {
      $input.value = webaddresstouri($input.value, true);
    }
  }
  $input = document.getElementById('url');
  $input.onfocus = function() {
    if (this.value.replace(/^\s+|\s+$/g,"") == 'http://yourdomain.com') {
      this.value = '';
    }
  }
  $input.onclick = function() {
    this.focus();
    this.select();
  }
  $input.onblur = function() {
    if (this.value.replace(/^\s+|\s+$/g,"") == '') {
      this.value = 'http://yourdomain.com';
    } else {
      this.value = webaddresstouri(this.value, true);
    }
  }
  $input.oninvalid = function() {
    this.value = webaddresstouri(this.value, true);
    if (this.willValidate) {
      this.setCustomValidity('');
      this.parentNode.submit();
      return false;
    } else if (document.getElementById('error')) {
        return;
    } else {
      $html = document.createElement("div");
      $html.id = 'error';
      $html.innerHTML = "Oops! looks like you didn't enter a URL. Try starting with http://";
      this.parentNode.appendChild($html)
    }
  }
</script>
</html>

