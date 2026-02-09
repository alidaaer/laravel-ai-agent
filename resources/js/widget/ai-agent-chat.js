/**
 * AI Agent Chat Widget
 * 
 * A customizable Web Component for AI chat integration.
 * Now with Markdown support for tables, formatting, and more!
 * 
 * Usage:
 * <ai-agent-chat endpoint="/api/chat" theme="dark"></ai-agent-chat>
 * 
 * @author Laravel AI Agent
 * @version 1.1.0
 */

class AIAgentChat extends HTMLElement {
    static get observedAttributes() {
        return [
            'endpoint', 'theme', 'position', 'width', 'height',
            'rtl', 'welcome-message', 'placeholder', 'title', 'subtitle',
            'primary-color', 'open', 'button-icon', 'button-size'
        ];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.isOpen = false;
        this.messages = [];
        this.isTyping = false;
        this.conversationId = this.generateId();
    }

    // ================================
    // Lifecycle
    // ================================

    connectedCallback() {
        // Load messages FIRST (before render) so they appear immediately
        this.loadMessagesSync();

        this.render();
        this.setupEventListeners();

        if (this.hasAttribute('open')) {
            this.open();
        }

        // Welcome message only if no messages loaded
        const welcomeMsg = this.getAttribute('welcome-message');
        if (welcomeMsg && this.messages.length === 0) {
            this.addMessage(welcomeMsg, 'bot');
        }
    }

    /**
     * Synchronously load messages from localStorage (before render)
     */
    loadMessagesSync() {
        if (!this.hasAttribute('persist-messages')) return;

        try {
            const key = `ai_agent_chat_${(this.getAttribute('endpoint') || '/api/chat').replace(/[^a-z0-9]/gi, '_')}`;
            const saved = localStorage.getItem(key);
            if (saved) {
                const data = JSON.parse(saved);
                this.messages = data.messages || [];
                this.conversationId = data.conversationId || this.conversationId;
            }
        } catch (e) {
            console.warn('AI Agent: Failed to load messages', e);
        }
    }

