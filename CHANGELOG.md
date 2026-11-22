# AI MCP by WEBMOSH: Changelog

## v1.0.1

- **Elementor MCP switch**: the AI can now turn Elementor MCP on or off. It's a site setting, not an Elementor experiment, so it was missing from the features list before.
- **WooCommerce features**: new tools to list and switch WooCommerce features, including MCP integration.

## v1.0.0 (first release)

### Install
Download the latest **ai-mcp-x.x.x.zip** from the Releases page, then in WordPress go to **Plugins > Add New > Upload Plugin**, pick the zip and activate. Don't use GitHub's green "Download ZIP" button, it names the folder wrong.

### Features
- **AI Agent**: chat with your site in wp-admin. The AI posts, edits pages and Elementor, manages orders, refunds, stock, products, coupons, customers, users, comments, plugins and settings. Optional "ask before making changes" mode.
- **MCP server** with its own menu tab: connect Claude, ChatGPT, Cursor or ClickUp. 50+ tools, live "connected" status in the MCP tab and the header.
- **Providers**: OpenAI, Anthropic (Claude), Google Gemini, OpenRouter and Others (any OpenAI-compatible API).
- **Chatbots, knowledge base, playground, content and image generator, AI forms, editor and WooCommerce assistant.**
- **AI features stay locked** until an AI provider is connected.
- **Activity log** on Home: every change made by MCP or the AI agent.
- **Security**: hashed access tokens, brute-force lockout, IP allow list, read-only switch, per-area permissions, prompt-injection guard, hard blocks on admin-level actions, "Disconnect everything" button.
- **WEBMOSH branding** and color theme.

### Requirements
WordPress 6.0+, PHP 7.4+. WooCommerce and Elementor are optional.
