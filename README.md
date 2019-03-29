# About this repository

This repository contains miscellaneous extensions to Phabricator which are
specialized for the needs of the Wikimedia Foundation's Phabricator instance
at https://phabricator.wikimedia.org

These extensions provide some basic custom functionality and integration with
Wikimedia's systems.

## Installation


This Repository consists of a single libphutil module which can be used in
phabricator by simply adding the repository root to the list of library paths
specified by the key `load-libraries` within phabricator's config.

### For example:

```json
"load-libraries": [
  "/path/to/this/repository/",
  "/path/to/another/extension/"
]
```

For more details, see [this article](https://secure.phabricator.com/book/phabcontrib/article/adding_new_classes/#linking-with-phabricator) in the phabricator documentation.

## Overview of extensions

The extensions are under the `src/` directory, organized into sub-directories
by extension type.

### src/oauth

`PhabricatorMediaWikiAuthProvider` and `PhutilMediaWikiAuthAdapter` constitute
an `authentication provider adapter` that enables Phabricator to use OAuth
federation to offload phabricator logins to Mediawiki's OAuth1 endpoint.

### src/customfields

Custom fields are extensions which add a field to various objects in
Phabricator. Wikimedia makes use of a few custom fields to extend user profile
pages and Differential code review pages.

#### `MediaWikiUserpageCustomField`
This custom field is used on phabricator user profile
pages, displays a link to a user's wiki userpage. The wiki userpage url is
discovered by looking up the link which is created by
`PhabricatorMediaWikiAuthProvider` when a user links their mediawiki login to
their phabricator account.

#### `LDAPUserpageCustomField`
Another custom field used on phabricator user profile pages
which simply displays the ldap username that is associated with the user's
phabricator account.

#### `DifferentialApplyPatchWithOnlyGitField`
A Differential custom field which displays a unix command line which can be
copied and pasted into a shell in order to download and apply the patch for a
given Differential revision. This is mainly useful for users who do not have
`arcanist` installed, providing an alternative way to apply patches.

### src/gerrit
Migration-related extensions facilitating the migration of Wikimedia code review
from gerrit to differential.

`GerritApplication` and `GerritProjectController` handle redirecting links from
`gerrit` to `diffusion` repositories.

This allows [diffusion](https://phabricator.wikimedia.org/diffusion/) to replace
[git.wikimedia.org](http://git.wikimedia.org) as the primary way to browse the
source code of various Wikimedia projects. The reason this is necessary is
because gerrit projects have a hierarchical structure which doesn't map directly
to phabricator's flat repository namespace. So our solution was to implement
url routing in phabricator with a static map between the old gerrit urls and the
corresponding repository "callsigns." The mapping is a static array contained
in `src/gerrit/GerritProjectMap.php` and `GerritProjectListController` handles
printing the full mapping as a list of html links as seen [here](https://phabricator.wikimedia.org/r/).

### Security Policy Extensions

See [src/policy/README](https://phabricator.wikimedia.org/source/phab-extensions/browse/wmf%252Fstable/src/policy/)

### Transaction Rollback

There is a command line tool in bin/rollback which can be used to roll back transactional edits on Maniphest tasks. Other phabricator applications could be supported with a little effort, however, none are supported currently. See `src/workflow/RollbackTransactionsWorkflow.php`

### Other Extensions

 These other extensions are used/maintained for use with Wikimedia's
 phabricator installation.

* {rPHSP} - https://phabricator.wikimedia.org/diffusion/PHSP/
