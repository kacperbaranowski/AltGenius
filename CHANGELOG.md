# Changelog

All notable changes to AltGenius will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-01-29

### Added

- **Image Format Validation**: OpenAI format validation (png, jpeg, gif, webp) to prevent 400 errors from unsupported formats like SVG
- **Unsupported Formats KPI**: New statistics card showing count of images in unsupported formats
- **Model Limitations Alert**: Informational alert box on stats page explaining which formats the current AI model supports
- **Future Provider Support**: Extensible structure prepared for future AI providers (e.g., Gemini) with different format support

### Fixed

- SVG and other unsupported formats are now rejected before API calls, preventing unnecessary errors and API quota usage

### Technical

- Added `OPENAI_SUPPORTED_FORMATS` constant for centralized format management
- New `count_unsupported_formats()` method for database queries
- Enhanced `get_images_stats()` to include unsupported format count
- Improved error messages with specific format information

## [1.0.0] - 2026-01-28

### Added

- Initial release
- AI-powered ALT text generation using OpenAI GPT models
- Vision API integration with base64 image analysis
- Automated CRON job (every 5 minutes, 30 images per batch)
- Two-way Gutenberg sync between Media Library and image blocks
- Real-time statistics dashboard with KPI cards
- Bulk actions for selected images
- GitHub auto-update integration
- Detailed logging system
- Customizable prompts with context support
- Support for multiple GPT models (gpt-4o-mini, gpt-4.1, o3, o4-mini)

### Features

- **Automation**: 288 runs/day processing ~8,640 images daily
- **Stats Dashboard**: Total images, with ALT, without ALT, coverage percentage
- **Security**: Nonce verification, capability checks, secure API key storage
- **Performance**: Optimized for OpenAI Tier 1 (10,000 RPD)
