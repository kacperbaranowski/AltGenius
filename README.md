# AltGenius - AI-Powered ALT Text Generator for WordPress

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-orange.svg)

**AltGenius** is an advanced WordPress plugin for automatically generating alternative texts (ALT) for images using OpenAI artificial intelligence. The plugin significantly improves website accessibility and SEO through intelligent image descriptions.

## 🚀 Key Features

### 🤖 Automatic ALT Generation
- **AI Generation:** Uses GPT models (gpt-4o-mini, gpt-4.1, o3, o4-mini) to create accurate, contextual image descriptions
- **Vision API:** Direct image analysis (base64) instead of URL
- **Content Context:** Automatic consideration of post/page/product context

### ⚡ Automation (CRON)
- **Frequency:** Every 5 minutes (288×/day) - runs automatically in the background
- **Limit:** 30 images per run
- **Performance:** ~8,640 requests/day (safe for OpenAI Tier 1: 10,000/day)
- **Detailed logging** of all operations to `logs/alt-scan-log.txt`

### 🔄 Gutenberg Sync
- **Two-way ALT synchronization** between Media Library and Gutenberg image blocks
- **Automatic update:** ALT change in library → update in all posts
- **Reverse sync:** ALT edit in Gutenberg → save to library
- **Block support:** `wp:image` and classic `<img>`

### 📊 Statistics Dashboard
- **Dedicated top-level menu** in WordPress
- **Real-time KPIs:**
  - All images in library
  - Images with ALT
  - Images without ALT
  - Coverage percentage
- **Unsupported formats** (SVG, etc.) - shows how many images cannot be processed
- **Cron Status:** Whether active, when next run
- **Model Information:** Alert showing supported image formats and limitations of selected AI model

### 🛡️ Format Validation
- **Supported OpenAI formats:** PNG, JPEG, JPG, GIF, WEBP
- **Automatic validation:** SVG and other unsupported formats are rejected before sending to API
- **Savings:** Prevents 400 errors and wasting API limits
- **Future-ready:** Structure prepared for adding other AI providers (e.g., Gemini) with different supported formats

### ⚙️ Bulk Actions
- **Generate for selected** - processing selected images in media library
- **Library button** - single "Generate ALT" button for each image

### 🔄 Automatic Updates
- **GitHub Releases integration** - automatic update downloads from `kacperbaranowski/AltGenius`
- **Public repo** - no token required
- **Safe updates** - settings preserved

## 📦 Installation

