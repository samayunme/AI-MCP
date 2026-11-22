=== AI MCP ===
Contributors: webmosh
Tags: ai, chatbot, openai, claude, gemini, mcp, content generator, embeddings
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

AI for WordPress: chatbots, content and image generation, AI forms, knowledge base, editor assistant, usage limits and an MCP server.

== Description ==

* **Chatbots**: unlimited bots, each with its own model, instructions, colors, start message and context size. Embed with [ai_mcp_chatbot id="default"] or show one as a site-wide popup.
* **Knowledge base (embeddings)**: sync every post, page and product, or add custom facts. Chatbots answer from your own content. Vectors live in your database, no Pinecone needed.
* **Providers**: OpenAI, Anthropic (Claude), Google Gemini, OpenRouter (hundreds of models) and Others (any OpenAI-compatible API: DeepSeek, Groq, Mistral, xAI Grok, Together, Perplexity, Qwen, Ollama, LM Studio). Keys can also live in wp-config.php: MOSHAI_OPENAI_KEY, MOSHAI_ANTHROPIC_KEY, MOSHAI_GOOGLE_KEY, MOSHAI_OPENROUTER_KEY.
* **Playground**: test any model, system prompt, temperature and max tokens.
* **Content Generator**: full SEO articles (title, excerpt, HTML body) saved as drafts.
* **Image Generator**: gpt-image-1 / dall-e-3 straight into the Media Library, alt text filled in.
* **AI Forms**: build forms with fields + a prompt template, embed with [ai_mcp_form id="..."].
* **Editor + WooCommerce assistant**: titles, excerpts, SEO meta descriptions, tags, full content, product descriptions and short descriptions, plus custom requests. Works in Gutenberg and the classic editor.
* **Meta descriptions**: a field on every post and page, printed in the head (skipped when an SEO plugin is active).
* **Discussions**: every chatbot conversation saved and browsable.
* **Usage and limits**: tokens by day, model and feature. Daily limits per visitor and per user, a burst limit, and optional OpenAI moderation.
* **MCP server**: let Claude, ChatGPT, Cursor or ClickUp manage the site. 50+ tools covering posts, pages, Elementor, WooCommerce orders, stock and products, users, comments, plugins, settings and AI generation.
* **AI Agent**: chat with your site in wp-admin and let the connected AI do the work, with an optional "ask before making changes" mode.
* **Audit log and security**: every AI change logged, hashed tokens, brute-force lockout, IP allow list, read-only switch and hard blocks on admin-level actions.

== Installation ==

1. Upload the zip in Plugins > Add New > Upload Plugin and activate.
2. AI MCP > Settings: paste an API key and choose the default model.
3. AI MCP > Chatbots: tune the Default bot, then add [ai_mcp_chatbot id="default"] to a page or pick it as the site popup.

== Developer hooks ==

Filters: moshai_models, moshai_query_args, moshai_query_result, moshai_show_popup, moshai_editor_post_types, moshai_mcp_tools, moshai_mcp_call.
JS: window.MoshAI.boot() re-scans the page for chatbots and forms.

== Changelog ==

= 1.0.0 =
* First public release.
* Chatbots, knowledge base, playground, content and image generator, AI forms, editor and WooCommerce assistant, meta descriptions, discussions, usage limits.
* AI Agent tab and dashboard widget: chat with your site and let the AI post, edit pages and Elementor, manage orders, refunds, stock, products, coupons, customers, users, comments, plugins and settings.
* MCP server with its own menu tab, live connection status and 50+ tools.
* Providers: OpenAI, Anthropic, Google Gemini, OpenRouter and Others (any OpenAI-compatible API).
* AI features stay locked until an AI is connected.
* Audit log of every change made by MCP apps or the AI Agent.
* Security: hashed MCP tokens, brute-force lockout, IP allow list, read-only switch, per-area permissions, prompt-injection guard, hard blocks on admin creation, credentials and site URL changes, "Disconnect everything" button.
* WEBMOSH branding and theme.
