# Imgixer Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.1.0 - 2024.07.16
### Added
- Backported support for Servd Assets Platform v3. If you are running a Craft 3 site on Servd, use Servd’s Asset Volumes, and want to upgrade from Servd’s Assets Platform v2 to v3, ensure all environments are updated to this version of Imgixer before initiating the asset platform upgrade.

### Changed
- The focal point for an asset will automatically be used when the parameters `fit:'crop'` and `crop:'focalpoint'` are set *without* explicit `fp-x` and `fp-y` parameters (backported from Craft 4/5 versions).

## 1.0.2 - 2021.05.20
- Released.

## 1.0.2-beta.3 - 2021.02.26
### Added
- Support for using Servd's image transform service.

### Changed
- Changed the timestamp parameter appended to image URLs from `mtime=` to `dm=`.

## 1.0.2-beta.2 - 2021.01.16
### Added
- Optionally replace Craft's native image transforms with Imgix.
- Pass assets directly to Imgixer, as well as image paths / urls.
- Append `mtime=` parameter to urls generated for assets, to break the cache when images are edited.

### Fixed
- Allow the `signed` parameter for a source to be set in the config as well as at the template level.

## 1.0.1 - 2019.05.13
### Fixed
- Imgixer was looking for a config value with the key 'url' to define the Imgix source domain for a given handle, when it should have been 'domain'.

### Changed
- Improved code comments and README.

## 1.0.0 - 2019.27.11
### Added
- Initial release
