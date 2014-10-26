<?php

final class SecurityPolicyEnforcerTestCase extends PhabricatorTestCase {

  public function testCustomPolicy() {
    $policy = WMFSecurityPolicy::createCustomPolicy(
      $task,
      $user_phids,
      $project_phids,
      true,
      null,
      false);
  }
}
