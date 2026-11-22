# AI MCP by WEBMOSH

WordPress plugin that connects AI to your website two ways:

- **MCP server**: Claude, ChatGPT, Cursor, ClickUp and other AI apps connect with a token and work on the site with their own AI. No API key needed on the site.
- **Built-in AI** (OpenAI, Claude, Gemini, OpenRouter, or any OpenAI-compatible API under "Others"): AI Agent chat in wp-admin, chatbots, AI forms, Studio (playground, article writer, images), knowledge base and an editor assistant.

## Features

- **AI Agent**: chat with your site in the dashboard. It posts, edits pages and Elementor text, manages WooCommerce orders, refunds, stock, products and coupons, users, comments, plugins and settings. "Ask before making changes" is on by default.
- **Full site control**: Elementor features and experiments (Atomic editor and more), Elementor Site Settings (safe merge), any plugin setting (with nested path edits), plugin installs from wordpress.org, Additional CSS, cache flush, and a bridge to every WordPress / WooCommerce REST route.
- **Live connection status**: "API connected" and "MCP connected" in the header, MCP client list with last seen and change counts.
- **Audit log**: every change made by MCP apps or the AI Agent, and every blocked attempt, on Home (live) and in Activity.
- **Locks**: every AI feature stays locked until an AI is connected, enforced on the server.
- **Chatbots and AI Forms**: shortcodes `[ai_mcp_chatbot id="default"]` and `[ai_mcp_form id="..."]`, optional site-wide chat bubble.
- **Knowledge base**: embeddings stored in your own database (OpenAI or Gemini), auto re-sync on post updates.

## Security

- MCP tokens stored as a keyed hash and shown only once.
- Token in the URL (`?token=`) off by default.
- Brute-force lockout (10 wrong tokens in 15 minutes blocks the IP for an hour), optional IP allow list.
- Cross-site browser requests refused (phishing pages, DNS rebinding).
- Read-only switch and per-area permissions (content, media, shop, users, site, REST).
- Always blocked: creating or promoting administrators, editing admin accounts, application passwords, site URL / admin email changes, reading secrets, switching off or deleting AI MCP.
- Prompt-injection guard: the agent treats website content as data and ignores instructions hidden in posts, comments or orders.
- "Disconnect everything" button rotates the token and cuts every client off.

## Install

1. Download **ai-mcp-1.0.0.zip** from the [Releases](../../releases) page. Don't use the green Code > Download ZIP button, it names the folder wrong.
2. WordPress > Plugins > Add New > Upload Plugin, then activate.
3. Open **AI MCP** in the admin menu: connect an AI under Settings, or switch on MCP and create a token.

MCP endpoint: `https://your-site.com/wp-json/ai-mcp/v1/mcp` with header `Authorization: Bearer <token>`.

Requires WordPress 6.0+ and PHP 7.4+. WooCommerce and Elementor tools switch on automatically when those plugins are active.

## License

GPLv2 or later. Made by [WEBMOSH](https://webmosh.com).
