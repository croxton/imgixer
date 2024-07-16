# Imgixer Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.0.1 - 2024.07.16
### Added
- Added a `craft` provider to generate native Craft transforms using Imgixer's core parameter set.
- Stable release.

## 3.0.0-beta.1 - 2024.03.24
- Craft 5 version.

## 2.1.3 - 2023.05.03
### Added
- Support for Servd's Asset Platform v3.

## 2.1.2 - 2023.02.23
### Fixed
- Fixed image previews in SEOmatic link cards.

## 2.1.1 - 2023.02.23
### Added
- Use Craft's own versioning helper for revving asset URLs. 
- Stable release.

## 2.1.0-beta.1 - 2023.01.16
### Added
- Added [ImageKit](https://imagekit.io/) as an optional image transform provider.
- Defined `core` and `extended` parameter sets for supported providers.
- Added support for images served from local filesystem subfolders ([#7](https://github.com/croxton/imgixer/issues/7)).
- Focal point parameters (`fp-x` and `fp-y`) are now set automatically from the Asset (if not otherwise specified) when using `crop:'focalpoint'` ([#8](https://github.com/croxton/imgixer/issues/8)).
- Improved native transform support and thumbnail generation in the control panel.
- 
### Fixed
- Default parameters now work properly.
- 
### Changed
- These source parameters have been changed (the old parameters are still supported, no changes are required to existing projects):
    - `domain` is now `endpoint`
    - `key` is now `privateKey`
- Updated docs.

## 2.0.0-beta.1 - 2022.06.26
- Craft 4 version.

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
