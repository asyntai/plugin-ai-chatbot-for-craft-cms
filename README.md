# Asyntai - AI Chatbot

AI assistant / chatbot plugin for Craft CMS – Provides instant answers to your website visitors.

## Description

Asyntai adds an AI chatbot to your Craft CMS site. It provides instant answers to visitors according to your instructions/knowledge base.

## Requirements

- Craft CMS 3.7 or later (supports Craft 3, 4, and 5)
- PHP 7.2 or later

## Installation

### Method 1: Via Composer (Recommended)

1. Add the plugin as a path repository in your project's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "plugins/craft/asyntai"
    }
]
```

2. Require the plugin via Composer:

```bash
composer require asyntai/asyntai-chatbot
```

3. Install the plugin in Craft CP:
   - Go to **Settings** → **Plugins**
   - Find "Asyntai - AI Chatbot" and click **Install**

### Method 2: Manual Installation

1. Copy the `asyntai` folder to your Craft project's `vendor` directory (or use a custom modules path)
2. Run `composer dump-autoload` from your project root
3. Install the plugin via Craft CP: **Settings** → **Plugins** → **Install**

## Setup

1. After installation, go to **Settings** → **Plugins** → **Asyntai - AI Chatbot**
2. Click the **Get started** button to create a free Asyntai account or sign in
3. The popup will connect your site automatically
4. Once connected, the chatbot will appear on all pages of your site

## Configuration

After connecting your account, you can:

- **Manage chatbot settings**: Visit [Asyntai Dashboard](https://asyntai.com/dashboard)
- **Customize AI responses**: Go to [Setup page](https://asyntai.com/dashboard#setup)
- **View chat logs**: Available in your Asyntai panel

## External Services

This plugin connects to Asyntai.com to enable and operate the chatbot widget. The Asyntai service hosts the chatbot application and processes chats so your visitors can interact with the AI assistant on your site.

To complete the plugin setup, you need to create an account or log in to your existing Asyntai account (via asyntai.com). The external service is required to deliver the chatbot experience, process messages, and manage settings and logs.

See Asyntai's [Terms of Service](https://asyntai.com/terms-and-conditions/) and [Privacy Policy](https://asyntai.com/privacy-policy/).

## Support

For support, please visit [asyntai.com/contact](https://asyntai.com/contact)

## License

MIT License - see LICENSE file for details

## Changelog

### 1.0.0
* Initial release
* Support for Craft CMS 3.7+ through 5.x
* OAuth-style connection flow
* Automatic chatbot injection on all site pages

