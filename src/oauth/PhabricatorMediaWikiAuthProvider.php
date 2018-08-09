<?php

final class PhabricatorMediaWikiAuthProvider
  extends PhabricatorOAuth1AuthProvider {

  const PROPERTY_MEDIAWIKI_NAME = 'oauth1:mediawiki:name';
  const PROPERTY_MEDIAWIKI_URI = 'oauth1:mediawiki:uri';
  const PROPERTY_PRIVATE_KEY = 'oauth1:mediawiki:key:private';
  const PROPERTY_PUBLIC_KEY = 'oauth1:mediawiki:key:public';

  public function getProviderConfig() {
      $config = parent::getProviderConfig();
      $config->setProviderType('mediawiki');
      return $config;
  }

  public function readFormValuesFromProvider() {
    $config = $this->getProviderConfig();
    return array(
      self::PROPERTY_MEDIAWIKI_NAME =>
        $this->getProviderDomain(),
      self::PROPERTY_MEDIAWIKI_URI =>
        $config->getProperty(self::PROPERTY_MEDIAWIKI_URI),
      self::PROPERTY_CONSUMER_KEY =>
        $config->getProperty(self::PROPERTY_CONSUMER_KEY),
      self::PROPERTY_CONSUMER_SECRET =>
        $config->getProperty(self::PROPERTY_CONSUMER_SECRET),
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    $is_setup = $this->isSetup();
    if ($is_setup) {
      $name = $request->getStr(self::PROPERTY_MEDIAWIKI_NAME);
    } else {
      $name = $this->getProviderDomain();
    }

    return array(
      self::PROPERTY_MEDIAWIKI_NAME => $name,
      self::PROPERTY_MEDIAWIKI_URI =>
        $request->getStr(self::PROPERTY_MEDIAWIKI_URI),
      self::PROPERTY_CONSUMER_KEY =>
        $request->getStr(self::PROPERTY_CONSUMER_KEY),
      self::PROPERTY_CONSUMER_SECRET =>
        $request->getStr(self::PROPERTY_CONSUMER_SECRET),
    );
  }

  public function getProviderName() {
    return pht('MediaWiki');
  }

  public function getWikiURI() {
    $config = $this->getProviderConfig();
    $uri = $config->getProperty(self::PROPERTY_MEDIAWIKI_URI);
    $uri = new PhutilURI($uri);
    $normalized = $uri->getProtocol().'://'.$uri->getDomain();
    if ($uri->getPort() != 80 && $uri->getPort() != 443) {
      $normalized .= ':'.$uri->getPort();
    }
    if (strlen(($uri->getPath())) > 0 && $uri->getPath() !== '/') {
      $normalized .= $uri->getPath();
    }
    if (substr($normalized, -1) == '/') {
      $normalized = substr($normalized, 0, -1);
    }
    return $normalized;
  }

  protected function getProviderConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());
    if ($this->isSetup()) {
      return pht(
        "**Step 1 of 2**: Provide the name and URI for your MediaWiki install.\n\n".
        "In the next step, you will create an auth consumer in MediaWiki to be used by Phabricator oauth.");
    } else {
      $wiki_uri = $this->getWikiURI();
      return pht(
        "**Step 2 of 2**: Create a MediaWiki auth consumer for this Phabricator instance.".
        "\n\n".
        "NOTE: Propose a consumer with the form at this url: %s".
        "\n\n".
        "Provide the following settings on the consumer registration:\n\n".
        "  - **Callback URL:** Set this to: `%s`\n".
        "  - **Grants:** `Basic Rights` is all that is needed for authentication.\n".
        "\n\n".
        "After you register the consumer, a **Consumer Key** and ".
        "**Consumer Secret** will be provided to you by MediaWiki. ".
        "To complete configuration of phabricator, copy the provided keys into ".
        "the corresponding fields above.".
        "\n\n".
        "NOTE: Before Phabricator can successfully authenticate to your MediaWiki,".
        " a wiki admin must approve the oauth consumer registration using the form".
        " which can be found at the following url: %s",
        $wiki_uri.'/index.php?title=Special:OAuthConsumerRegistration/propose',
        $login_uri,
        $wiki_uri.'/index.php?title=Special:OAuthManageConsumers/proposed');
    }
  }

  protected function newOAuthAdapter() {
    $config = $this->getProviderConfig();

    return id(new PhutilMediaWikiAuthAdapter())
      ->setAdapterDomain($config->getProviderDomain())
      ->setMediaWikiBaseURI($this->getWikiURI());
  }

  protected function getLoginIcon() {
    return 'MediaWiki';
  }

  private function isSetup() {
    return !$this->getProviderConfig()->getID();
  }

  public function hasSetupStep() {
    return true;
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {
    $errors = array();
    $issues = array();

    $is_setup = $this->isSetup();

    $key_name = self::PROPERTY_MEDIAWIKI_NAME;
    $key_uri = self::PROPERTY_MEDIAWIKI_URI;
    $key_secret = self::PROPERTY_CONSUMER_SECRET;
    $key_consumer = self::PROPERTY_CONSUMER_KEY;

    if (!strlen($values[$key_uri])) {
      $errors[] = pht('MediaWiki base URI is required.');
      $issues[$key_uri] = pht('Required');
    } else {
      $uri = new PhutilURI($values[$key_uri]);
      if (!$uri->getProtocol()) {
        $errors[] = pht(
          'MediaWiki base URI should include protocol '
         .'(like "https://").');
        $issues[$key_uri] = pht('Invalid');
      }
    }

    if (!$is_setup && !strlen($values[$key_secret])) {
      $errors[] = pht('Consumer Secret is required');
      $issues[$key_secret] = pht('Required');
    }

    if (!$is_setup && !strlen($values[$key_consumer])) {
      $errors[] = pht('Consumer Key is required');
      $issues[$key_consumer] = pht('Required');
    }

    if (!count($errors)) {
      $config = $this->getProviderConfig();
      $config->setProviderDomain($values[$key_name]);
      $config->setProperty($key_name, $values[$key_name]);
      if ($is_setup) {


        $config->setProperty($key_uri, $values[$key_uri]);
      } else {
        $config->setProperty($key_uri, $values[$key_uri]);
        $config->setProperty($key_secret, $values[$key_secret]);
        $config->setProperty($key_consumer, $values[$key_consumer]);
      }
      $config->save();
    }
    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $is_setup = $this->isSetup();

    $e_required = $request->isFormPost() ? null : true;

    $v_name = $values[self::PROPERTY_MEDIAWIKI_NAME];
    if ($is_setup) {
      $e_name = idx($issues, self::PROPERTY_MEDIAWIKI_NAME, $e_required);
    } else {
      $e_name = null;
    }

    $v_uri = $values[self::PROPERTY_MEDIAWIKI_URI];
    $e_uri = idx($issues, self::PROPERTY_MEDIAWIKI_URI, $e_required);

    $config = $this->getProviderConfig();

    if ($is_setup) {
      $form
        ->appendRemarkupInstructions(
          pht(
            "**MediaWiki Instance Name**\n\n".
            "Choose a permanent name for this instance of MediaWiki.".
            "Phabricator uses this name internally to keep track of ".
            "this instance of MediaWiki, in case the URL changes later.".
            "\n\n".
            "Use lowercase letters, digits, and period. For example: ".
            "\n\n`mediawiki`, `mediawiki.mycompany` ".
            "or `mediawiki.engineering` are reasonable names."))
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('MediaWiki Instance Name'))
            ->setValue($v_name)
            ->setName(self::PROPERTY_MEDIAWIKI_NAME)
            ->setError($e_name));
    } else {
      $form->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('MediaWiki Instance Name'))
            ->setValue($v_name)
            ->setName(self::PROPERTY_MEDIAWIKI_NAME)
            ->setDisabled(true)
            ->setError($e_name));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('MediaWiki Base URI'))
          ->setValue($v_uri)
          ->setName(self::PROPERTY_MEDIAWIKI_URI)
          ->setPlaceholder('https://www.mediawiki.org/w')
          ->setCaption(pht('The full URL to your MediaWiki install, up to but not including "index.php"'))
          ->setError($e_uri));

    if (!$is_setup) {
      if (!strlen($config->getProperty(self::PROPERTY_CONSUMER_KEY))) {
        $form->appendRemarkupInstructions(
          pht(
            'NOTE: Copy the keys generated by the MediaWiki OAuth'.
            ' consumer registration and paste them here.'));
      }

      $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Consumer Key'))
          ->setName(self::PROPERTY_CONSUMER_KEY)
          ->setValue($values[self::PROPERTY_CONSUMER_KEY]))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Secret Key'))
          ->setName(self::PROPERTY_CONSUMER_SECRET)
          ->setValue($values[self::PROPERTY_CONSUMER_SECRET]));
    }
  }

  public static function getMediaWikiProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorMediaWikiAuthProvider) {
        return $provider;
      }
    }

    return null;
  }

  protected function getContentSecurityPolicyFormActions() {

    $csp_actions = $this->getAdapter()->getContentSecurityPolicyFormActions();
    $uri = new phutilURI($csp_actions[0]);
    $mobile_uri = new phutilURI($uri);
    $domain = preg_replace('/^www\./', 'm.', $uri->getDomain());
    $mobile_uri->setDomain($domain);
    if ((string)$uri != (string)$mobile_uri) {
      $csp_actions[] = (string)$mobile_uri;
    }
    return $csp_actions;
  }
}
