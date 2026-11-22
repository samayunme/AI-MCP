=== AI MCP ===
Contributors: samayun1
Tags: ai, chatbot, mcp, ai agent, content generator
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbots, content and image generation, AI forms, a knowledge base, an AI agent for wp-admin and an MCP server for your site.

== Description ==

AI MCP brings AI into WordPress and lets AI apps work on your site safely.

* **Chatbots**: unlimited bots, each with its own model, instructions, colors, start message and context size. Embed with [ai_mcp_chatbot id="default"] or show one as a site-wide popup.
* **Knowledge base (embeddings)**: sync your posts, pages and products, or add custom facts. Chatbots answer from your own content. Vectors are stored in your own database.
* **Providers**: OpenAI, Anthropic (Claude), Google Gemini, OpenRouter, and Others: any OpenAI-compatible API, such as DeepSeek, Groq, Mistral, xAI, Together, Perplexity, Qwen or a local Ollama / LM Studio server. Keys can also be set in wp-config.php: MOSHAI_OPENAI_KEY, MOSHAI_ANTHROPIC_KEY, MOSHAI_GOOGLE_KEY, MOSHAI_OPENROUTER_KEY.
* **Playground**: test any model, system prompt, temperature and max tokens.
* **Content generator**: full articles (title, excerpt, HTML body) saved as drafts.
* **Image generator**: images straight into the Media Library, with alt text filled in.
* **AI forms**: build forms with fields and a prompt template, embed with [ai_mcp_form id="..."].
* **Editor and WooCommerce assistant**: titles, excerpts, meta descriptions, tags, full content and product descriptions, plus custom requests. Works in the block editor and the classic editor.
* **Meta descriptions**: a field on every post and page, printed in the head (skipped when an SEO plugin is active).
* **Discussions**: chatbot conversations saved and browsable in wp-admin.
* **Usage and limits**: tokens by day, model and feature. Daily limits per visitor and per user, a burst limit, and optional OpenAI moderation.
* **MCP server**: connect Claude, ChatGPT, Cursor, ClickUp or any MCP client. 50+ tools for posts, pages, Elementor, WooCommerce orders, stock and products, users, comments, plugins and settings.
* **AI Agent**: chat with your site in wp-admin and let the connected AI do the work, with an optional "ask before making changes" mode.
* **Audit log and security**: every AI change is logged. Hashed access tokens, brute-force lockout, IP allow list, read-only switch, per-area permissions, and hard blocks on admin-level actions (creating administrators, changing the site URL or admin email, creating login credentials, switching AI MCP off).

Nothing calls an AI service until you add an API key or connect an MCP client yourself.

= Developer hooks =

Filters: moshai_models, moshai_query_args, moshai_query_result, moshai_show_popup, moshai_editor_post_types, moshai_mcp_tools, moshai_mcp_call.
JavaScript: window.MoshAI.boot() re-scans the page for chatbots and forms.

== External services ==

AI MCP connects to the third-party services below. It only contacts a service after you add an API key (or endpoint) for it, and only the provider you choose for a feature is used.

= OpenAI =
Used for chat, content, images, embeddings (knowledge base) and optional moderation when OpenAI is selected or the moderation option is on. Sent: the prompt or chat messages, the system instructions, and for the knowledge base the text of the content being indexed. Sent each time a feature runs.
Terms: https://openai.com/policies/terms-of-use/ Privacy: https://openai.com/policies/privacy-policy/

= Anthropic (Claude) =
Used for chat, content and the AI Agent when Anthropic is selected. Sent: the prompt or chat messages and the system instructions, each time a feature runs.
Terms: https://www.anthropic.com/legal/consumer-terms Privacy: https://www.anthropic.com/legal/privacy

= Google Gemini =
Used for chat, content, images and embeddings when Google is selected. Sent: the prompt or chat messages, the system instructions, and content text for the knowledge base, each time a feature runs.
Terms: https://ai.google.dev/gemini-api/terms Privacy: https://policies.google.com/privacy

= OpenRouter =
Used for chat, content and the AI Agent when OpenRouter is selected. Sent: the prompt or chat messages and the system instructions, each time a feature runs.
Terms: https://openrouter.ai/terms Privacy: https://openrouter.ai/privacy

= Other OpenAI-compatible APIs =
When you choose "Others", requests go to the endpoint URL you enter (for example DeepSeek, Groq, Mistral, xAI, Together, Perplexity, Alibaba Qwen, or your own Ollama or LM Studio server). The same data as above is sent. Their terms and privacy policies are those of the provider you choose.

= Model lists =
When you open Settings with a key saved, AI MCP asks that provider for its list of available models. Only your API key is sent.

= MCP clients =
The MCP server does not contact anything. Apps you connect (such as Claude, ChatGPT, Cursor or ClickUp) send requests to your site with an access token you create, and receive the data their tools ask for. Each app's own terms and privacy policy apply.

== Installation ==

1. Install from Plugins > Add New, or upload the zip in Plugins > Add New > Upload Plugin, and activate.
2. AI MCP > Settings: connect an AI by pasting an API key, and choose the default model.
3. AI MCP > Chatbots: tune the Default bot, then add [ai_mcp_chatbot id="default"] to a page or pick it as the site popup.
4. Optional: AI MCP > MCP: turn on the MCP server, create an access token and connect your AI app.

== Frequently Asked Questions ==

= Do I need an API key? =
For chatbots, the playground, content, images, forms, the knowledge base and the AI Agent, yes: connect OpenAI, Claude, Gemini, OpenRouter or another OpenAI-compatible API. The MCP server works without a key, because the connected app brings its own AI.

= Where is my data stored? =
Settings, chatbot conversations, usage logs, the activity log and knowledge base vectors are stored in your WordPress database. Text is only sent to the AI provider you configure, when a feature runs.

= Can an AI app take over my site? =
No. MCP is off until you turn it on and create a token. You choose which areas AI may change, or make it read-only. Creating or editing administrators, creating login credentials, changing the site URL or admin email and switching AI MCP off are always blocked, and every change is logged.

= Does it remove its data when deleted? =
Only if you turn on "Delete all AI MCP data when the plugin is deleted" in Settings.

== Changelog ==

= 1.0.3 =
* Prepared for WordPress.org: updates now come through WordPress.org only.
* All admin output is escaped late with wp_kses().
* Cleaner IP allow list checks.

= 1.0.1 =
* AI can now turn Elementor MCP on or off (it is a site setting, not an experiment, so it was missing from the Elementor features list).
* New tools: wc_list_features and wc_set_feature for WooCommerce features such as MCP integration.

= 1.0.0 =
* First public release.
