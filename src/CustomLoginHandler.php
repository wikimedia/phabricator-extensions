<?php

// Custom greeter on login page. See T963, T862, T116142

final class CustomLoginHandler
  extends PhabricatorAuthLoginHandler {

  public function getAuthLoginHeaderContent() {
    return phutil_safe_html(
      '<div style="font-weight:bold;font-size:1.8em;text-align:center">Log in or register to Wikimedia Phabricator</div><p style="font-size:1.1em;text-align:center;line-height:1.5;padding:10px">Click the MediaWiki button below to connect your <a href="//meta.wikimedia.org/wiki/Help:Unified_login">Wikimedia unified account</a>.<br>Alternatively, you can introduce your <a href="//wikitech.wikimedia.org">Labs/Gerrit</a> LDAP credentials.<br>In case of doubt, check the Phabricator Help: <a href="//www.mediawiki.org/wiki/Phabricator/Help#Creating_your_account">Creating your account</a>.</p>');
  }

}
