== About this repo ==

This repo contains misc. WMF-specific extensions to phabricator.

These extensions provide some basic custom functionality and integration
with wikimedia's systems.

* `MediaWikiUserpageCustomField`: provides a custom field on user profile pages that can link to a user's wiki userpage.
* `PhabricatorMediaWikiAuthProvider` and `PhutilMediaWikiAuthAdapter`: a custom authentication provider that uses mediawiki oauth to authenticate phabricator logins.
* `GerritApplication` and `GerritProjectController`: Handles redirecting links from gerrit to diffusion repositories. This allows diffusion to replace gitblit as the primary way to browse the source code of various wikimedia projects. This is part of our gradual migration away from using Gerrit for code review.


== Other Extensions ==

* https://phabricator.wikimedia.org/diffusion/PHES/
* https://phabricator.wikimedia.org/diffusion/PHSP/
