<?php

/**
 * Authentication adapter for MediaWiki OAuth1.
 */
final class PhutilMediaWikiAuthAdapter
    extends PhutilOAuth1AuthAdapter {

  private $userinfo;
  private $domain = '';
  private $mediaWikiBaseURI = '';
  private $callback_uri = '';

  public function getWikiPageURI($title, $query_params = null) {
    $uri = $this->mediaWikiBaseURI;
    if (substr($uri, -1) != '/') {
      $uri .= '/';
    }
    if (!is_array($query_params)) {
      $query_params = array();
    }
    $query_params['title'] = $title;
    return rawurldecode( $uri.'index.php?'.
        http_build_query(
          $query_params,
          '',
          '&'));
  }

  public function getAccountID() {
    $this->getHandshakeData();
    return idx($this->getUserInfo(), 'userid');
  }

  public function getAccountName() {
    return idx($this->getUserInfo(), 'username');
  }

  public function getAccountURI() {
    $name = $this->getAccountName();
    if (strlen($name)) {
      return $this->getWikiPageURI('User:'.$name);
    }
    return null;
  }

  public function getAccountImageURI() {
    $info = $this->getUserInfo();
    return idx($info, 'profile_image_url');
  }

  public function getAccountRealName() {
    $info = $this->getUserInfo();
    return idx($info, 'name');
  }

  public function getAdapterType() {
    return 'mediawiki';
  }

  public function getAdapterDomain() {
    return $this->domain;
  }

  /* mediawiki oauth needs the callback uri to be "oob"
   (out of band callback) */
  public function getCallbackURI() {
    return $this->callback_uri;
  }

  public function setCallbackURI($uri) {
    $this->callback_uri = $uri;
  }

  public function shouldAddCSRFTokenToCallbackURI() {
    return false;
  }

  protected function getRequestTokenURI() {
    $callback = $this->getCallbackURI();
    return $this->getWikiPageURI('Special:OAuth/initiate',
                  array('oauth_callback' => $callback));
  }

  protected function getAuthorizeTokenURI() {
    return $this->getWikiPageURI('Special:OAuth/authorize');
  }

  public function setAdapterDomain($domain) {
    $this->domain = $domain;
    return $this;
  }

  public function setMediaWikiBaseURI($uri) {
    $this->mediaWikiBaseURI = $uri;
    return $this;
  }

  public function getClientRedirectURI() {
    $p = parent::getClientRedirectURI();
    return $p."&oauth_consumer_key={$this->getConsumerKey()}";
  }

  protected function getValidateTokenURI() {
    return $this->getWikiPageURI('Special:OAuth/token');
  }

  private function getUserInfo() {
    if ($this->userinfo === null) {
      $uri = new PhutilURI(
        $this->getWikiPageURI('Special:OAuth/identify',
                        array('format' => 'json')));

      // We gen this so we can check for replay below:
      $nonce = Filesystem::readRandomCharacters(32);
      list($body) = $this->newOAuth1Future($uri)
        ->setMethod('GET')
        ->setNonce($nonce)
        ->resolvex();
      $this->userinfo = $this->decodeAndVerifyJWT($body, $nonce);
    }
    return $this->userinfo;
  }

  protected function willProcessTokenRequestResponse($body) {
    if (substr_count($body, 'Error:') > 0) {
      throw new Exception(
        pht('OAuth provider returned an error response.'));
    }
  }

  /**
   * MediaWiki uses a signed JWT to assert the user's identity
   * here we verify the identity, not just the jwt signature.
   */
  private function decodeAndVerifyJWT($jwt, $nonce) {
    $userinfo = array();
    $identity = $this->decodeJWT($jwt);
    $iss_uri = new PhutilURI($identity->iss);
    $expected_uri = new PhutilURI($this->mediaWikiBaseURI);

    $now = time();

    if ($iss_uri->getDomain() !== $expected_uri->getDomain()) {
      throw new Exception(
        pht('OAuth JWT iss didn\'t match expected server name'));
    }
    if ($identity->aud !== $this->getConsumerKey()) {
      throw new Exception(
        pht('OAuth JWT aud didn\'t match expected consumer key'));
    }
    if ($identity->iat > $now || $identity->exp < $now) {
      throw new Exception(
        pht('OAuth JWT wasn\'t valid at this time'));
    }
    if ($identity->nonce !== $nonce) {
      throw new Exception(
        pht('OAuth JWT nonce didn\'t match what we sent.'));
    }
    if ($identity->blocked) {
      throw new Exception(
        pht('OAuth error: this account has been blocked in MediaWiki.'));
    }

    $userinfo['userid'] = $identity->sub;
    $userinfo['username'] = $identity->username;
    $userinfo['groups'] = $identity->groups;
    $userinfo['blocked'] = $identity->blocked;
    $userinfo['editcount'] = $identity->editcount;
    return $userinfo;
  }

  /** decode a JWT and verify the signature is valid */
  private function decodeJWT($jwt) {
    list($headb64, $bodyb64, $sigb64) = explode('.', $jwt);

    $header = json_decode($this->urlsafeB64Decode($headb64));
    $body = json_decode($this->urlsafeB64Decode($bodyb64));
    $sig = $this->urlsafeB64Decode($sigb64);

    $expect_sig = hash_hmac(
        'sha256',
        "$headb64.$bodyb64",
        $this->getConsumerSecret()->openEnvelope(),
        true);

    // MediaWiki will only use sha256 hmac (HS256) for now.
    // This checks that an attacker doesn't return invalid JWT signature type.
    if ($header->alg !== 'HS256' ||
        !$this->compareHash($sig, $expect_sig)) {
      throw new Exception('Invalid JWT signature from /identify.');
    }

    return $body;
  }

  private function urlsafeB64Decode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
      $padlen = 4 - $remainder;
      $input .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($input, '-_', '+/'));
  }

  /** return true if hash1 has the same value as hash2 */
  private function compareHash($hash1, $hash2) {
    $result = strlen($hash1) ^ strlen($hash2);
    $len = min(strlen($hash1), strlen($hash2));
    for ($i = 0; $i < $len; $i++) {
        $result |= ord($hash1{$i}) ^ ord($hash2{$i});
    }
    // this is just a constant time compare of the two hash strings
    return $result == 0;
  }
}
