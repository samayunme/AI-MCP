=== AI MCP ===
Contributors: webmosh
Tags: ai, chatbot, openai, claude, gemini, mcp, content generator, embeddings
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later

AI for WordPress: chatbots, content and image generation, AI forms, knowledge base, editor assistant, usage limits and an MCP server.

== Description ==

* **Chatbots**: unlimited bots, each with its own model, instructions, colors, start message and context size. Embed with [ai_mcp_chatbot id="default"] or show one as a site-wide popup.
* **Knowledge base (embeddings)**: sync every post, page and product, or add custom facts. Chatbots answer from your own content. Vectors live in your database, no Pinecone needed.
* **Providers**: OpenAI, Anthropic (Claude), Google Gemini and OpenRouter (hundreds of models). Keys can also live in wp-config.php: MOSHAI_OPENAI_KEY, MOSHAI_ANTHROPIC_KEY, MOSHAI_GOOGLE_KEY, MOSHAI_OPENROUTER_KEY.
* **Playground**: test any model, system prompt, temperature and max tokens.
* **Content Generator**: full SEO articles (title, excerpt, HTML body) saved as drafts.
* **Image Generator**: gpt-image-1 / dall-e-3 straight into the Media Library, alt text filled in.
* **AI Forms**: build forms with fields + a prompt template, embed with [ai_mcp_form id="..."].
* **Editor + WooCommerce assistant**: titles, excerpts, SEO meta descriptions, tags, full content, product descriptions and short descriptions, plus custom requests. Works in Gutenberg and the classic editor.
* **Meta descriptions**: a field on every post and page, printed in the head (skipped when an SEO plugin is active).
* **Discussions**: every chatbot conversation saved and browsable.
* **Usage and limits**: tokens by day, model and feature. Daily limits per visitor and per user, a burst limit, and optional OpenAI moderation.
* **MCP server**: let Claude, ChatGPT, Cursor or ClickUp manage the site. 21 tools including posts, meta (backslash-safe, with search and replace for Elementor data), media alt text, plugins and AI generation.

== Installation ==

1. Upload the zip in Plugins > Add New > Upload Plugin and activate.
2. AI MCP > Settings: paste an API key and choose the default model.
3. AI MCP > Chatbots: tune the Default bot, then add [ai_mcp_chatbot id="default"] to a page or pick it as the site popup.

== Developer hooks ==

Filters: moshai_models, moshai_query_args, moshai_query_result, moshai_show_popup, moshai_editor_post_types, moshai_mcp_tools, moshai_mcp_call.
JS: window.MoshAI.boot() re-scans the page for chatbots and forms.

== Changelog ==

= 1.4.0 =
* WEBMOSH branding: logo in the header, admin menu and toolbar; black and logo-blue theme.
* Live connection status: "API connected" and "MCP connected · client" pills in the header, client list with last seen and change counts on the MCP tab, green dot in the toolbar.
* Audit log: every change made by MCP apps or the AI Agent, and every blocked attempt, on Home (live) and in Activity with filters.
* AI features (Agent, Chatbots, Studio, Forms, Knowledge, editor box, front-end chatbots and forms) stay locked until an AI is connected, enforced on the server too.
* New "Others" provider for any OpenAI-compatible API, with presets for DeepSeek, Groq, Mistral, xAI Grok, Together, Perplexity, Qwen, Ollama and LM Studio.
* Gemini alone now unlocks images and the knowledge base.
* Full site control for AI: Elementor features and experiments (Atomic editor etc.), Elementor Site Settings with safe merge, any plugin setting (with nested path edits), plugin installs from wordpress.org, Additional CSS, cache flush.
* Security: hashed MCP tokens shown once, token in URL off by default, brute-force lockout, IP allow list, cross-site browser requests refused, read-only switch, per-area permissions, hard blocks on admin creation and promotion, admin account edits, credentials, site URL / admin email, and on switching off AI MCP; prompt-injection guard for the agent; "Disconnect everything" button.

= 1.3.0 =
* New AI Agent tab: chat with your site in wp-admin. The connected AI (OpenAI, Claude, Gemini, OpenRouter) uses real tools to post, edit pages and Elementor text, manage orders, refunds, stock, products, coupons, customers, users, comments, plugins and settings.
* "Ask before making changes": reading runs instantly, every change waits for Approve & run.
* New tools for MCP and the agent: WooCommerce orders, order status and notes, refunds, products, stock, coupons, customers, sales report; comments, terms, featured images, users, plugins, options, and wp_rest_request for any WordPress or WooCommerce REST route.
* "AI MCP Agent" widget on the WordPress dashboard.

= 1.2.0 =
* Renamed to AI MCP. New shortcodes [ai_mcp_chatbot] and [ai_mcp_form]; the old [mosh_chatbot] and [mosh_form] keep working.
* MCP endpoint is now /wp-json/ai-mcp/v1/mcp.
* MCP has its own tab in the main menu (AI MCP > MCP) instead of living inside Settings.

= 1.1.0 =
* New one-page admin: Home, Chatbots, Studio, AI Forms, Knowledge, Activity, Settings. Everything saves instantly, no page reloads.
* One-click "Connect" on first run, key testing with live model lists, masked keys.
* Chatbot and form editors with live preview; the floating bubble is a single toggle.
* Knowledge base: packed vectors (much smaller, faster search), cached question embeddings, auto re-index on post update, source links under answers.
* Long chats are trimmed to save tokens; logs and conversations auto-delete after a retention period.
* Fixed: numbers in chatbot and form answers showed up as "undefined".

= 1.0.0 =
* First release.
