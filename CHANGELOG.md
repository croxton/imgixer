# Imgixer Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.0.2 - 2021-20-05
- Released.

## 1.0.2-beta.3 - 2021-26-02
### Added
- Support for using Servd's image transform service.

### Changed
- Changed the timestamp parameter appended to image URLs from `mtime=` to `dm=`.

## 1.0.2-beta.2 - 2021-16-01
### Added
- Optionally replace Craft's native image transforms with Imgix.
- Pass assets directly to Imgixer, as well as image paths / urls.
- Append `mtime=` parameter to urls generated for assets, to break the cache when images are edited.

### Fixed
- Allow the `signed` parameter for a source to be set in the config as well as at the template level.

## 1.0.1 - 2019-13-05
### Fixed
- Imgixer was looking for a config value with the key 'url' to define the Imgix source domain for a given handle, when it should have been 'domain'.

### Changed
- Improved code comments and README.

## 1.0.0 - 2019-11-27
### Added
- Initial release
