=== ForgeHub AI Woo Writer ===
Contributors: wpforgehub
Tags: woocommerce, ai product writer, product descriptions, seo copywriting, byok
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

BYOK AI product writer for WooCommerce. Generate SEO descriptions, short descriptions, and feature bullets with preview and one-click apply.

== Description ==

**ForgeHub AI Woo Writer** brings Bring-Your-Own-Key (BYOK) multi-AI copywriting directly into the WooCommerce product edit screen. Generate conversion-focused SEO long descriptions, concise short descriptions, and benefit-driven feature bullets using your own API key—with zero monthly markup, zero intermediary servers, and an interactive preview before applying a single character.

Built with native WooCommerce CRUD APIs, full High-Performance Order Storage (HPOS) compatibility, and strict overwrite protection so your manually crafted copy is never lost by accident.

### Key Features (Free Version)
* **BYOK Multi-AI Support**: Connect your own **OpenAI (GPT-4o / GPT-4o Mini)**, **Anthropic Claude (3.5 Haiku / Sonnet)**, **Google Gemini (2.0 Flash / 1.5 Pro)**, **OpenRouter**, **DeepSeek**, or any **Custom OpenAI-Compatible** endpoint (Ollama, Groq, vLLM).
* **Custom Model Override**: Pick from curated preset models or type any new model ID manually.
* **Single-Product Editor Metabox**: Generate SEO Long Descriptions, Short Descriptions, and Key Feature Bullets directly from the WooCommerce product edit screen using product title, categories, attributes, SKU, price, and custom focus keywords.
* **Live Editable Preview & One-Click Apply**: Review and tweak generated copy before applying it to the Classic/TinyMCE editor or saving directly to the product.
* **Granular Field Selection & Overwrite Guard**: Choose which fields to generate and keep existing non-empty descriptions protected by default.
* **Zero Server Bloat & HPOS Ready**: All API calls go straight from your site to your chosen AI provider via native HTTP requests.

### Pro Features (Separate Commercial Plugin)
Managing a large catalog or multi-language store? The separate commercial Pro edition is available at [WPForgeHub](https://wpforgehub.com/product/ai-woo-writer/) and is not bundled in this free plugin:
* **Category & Filter-Based Bulk AJAX Batch Queue**: Filter products by category, search term, or missing long/short descriptions and generate copy safely one-by-one with live progress tracking and stop control.
* **Custom Brand Voice & Prompt Templates**: Save reusable brand guidelines and custom prompt instructions for consistent tone across every product.
* **Multi-Language Output**: Generate product copy in 12+ languages (English, Turkish, German, French, Spanish, Italian, Portuguese, Dutch, Polish, Arabic, Japanese, Korean).
* **Automatic SEO Meta & Excerpt Sync**: Automatically sync generated 140-155 character SEO meta descriptions to Yoast SEO, Rank Math, All in One SEO, and ForgeHub SEO Meta.

== Installation ==

1. Upload the `forgehub-ai-woo-writer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu.
3. Navigate to **Settings > AI Woo Writer**, choose your AI provider, enter your API key, and click **Test API Connection**.
4. Edit any WooCommerce product, use the **ForgeHub AI Woo Writer (BYOK)** metabox to preview copy, and apply in one click.

== Frequently Asked Questions ==

= Do I need a subscription or third-party account with you? =
No. ForgeHub AI Woo Writer uses a 100% Bring-Your-Own-Key (BYOK) architecture. You connect directly to OpenAI, Anthropic, Google Gemini, OpenRouter, DeepSeek, or your own OpenAI-compatible endpoint with no middleman markup.

= Will it overwrite my existing product descriptions? =
No. Overwrite protection is enabled by default. Existing non-empty descriptions are never replaced unless you explicitly check "Overwrite existing non-empty fields".

= Is this plugin compatible with WooCommerce HPOS? =
Yes. ForgeHub AI Woo Writer declares full `custom_order_tables` compatibility and saves product data strictly through WooCommerce CRUD methods.

= Where is my API key stored? =
Your API key is stored locally in your site's own `wp_options` table and is only transmitted directly to the AI provider endpoint you select.

== Screenshots ==

1. ForgeHub AI Woo Writer settings screen with BYOK multi-AI provider selection, custom model override, connection tester, and Pro bulk queue workspace.

== Changelog ==

= 1.0.0 =
* Initial stable release with BYOK multi-AI support (OpenAI, Anthropic Claude, Google Gemini, OpenRouter, DeepSeek, Custom), single-product metabox with editable preview and one-click apply, overwrite protection, HPOS compatibility, and Pro bulk AJAX batch queue, brand voice prompt templates, multi-language output, and SEO meta sync.