### Method 1: Via WordPress Dashboard
1. Download the latest version from [GitHub Releases](https://github.com/kacperbaranowski/AltGenius/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the downloaded ZIP file
4. Click **Install Now**
5. **Activate** the plugin

### Method 2: Manual Installation
1. Download the plugin from [GitHub](https://github.com/kacperbaranowski/AltGenius)
2. Extract the folder to `/wp-content/plugins/`
3. Activate the plugin in WordPress dashboard

### Method 3: Git (for developers)
```bash
cd wp-content/plugins/
git clone https://github.com/kacperbaranowski/AltGenius.git wp-alt-generator
```

## ⚙️ Configuration

### 1. Get OpenAI API Key
1. Go to [platform.openai.com](https://platform.openai.com/api-keys)
2. Log in or create an account
3. Create a new API key
4. Copy the key (keep it in a safe place!)

### 2. Plugin Configuration
1. In WordPress go to **AltGenius → Settings**
2. Paste the **API Key** in the appropriate field
3. Select **Model** (recommended: `gpt-4o-mini` for best price-to-quality ratio)
4. (Optional) Customize the **Prompt** to your needs
5. (Optional) Enable **Automatic generation on upload**
6. Click **Save Changes**

## 📖 Usage

### Statistics Dashboard
1. Go to **AltGenius → Statistics**
2. View:
   - **KPI Cards:** All images, With ALT, Without ALT, Coverage %
   - **Cron Status:** Whether active, when next run (every 5 minutes)
   - Info: Cron processes ~8,640 images/day

### Generating ALT for a Single Image
1. Go to **Media → Library**
2. Find an image without ALT
3. Click the **Generate ALT** button
4. Wait for generation (status will appear next to the button)

### Bulk ALT Generation
1. Go to **Media → Library**
2. Select images (checkbox next to thumbnails)
3. From **Bulk Actions** menu select **Generate ALT for selected**
4. Click **Apply**

### Automatic Generation (CRON)
Cron runs automatically every 5 minutes and:
- Scans media library for images without ALT
- Processes 30 images per run
- Logs all operations to `logs/alt-scan-log.txt`
- **Requires no intervention** - runs in background 24/7

**Cron Status:** Check in **AltGenius → Statistics**

## 🎨 Customization

### Changing the Prompt
Default prompt:
```
Describe this photo in one sentence in English for ALT. URL: {{image_url}}
```

You can customize it in **AltGenius → Settings → Prompt**. Use `{{image_url}}` as a placeholder.

Examples:
```
Create a short, descriptive alt text for this image: {{image_url}}
```
```
Generate WCAG 2.1 compliant alt text for: {{image_url}}
```

### Changing Cron Frequency (advanced)
Default: every 5 minutes. To change, edit in the plugin file (line ~537):
```php
$schedules['every_5_minutes'] = [
    'interval' => 300, // 300 seconds = 5 minutes
    'display'  => __('Every 5 minutes')
];
```

Example values:
- `60` - every minute (not recommended - rate limits!)
- `300` - every 5 minutes (default, recommended for Tier 1)
- `600` - every 10 minutes
- `1800` - every 30 minutes

**⚠️ Note:** After changing, you must deactivate and reactivate the plugin!

### Changing Processing Limit
Default: 30 images/batch (optimal for OpenAI Tier 1).
Edit in default settings (line ~78):
```php
'scan_limit' => 30
```

**⚠️ Note:**
- Tier 1 (10,000 RPD): max ~35 at 5-minute interval
- Tier 2+ (50,000 RPD): you can increase to 100-150

## 🗂️ File Structure

```
wp-alt-generator/
├── wp-alt-generator.php    # Main plugin file
├── assets/
│   ├── altgpt.js           # JS for media library
│   ├── stats.js            # JS for statistics dashboard
│   └── stats.css           # Styles for statistics dashboard
├── logs/
│   └── alt-scan-log.txt    # Log file (created automatically)
└── README.md
```

## 🔐 Security

- **API Key:** Stored securely in WordPress database
- **Nonce verification:** All AJAX actions secured
- **Capability checks:** Only administrators have access (`manage_options`)

## 💰 OpenAI API Costs

The plugin uses **Vision API** (image analysis), which affects costs:

### OpenAI Tier 1 (10,000 RPD)
- **Cost per image:** ~$0.001 - $0.003 (model: gpt-4o-mini)
- **Daily performance:** ~8,640 images (with default settings)
- **Daily cost:** ~$8.64 - $25.92
- **Monthly cost:** ~$259 - $777

### Examples (gpt-4o-mini):
- **100 images:** ~$0.10 - $0.30
- **1,000 images:** ~$1.00 - $3.00
- **10,000 images:** ~$10.00 - $30.00

💡 **Tip:** Use `gpt-4o-mini` for lowest costs while maintaining good quality!

Check current prices at [OpenAI Pricing](https://openai.com/api/pricing/).

## 🐛 Troubleshooting

### "Missing API key"
- Make sure the API key is correctly pasted in **AltGenius → Settings**
- Check for extra spaces

### "OpenAI error 401"
- API key is invalid or expired
- Generate a new key at platform.openai.com

### "OpenAI error 429" (Rate Limit)
- API request limit exceeded
- **Tier 1:** Reduce limit to 20-25 images or increase interval to 10 minutes
- **Solution:** Upgrade to Tier 2+ on OpenAI

### Cron not working
- **Check status:** **AltGenius → Statistics** → "Automatic Generation" section
- **Reset cron:** Deactivate and reactivate the plugin
- **WordPress Cron:** Check if not disabled (`DISABLE_WP_CRON`)
- **Logs:** Check `logs/alt-scan-log.txt` for errors

### Gutenberg Sync not working
- **Verification:** Edit a post in Gutenberg and change an image's ALT
- **Check logs:** Saved in `logs/alt-scan-log.txt`
- **Cache:** Clear WordPress and browser cache

### High API usage
- Reduce limit from 30 to 20 (line ~78)
- Increase interval from 5 to 10 minutes (line ~537)
- Monitor usage at [platform.openai.com/usage](https://platform.openai.com/usage)

## 🤝 Support and Bug Reports

- **Issues:** [GitHub Issues](https://github.com/kacperbaranowski/AltGenius/issues)
- **Author:** Kacper Baranowski
- **GitHub:** [@kacperbaranowski](https://github.com/kacperbaranowski)

## 📄 License

GPLv2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

**Was this plugin helpful? Leave a ⭐ on [GitHub](https://github.com/kacperbaranowski/AltGenius)!**
