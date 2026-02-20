# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-02-20

### Added
- AI Agent core with fluent, chainable API (`chat()`, `stream()`, `chatWithEvents()`)
- Multi-provider support: OpenAI, Anthropic, Gemini, DeepSeek, OpenRouter
- `#[AsAITool]` attribute for zero-boilerplate tool registration with auto-discovery
- Smart parameter inference from method signatures, FormRequest rules, and static analysis
- Multi-agent system with isolated tools, permissions, and conversations per agent
- Conversation memory with AI-powered summarization (session and database drivers)
- Security layer: prompt injection detection, XSS prevention, secret redaction, audit logging
- Drop-in chat widget (Web Component) with SSE streaming, Markdown, voice input, i18n, and RTL
- `BaseAgent` abstract class for custom agent definitions
- Middleware pipeline support for prompt preprocessing
- Smart return handling for Eloquent models, views, redirects, JSON, and API resources
- Rate limiting and budget control
- i18n support with 5 built-in languages: English, Arabic, French, Spanish, Chinese
- `FakeDriver` for testing