    disconnectedCallback() {
        this.saveMessages();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.render();
        }
    }

    // ================================
    // Configuration
    // ================================

    get config() {
        return {
            endpoint: this.getAttribute('endpoint') || '/api/chat',
            theme: this.getAttribute('theme') || 'dark',
            position: this.getAttribute('position') || 'bottom-right',
            width: this.getAttribute('width') || '420px',
            height: this.getAttribute('height') || '550px',
            rtl: this.hasAttribute('rtl'),
            title: this.getAttribute('title') || 'AI Assistant',
            subtitle: this.getAttribute('subtitle') || '',
            placeholder: this.getAttribute('placeholder') || 'Type your message...',
            primaryColor: this.getAttribute('primary-color') || '#6366f1',
            buttonIcon: this.getAttribute('button-icon') || '💬',
            buttonSize: this.getAttribute('button-size') || '60px',
            persistMessages: this.hasAttribute('persist-messages'),
        };
    }

    // ================================
    // Message Persistence (localStorage)
    // ================================

    getStorageKey() {
        return `ai_agent_chat_${this.config.endpoint.replace(/[^a-z0-9]/gi, '_')}`;
    }

    loadMessages() {
        if (!this.config.persistMessages) return;

        try {
            const saved = localStorage.getItem(this.getStorageKey());
            if (saved) {
                const data = JSON.parse(saved);
                this.messages = data.messages || [];
                this.conversationId = data.conversationId || this.conversationId;
                this.updateMessagesUI();
            }
        } catch (e) {
            console.warn('AI Agent: Failed to load messages', e);
        }
    }

    saveMessages() {
        if (!this.config.persistMessages) return;

        try {
            const data = {
                messages: this.messages,
                conversationId: this.conversationId,
                savedAt: new Date().toISOString()
            };
            localStorage.setItem(this.getStorageKey(), JSON.stringify(data));
        } catch (e) {
            console.warn('AI Agent: Failed to save messages', e);
        }
    }

    clearMessages() {
        this.messages = [];
        this.conversationId = this.generateId();
        localStorage.removeItem(this.getStorageKey());
        this.updateMessagesUI();
    }

    updateMessagesUI() {
        const container = this.shadowRoot.querySelector('.widget-messages');
        if (container) {
            container.innerHTML = this.renderMessages();
            container.scrollTop = container.scrollHeight;
        }
    }

    // ================================
    // Markdown Parser (Built-in, no dependencies)
    // ================================

    parseMarkdown(text) {
        if (!text) return '';

        let html = text;

        // Escape HTML
        html = html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Code blocks ```
        html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            return `<pre class="md-code-block"><code>${code.trim()}</code></pre>`;
        });

        // Inline code `code`
        html = html.replace(/`([^`]+)`/g, '<code class="md-inline-code">$1</code>');

        // Tables
        html = this.parseTables(html);

        // Headers
        html = html.replace(/^### (.+)$/gm, '<h4 class="md-h4">$1</h4>');
        html = html.replace(/^## (.+)$/gm, '<h3 class="md-h3">$1</h3>');
        html = html.replace(/^# (.+)$/gm, '<h2 class="md-h2">$1</h2>');

        // Bold **text** or __text__
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');

        // Italic *text* or _text_
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

        // Strikethrough ~~text~~
        html = html.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        // Unordered lists
        html = html.replace(/^[\-\*] (.+)$/gm, '<li class="md-li">$1</li>');
        html = html.replace(/(<li class="md-li">.*<\/li>\n?)+/g, '<ul class="md-ul">$&</ul>');

        // Ordered lists
        html = html.replace(/^\d+\. (.+)$/gm, '<li class="md-li-num">$1</li>');
        html = html.replace(/(<li class="md-li-num">.*<\/li>\n?)+/g, '<ol class="md-ol">$&</ol>');

        // Blockquotes
        html = html.replace(/^> (.+)$/gm, '<blockquote class="md-quote">$1</blockquote>');

        // Links [text](url)
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="md-link">$1</a>');

        // Horizontal rule
        html = html.replace(/^---$/gm, '<hr class="md-hr">');

        // Clean up multiple <br>
        html = html.replace(/(<br>){2,}/g, '<br>');

        return html;
    }

    parseTables(text) {
        const tableRegex = /(?:^|\n)((?:\|[^\n]+\|\n?)+)/g;

        return text.replace(tableRegex, (match, tableText) => {
            const rows = tableText.trim().split('\n').filter(row => row.includes('|'));

            if (rows.length < 2) return match;

            // Check if second row is separator (|---|---|)
            const hasSeparator = /^\|[\s\-:|]+\|$/.test(rows[1]);

            let html = '<div class="md-table-wrapper"><table class="md-table">';

            rows.forEach((row, idx) => {
                // Skip separator row
                if (idx === 1 && hasSeparator) return;

                const cells = row.split('|').filter(c => c.trim() !== '');
                const isHeader = idx === 0 && hasSeparator;
                const tag = isHeader ? 'th' : 'td';

                html += `<tr>${cells.map(c => `<${tag}>${c.trim()}</${tag}>`).join('')}</tr>`;
            });

            html += '</table></div>';
            return html;
        });
    }

    // ================================
    // Icon Rendering
    // ================================

    /**
     * Render button icon - supports emoji, image URL, or inline SVG
     */
    renderButtonIcon(icon) {
        if (!icon) return '💬';

        // Check if it's a URL (image)
        if (icon.startsWith('http://') || icon.startsWith('https://') || icon.startsWith('/')) {
            return `<img src="${icon}" alt="Chat" style="width: 28px; height: 28px; object-fit: contain;">`;
        }

        // Check if it's inline SVG
        if (icon.trim().startsWith('<svg')) {
            return icon;
        }

        // Otherwise treat as emoji or text
        return icon;
    }

    // ================================
    // Rendering
    // ================================

    render() {
        const { theme, position, width, height, rtl, title, subtitle,
            placeholder, primaryColor, buttonIcon, buttonSize } = this.config;

        const positionStyles = this.getPositionStyles(position);
        const themeStyles = this.getThemeStyles(theme, primaryColor);

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    --primary: ${primaryColor};
                    --primary-dark: ${this.darkenColor(primaryColor, 20)};
                    --bg: ${themeStyles.bg};
                    --card: ${themeStyles.card};
                    --text: ${themeStyles.text};
                    --muted: ${themeStyles.muted};
                    --border: ${themeStyles.border};
                    --input-bg: ${themeStyles.inputBg};
                    --user-bubble: var(--primary);
                    --bot-bubble: ${themeStyles.botBubble};
                    --table-header: ${themeStyles.tableHeader};
                    --table-row: ${themeStyles.tableRow};
                    --table-row-alt: ${themeStyles.tableRowAlt};
                    
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    direction: ${rtl ? 'rtl' : 'ltr'};
                }

                * { box-sizing: border-box; margin: 0; padding: 0; }

                /* Button */
                .widget-button {
                    position: fixed;
                    ${positionStyles.button}
                    width: ${buttonSize};
                    height: ${buttonSize};
                    border-radius: 50%;
                    background: var(--primary);
                    border: none;
                    cursor: pointer;
                    font-size: 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                    transition: all 0.3s ease;
                    z-index: 9998;
                }

                .widget-button:hover {
                    transform: scale(1.1);
                    background: var(--primary-dark);
                }

                .widget-button.hidden { display: none; }

                /* Window */
                .widget-window {
                    position: fixed;
                    ${positionStyles.window}
                    width: ${width};
                    height: ${height};
                    max-width: 95vw;
                    max-height: 85vh;
                    background: var(--card);
                    border-radius: 16px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
                    display: none;
                    flex-direction: column;
                    overflow: hidden;
                    z-index: 9999;
                    animation: slideUp 0.3s ease;
                }

                .widget-window.open { display: flex; }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                /* Header */
                .widget-header {
                    padding: 16px;
                    background: var(--primary);
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .widget-header-info h3 {
                    font-size: 1rem;
                    font-weight: 600;
                }

                .widget-header-info p {
                    font-size: 0.75rem;
                    opacity: 0.8;
                    margin-top: 2px;
                }

                .widget-close {
                    background: rgba(255,255,255,0.2);
                    border: none;
                    color: white;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 1.2rem;
                    transition: background 0.2s;
                }

                .widget-close:hover { background: rgba(255,255,255,0.3); }

                /* Messages */
                .widget-messages {
                    flex: 1;
                    padding: 16px;
                    overflow-y: auto;
                    background: var(--bg);
                }

                .message {
                    max-width: 90%;
                    padding: 12px 16px;
                    border-radius: 16px;
                    margin-bottom: 12px;
                    line-height: 1.6;
                    font-size: 0.9rem;
                    word-wrap: break-word;
                }

                .message-user {
                    background: var(--user-bubble);
                    color: white;
                    margin-${rtl ? 'right' : 'left'}: auto;
                    border-bottom-${rtl ? 'left' : 'right'}-radius: 4px;
                }

                .message-bot {
                    background: var(--bot-bubble);
                    color: var(--text);
                    border-bottom-${rtl ? 'right' : 'left'}-radius: 4px;
                }

                .message-time {
                    font-size: 0.65rem;
                    opacity: 0.6;
                    margin-top: 6px;
                }

                /* ================================
                   Markdown Styles
                   ================================ */
                
                /* Tables */
                .md-table-wrapper {
                    overflow-x: auto;
                    margin: 10px 0;
                    border-radius: 8px;
                    border: 1px solid var(--border);
                }

                /* Table Scrollbar Styling */
                .md-table-wrapper::-webkit-scrollbar {
                    height: 6px;
                }

                .md-table-wrapper::-webkit-scrollbar-track {
                    background: var(--bg);
                    border-radius: 3px;
                }

                .md-table-wrapper::-webkit-scrollbar-thumb {
                    background: var(--primary);
                    border-radius: 3px;
                    opacity: 0.7;
                }

                .md-table-wrapper::-webkit-scrollbar-thumb:hover {
                    background: var(--primary-dark);
                }

                /* Firefox scrollbar */
                .md-table-wrapper {
                    scrollbar-width: thin;
                    scrollbar-color: var(--primary) var(--bg);
                }

                /* Code block scrollbar */
                .md-code-block::-webkit-scrollbar {
                    height: 6px;
                }

                .md-code-block::-webkit-scrollbar-track {
                    background: transparent;
                }

                .md-code-block::-webkit-scrollbar-thumb {
                    background: var(--border);
                    border-radius: 3px;
                }

                .md-code-block::-webkit-scrollbar-thumb:hover {
                    background: var(--primary);
                }

                .md-code-block {
                    scrollbar-width: thin;
                    scrollbar-color: var(--border) transparent;
                }

                .md-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 0.85rem;
                }

                .md-table th {
                    background: var(--table-header);
                    padding: 10px 12px;
                    text-align: ${rtl ? 'right' : 'left'};
                    font-weight: 600;
                    border-bottom: 2px solid var(--primary);
                }

                .md-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid var(--border);
                }

                .md-table tr:nth-child(even) {
                    background: var(--table-row-alt);
                }

                .md-table tr:hover {
                    background: var(--table-row);
                }

                /* Code */
                .md-code-block {
                    background: var(--bg);
                    padding: 12px;
                    border-radius: 8px;
                    overflow-x: auto;
                    margin: 10px 0;
                    font-family: 'Fira Code', 'Consolas', monospace;
                    font-size: 0.8rem;
                    border: 1px solid var(--border);
                }

                .md-inline-code {
                    background: var(--bg);
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-family: 'Fira Code', 'Consolas', monospace;
                    font-size: 0.85em;
                }

                /* Headers */
                .md-h2, .md-h3, .md-h4 {
                    margin: 12px 0 8px 0;
                    color: var(--primary);
                }

                .md-h2 { font-size: 1.2rem; }
                .md-h3 { font-size: 1.1rem; }
                .md-h4 { font-size: 1rem; }

                /* Lists */
                .md-ul, .md-ol {
                    margin: 8px 0;
                    padding-${rtl ? 'right' : 'left'}: 20px;
                }

                .md-li, .md-li-num {
                    margin: 4px 0;
                }

                /* Blockquote */
                .md-quote {
                    border-${rtl ? 'right' : 'left'}: 3px solid var(--primary);
                    padding: 8px 12px;
                    margin: 8px 0;
                    background: var(--bg);
                    font-style: italic;
                    border-radius: 0 8px 8px 0;
                }

                /* Link */
                .md-link {
                    color: var(--primary);
                    text-decoration: none;
                }

                .md-link:hover {
                    text-decoration: underline;
                }

                /* Horizontal rule */
                .md-hr {
                    border: none;
                    border-top: 1px solid var(--border);
                    margin: 12px 0;
                }

                /* Typing Indicator */
                .typing-indicator {
                    display: none;
                    padding: 10px 14px;
                    background: var(--bot-bubble);
                    border-radius: 16px;
                    width: fit-content;
                }

                .typing-indicator.show { display: flex; gap: 4px; }

                .typing-dot {
                    width: 8px;
                    height: 8px;
                    background: var(--muted);
                    border-radius: 50%;
                    animation: typingBounce 1.4s infinite ease-in-out;
                }

                .typing-dot:nth-child(1) { animation-delay: 0s; }
                .typing-dot:nth-child(2) { animation-delay: 0.2s; }
                .typing-dot:nth-child(3) { animation-delay: 0.4s; }

                @keyframes typingBounce {
                    0%, 60%, 100% { transform: translateY(0); }
                    30% { transform: translateY(-6px); }
                }

                /* Input */
                .widget-input-area {
                    padding: 12px;
                    background: var(--card);
                    border-top: 1px solid var(--border);
                    display: flex;
                    gap: 10px;
                }

                .widget-input {
                    flex: 1;
                    padding: 12px 16px;
                    border: 1px solid var(--border);
                    border-radius: 24px;
                    background: var(--input-bg);
                    color: var(--text);
                    font-size: 0.9rem;
                    outline: none;
                    transition: border-color 0.2s;
                }

                .widget-input:focus { border-color: var(--primary); }

                .widget-input::placeholder { color: var(--muted); }

                .widget-send {
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    background: var(--primary);
                    border: none;
                    color: white;
                    cursor: pointer;
                    font-size: 1.1rem;
                    transition: all 0.2s;
                }

                .widget-send:hover { background: var(--primary-dark); }
                .widget-send:disabled { opacity: 0.5; cursor: not-allowed; }

                /* Scrollbar */
                .widget-messages::-webkit-scrollbar { width: 6px; }
                .widget-messages::-webkit-scrollbar-track { background: transparent; }
                .widget-messages::-webkit-scrollbar-thumb { 
                    background: var(--border); 
                    border-radius: 3px; 
                }

                /* Mobile */
                @media (max-width: 480px) {
                    .widget-window {
                        width: 100%;
                        height: 100%;
                        max-height: 100%;
                        border-radius: 0;
                        top: 0 !important;
                        left: 0 !important;
                        right: 0 !important;
                        bottom: 0 !important;
                    }
                }
            </style>

            <button class="widget-button" part="button">
                ${this.renderButtonIcon(buttonIcon)}
            </button>

            <div class="widget-window" part="window">
                <div class="widget-header" part="header">
                    <div class="widget-header-info">
                        <h3>${title}</h3>
                        ${subtitle ? `<p>${subtitle}</p>` : ''}
                    </div>
                    <button class="widget-close" part="close-button">×</button>
                </div>

                <div class="widget-messages" part="messages">
                    ${this.renderMessages()}
                    <div class="typing-indicator" part="typing">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>

                <div class="widget-input-area" part="input-area">
                    <input 
                        type="text" 
                        class="widget-input" 
                        placeholder="${placeholder}"
                        part="input"
                    >
                    <button class="widget-send" part="send-button">
                        ${rtl ? '←' : '→'}
                    </button>
                </div>
            </div>
        `;
    }

    renderMessages() {
        return this.messages.map(msg => {
            const content = msg.role === 'bot' ? this.parseMarkdown(msg.content) : this.escapeHtml(msg.content);
            return `
                <div class="message message-${msg.role}">
                    <div class="message-content">${content}</div>
                    <div class="message-time">${msg.time}</div>
                </div>
            `;
        }).join('');
    }

    escapeHtml(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ================================
    // Theme & Position
    // ================================

    getThemeStyles(theme, primary) {
        const themes = {
            dark: {
                bg: '#0f172a',
                card: '#1e293b',
                text: '#f1f5f9',
                muted: '#64748b',
                border: '#334155',
                inputBg: '#0f172a',
                botBubble: '#334155',
                tableHeader: '#1e293b',
                tableRow: 'rgba(99, 102, 241, 0.1)',
                tableRowAlt: 'rgba(255, 255, 255, 0.02)',
            },
            light: {
                bg: '#f8fafc',
                card: '#ffffff',
                text: '#1e293b',
                muted: '#64748b',
                border: '#e2e8f0',
                inputBg: '#f1f5f9',
                botBubble: '#e2e8f0',
                tableHeader: '#f1f5f9',
                tableRow: 'rgba(99, 102, 241, 0.1)',
                tableRowAlt: 'rgba(0, 0, 0, 0.02)',
            },
        };
        return themes[theme] || themes.dark;
    }

    getPositionStyles(position) {
        const positions = {
            'bottom-right': {
                button: 'bottom: 20px; right: 20px;',
                window: 'bottom: 100px; right: 20px;',
            },
            'bottom-left': {
                button: 'bottom: 20px; left: 20px;',
                window: 'bottom: 100px; left: 20px;',
            },
            'top-right': {
                button: 'top: 20px; right: 20px;',
                window: 'top: 100px; right: 20px;',
            },
            'top-left': {
                button: 'top: 20px; left: 20px;',
                window: 'top: 100px; left: 20px;',
            },
        };
        return positions[position] || positions['bottom-right'];
    }

    darkenColor(hex, percent) {
        // Support both RGB (#RRGGBB) and RGBA (#RRGGBBAA)
        let color = hex.replace('#', '');
        let alpha = '';

        // If RGBA (8 chars), extract alpha and work with RGB
        if (color.length === 8) {
            alpha = color.slice(6, 8);
            color = color.slice(0, 6);
        }

        const num = parseInt(color, 16);
        const amt = Math.round(2.55 * percent);
        const R = Math.max((num >> 16) - amt, 0);
        const G = Math.max((num >> 8 & 0x00FF) - amt, 0);
        const B = Math.max((num & 0x0000FF) - amt, 0);

        const result = `#${(1 << 24 | R << 16 | G << 8 | B).toString(16).slice(1)}`;
        return alpha ? result + alpha : result;
    }

    // ================================
    // Event Listeners
    // ================================

    setupEventListeners() {
        const button = this.shadowRoot.querySelector('.widget-button');
        const closeBtn = this.shadowRoot.querySelector('.widget-close');
        const input = this.shadowRoot.querySelector('.widget-input');
        const sendBtn = this.shadowRoot.querySelector('.widget-send');

        button.addEventListener('click', () => this.toggle());
        closeBtn.addEventListener('click', () => this.close());
        sendBtn.addEventListener('click', () => this.sendMessage());

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
    }

    // ================================
    // Methods
    // ================================

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        const window = this.shadowRoot.querySelector('.widget-window');
        const button = this.shadowRoot.querySelector('.widget-button');
        window.classList.add('open');
        button.classList.add('hidden');
        this.shadowRoot.querySelector('.widget-input').focus();

        // Scroll to bottom to show latest messages
        const messages = this.shadowRoot.querySelector('.widget-messages');
        if (messages) {
            setTimeout(() => {
                messages.scrollTop = messages.scrollHeight;
            }, 50);
        }

        this.dispatchEvent(new CustomEvent('open'));
    }

    close() {
        this.isOpen = false;
        const window = this.shadowRoot.querySelector('.widget-window');
        const button = this.shadowRoot.querySelector('.widget-button');
        window.classList.remove('open');
        button.classList.remove('hidden');
        this.dispatchEvent(new CustomEvent('close'));
    }

    async sendMessage() {
        const input = this.shadowRoot.querySelector('.widget-input');
        const message = input.value.trim();

        if (!message) return;

        input.value = '';
        this.addMessage(message, 'user');
        this.showTyping(true);

        try {
            const response = await this.fetchResponse(message);
            this.showTyping(false);
            this.addMessage(response, 'bot');
        } catch (error) {
            this.showTyping(false);
            this.addMessage('Error: ' + error.message, 'bot');
            this.dispatchEvent(new CustomEvent('error', { detail: error }));
        }
    }

    async fetchResponse(message) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(this.config.endpoint, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({
                message,
                conversation_id: this.conversationId,
            }),
        });

        if (!response.ok) {
            // Try to get error message from response body
            let errorMessage = `HTTP ${response.status}`;
            try {
                const errorData = await response.json();
                errorMessage = errorData.error || errorData.message || errorMessage;
            } catch (e) {
                // Keep default error message
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();
        return data.response || data.message || data.content || JSON.stringify(data);
    }

    addMessage(content, role) {
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        this.messages.push({ content, role, time });
        this.updateMessages();
        this.saveMessages();

        this.dispatchEvent(new CustomEvent(role === 'user' ? 'message-sent' : 'message-received', {
            detail: { content, role, time }
        }));
    }

    updateMessages() {
        const container = this.shadowRoot.querySelector('.widget-messages');
        const typingIndicator = container.querySelector('.typing-indicator');

        // Remove old messages
        container.querySelectorAll('.message').forEach(el => el.remove());

        // Add messages HTML before typing indicator
        typingIndicator.insertAdjacentHTML('beforebegin', this.renderMessages());

        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    showTyping(show) {
        this.isTyping = show;
        const indicator = this.shadowRoot.querySelector('.typing-indicator');
        indicator.classList.toggle('show', show);

        if (show) {
            const container = this.shadowRoot.querySelector('.widget-messages');
            container.scrollTop = container.scrollHeight;
        }
    }

    // ================================
    // Storage
    // ================================

    getStorageKey() {
        // Use endpoint as stable key (not conversationId which changes on refresh)
        return `ai_agent_chat_${this.config.endpoint.replace(/[^a-z0-9]/gi, '_')}`;
    }

    saveMessages() {
        if (!this.config.persistMessages) return;

        try {
            const data = {
                messages: this.messages,
                conversationId: this.conversationId,
                savedAt: new Date().toISOString()
            };
            localStorage.setItem(this.getStorageKey(), JSON.stringify(data));
        } catch (e) {
            console.warn('AI Agent: Failed to save messages', e);
        }
    }

    loadMessages() {
        if (!this.config.persistMessages) return;

        try {
            const stored = localStorage.getItem(this.getStorageKey());
            if (stored) {
                const data = JSON.parse(stored);
                this.messages = data.messages || [];
                // Restore the same conversationId so server-side memory matches
                this.conversationId = data.conversationId || this.conversationId;
            }
        } catch (e) {
            console.warn('AI Agent: Failed to load messages', e);
        }
    }

    clearMessages() {
        this.messages = [];
        this.conversationId = this.generateId();
        localStorage.removeItem(this.getStorageKey());
        this.updateMessages();
    }

    // ================================
    // Utilities
    // ================================

    generateId() {
        return 'chat_' + Math.random().toString(36).substr(2, 9);
    }

    // Public API
    configure(options) {
        Object.entries(options).forEach(([key, value]) => {
            if (typeof value === 'function') {
                this.addEventListener(key.replace('on', '').toLowerCase(), value);
            } else {
                this.setAttribute(key, value);
            }
        });
    }
}

// Register the Web Component
customElements.define('ai-agent-chat', AIAgentChat);

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AIAgentChat;
}
