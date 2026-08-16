# BDC v2.3.3-dev215

## Role-strict competitor search

- Leader search boxes show only competitors whose BDC ID is registered as Leader.
- Follower search boxes show only competitors whose BDC ID is registered as Follower.
- Manual and Automatic scoring use separate role-specific suggestion lists.
- Automatic entry validates the role again on the server, preventing a manually typed wrong-role BDC ID.
- Dual-role dancers continue using their separate Leader and Follower BDC IDs.

## Safety

- Existing competitors, scores, judge assignments and results are preserved.
- Changes are limited to the `develop` release line.
- Production is not modified by this release.
