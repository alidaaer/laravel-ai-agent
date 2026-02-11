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
            'endpoint', 'history-endpoint', 'stream', 'theme', 'position', 'width', 'height',
            'rtl', 'lang', 'welcome-message', 'placeholder', 'title', 'subtitle',
            'primary-color', 'open', 'button-icon', 'button-size',
            'conversations-label', 'new-chat-label', 'no-conversations-label'
        ];
    }

    // ================================
    // Translations
    // ================================

    static translations = {
        en: {
            title: 'AI Assistant',
            placeholder: 'Type a message...',
            conversations: 'Conversations',
            newChat: '+ New Chat',
            delete: 'Delete',
            loadFailed: 'Failed to load',
            noConversations: 'No conversations yet',
            errorLoading: 'Error loading',
            justNow: 'Just now',
            minutesAgo: '{n}m ago',
            hoursAgo: '{n}h ago',
            daysAgo: '{n}d ago',
            error: 'Error: {msg}',
            stopped: 'Stopped',
            thinking: 'Thinking...',
            executing: 'Executing: {name}...',
            executed: '{name} ✓',
            writing: 'Writing response...',
        },
        ar: {
            title: 'مساعد ذكي',
            placeholder: 'اكتب رسالة...',
            conversations: 'المحادثات',
            newChat: '+ محادثة جديدة',
            delete: 'حذف',
            loadFailed: 'فشل تحميل المحادثات',
            noConversations: 'لا توجد محادثات سابقة',
            errorLoading: 'خطأ في التحميل',
            justNow: 'الآن',
            minutesAgo: 'منذ {n} دقيقة',
            hoursAgo: 'منذ {n} ساعة',
            daysAgo: 'منذ {n} يوم',
            error: 'خطأ: {msg}',
            stopped: 'تم الإيقاف',
            thinking: 'يفكر...',
            executing: 'ينفذ: {name}...',
            executed: '{name} ✓',
            writing: 'يكتب الرد...',
        },
        fr: {
            title: 'Assistant IA',
            placeholder: 'Saisissez un message...',
            conversations: 'Conversations',
            newChat: '+ Nouvelle conversation',
            delete: 'Supprimer',
            loadFailed: 'Échec du chargement',
            noConversations: 'Aucune conversation',
            errorLoading: 'Erreur de chargement',
            justNow: 'À l\'instant',
            minutesAgo: 'Il y a {n} min',
            hoursAgo: 'Il y a {n}h',
            daysAgo: 'Il y a {n}j',
            error: 'Erreur : {msg}',
            stopped: 'Arrêté',
            thinking: 'Réflexion...',
            executing: 'Exécution : {name}...',
            executed: '{name} ✓',
            writing: 'Rédaction...',
        },
        es: {
            title: 'Asistente IA',
            placeholder: 'Escribe un mensaje...',
            conversations: 'Conversaciones',
            newChat: '+ Nueva conversación',
            delete: 'Eliminar',
            loadFailed: 'Error al cargar',
            noConversations: 'Sin conversaciones',
            errorLoading: 'Error al cargar',
            justNow: 'Ahora',
            minutesAgo: 'Hace {n} min',
            hoursAgo: 'Hace {n}h',
            daysAgo: 'Hace {n}d',
            error: 'Error: {msg}',
            stopped: 'Detenido',
            thinking: 'Pensando...',
            executing: 'Ejecutando: {name}...',
            executed: '{name} ✓',
            writing: 'Escribiendo...',
        },
        zh: {
            title: 'AI 助手',
            placeholder: '输入消息...',
            conversations: '对话列表',
            newChat: '+ 新对话',
            delete: '删除',
            loadFailed: '加载失败',
            noConversations: '暂无对话',
            errorLoading: '加载出错',
            justNow: '刚刚',
            minutesAgo: '{n}分钟前',
            hoursAgo: '{n}小时前',
            daysAgo: '{n}天前',
            error: '错误：{msg}',
            stopped: '已停止',
            thinking: '思考中...',
            executing: '执行: {name}...',
            executed: '{name} ✓',
            writing: '正在编写...',
        },
    };

    static rtlLanguages = ['ar', 'he', 'fa', 'ur'];

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.isOpen = false;
        this.messages = [];
        this.isTyping = false;
        this.isSending = false;
        this.abortController = null;
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

        // Load history from server, then show welcome if empty
        if (this.hasAttribute('persist-messages')) {
            this.loadHistoryFromServer().then(() => {
                if (this.messages.length === 0) {
                    const welcomeMsg = this.getAttribute('welcome-message');
                    if (welcomeMsg) this.addMessage(welcomeMsg, 'bot');
                }
            });
        } else {
            const welcomeMsg = this.getAttribute('welcome-message');
            if (welcomeMsg && this.messages.length === 0) {
                this.addMessage(welcomeMsg, 'bot');
            }
        }
    }

    /**
     * Synchronously restore conversationId from localStorage (before render)
     */
    loadMessagesSync() {
        if (!this.hasAttribute('persist-messages')) return;

        try {
            const key = this.getConversationKey();
            const savedId = localStorage.getItem(key);
            if (savedId) {
                this.conversationId = savedId;
            }
        } catch (e) {
            console.warn('AI Agent: Failed to restore conversation ID', e);
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

    get lang() {
        return this.getAttribute('lang') || 'en';
    }


    t(key, params = {}) {
        // Check for attribute override
        const attrMap = {
            conversations: 'conversations-label',
            newChat: 'new-chat-label',
            noConversations: 'no-conversations-label',
        };
        if (attrMap[key]) {
            const attrVal = this.getAttribute(attrMap[key]);
            if (attrVal) return attrVal;
        }

        const lang = this.lang;
        const translations = AIAgentChat.translations[lang] || AIAgentChat.translations['en'];
        let text = translations[key] || AIAgentChat.translations['en'][key] || key;
        Object.entries(params).forEach(([k, v]) => {
            text = text.replace(`{${k}}`, v);
        });
        return text;
    }

    get config() {
        const lang = this.lang;
        const isRtl = this.hasAttribute('rtl') || AIAgentChat.rtlLanguages.includes(lang);
        return {
            endpoint: this.getAttribute('endpoint') || '/api/chat',
            theme: this.getAttribute('theme') || 'dark',
            position: this.getAttribute('position') || 'bottom-right',
            width: this.getAttribute('width') || '420px',
            height: this.getAttribute('height') || '550px',
            rtl: isRtl,
            lang: lang,
            title: this.getAttribute('title') || this.t('title'),
            subtitle: this.getAttribute('subtitle') || '',
            placeholder: this.getAttribute('placeholder') || this.t('placeholder'),
            primaryColor: this.getAttribute('primary-color') || '#6366f1',
            buttonIcon: this.getAttribute('button-icon') || null,
            buttonSize: this.getAttribute('button-size') || '60px',
            persistMessages: this.hasAttribute('persist-messages'),
            historyEndpoint: this.getAttribute('history-endpoint') || null,
            stream: this.hasAttribute('stream'),
        };
    }

    // ================================
    // Message Persistence (Server-side)
    // ================================

    getConversationKey() {
        return `ai_agent_cid_${this.config.endpoint.replace(/[^a-z0-9]/gi, '_')}`;
    }

    getHistoryEndpoint() {
        // 1. Explicit attribute takes priority
        if (this.config.historyEndpoint) {
            return this.config.historyEndpoint;
        }
        // 2. Derive from chat endpoint: /ai-agent/chat → /ai-agent/history
        const endpoint = this.config.endpoint;
        if (endpoint.endsWith('/chat')) {
            return endpoint.replace(/\/chat$/, '/history');
        }
        return endpoint + '/history';
    }

    async loadHistoryFromServer() {
        try {
            const headers = { 'Accept': 'application/json' };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            const url = `${this.getHistoryEndpoint()}?conversation_id=${encodeURIComponent(this.conversationId)}`;
            const response = await fetch(url, { headers, credentials: 'same-origin' });

            if (!response.ok) return;

            const data = await response.json();
            if (data.success && data.messages && data.messages.length > 0) {
                this.messages = data.messages.map(msg => ({
                    content: msg.content,
                    role: msg.role === 'assistant' ? 'bot' : 'user',
                    time: ''
                }));
                this.updateMessagesUI();
            }
        } catch (e) {
            console.warn('AI Agent: Failed to load history from server', e);
        }
    }

    saveMessages() {
        // Save only conversationId to localStorage (messages are on the server)
        if (!this.config.persistMessages) return;
        try {
            localStorage.setItem(this.getConversationKey(), this.conversationId);
        } catch (e) {
            console.warn('AI Agent: Failed to save conversation ID', e);
        }
    }

    async clearMessages() {
        this.messages = [];

        // Clear on server
        try {
            const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            await fetch(this.getHistoryEndpoint(), {
                method: 'DELETE',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ conversation_id: this.conversationId }),
            });
        } catch (e) {
            console.warn('AI Agent: Failed to clear server history', e);
        }

        // Reset conversation
        this.conversationId = this.generateId();
        localStorage.removeItem(this.getConversationKey());
        this.updateMessagesUI();
    }

    updateMessagesUI() {
        const container = this.shadowRoot.querySelector('.widget-messages');
        if (!container) return;

        // Preserve typing indicator
        const typingIndicator = container.querySelector('.typing-indicator');

        // Clear and re-render
        container.innerHTML = this.renderMessages();

        // Re-add typing indicator
        if (typingIndicator) {
            container.appendChild(typingIndicator);
        } else {
            container.insertAdjacentHTML('beforeend', `
                <div class="typing-indicator" part="typing">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
            `);
        }

        container.scrollTop = container.scrollHeight;
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
        if (!icon) return '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';

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
                    --primary-dark: ${this.darkenColor(primaryColor, 5)};
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
                    color: #fff;
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

                /* Progress Block (SSE collapsible) */
                .progress-block {
                    margin-bottom: 8px;
                    border-radius: 12px;
                    overflow: hidden;
                    background: var(--bot-bubble);
                    border: 1px solid color-mix(in srgb, var(--primary) 15%, transparent);
                    animation: fadeInUp 0.25s ease;
                }

                .progress-header {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 14px;
                    cursor: pointer;
                    user-select: none;
                    font-size: 0.82rem;
                    color: var(--muted);
                    transition: background 0.2s;
                }

                .progress-header:hover {
                    background: color-mix(in srgb, var(--primary) 5%, transparent);
                }

                .progress-arrow {
                    font-size: 0.7rem;
                    transition: transform 0.2s ease;
                    display: inline-block;
                }

                .progress-block.collapsed .progress-arrow {
                    transform: rotate(-90deg);
                }

                .progress-summary {
                    flex: 1;
                }

                .progress-steps {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                    padding: 0 14px 10px;
                    overflow: hidden;
                    transition: max-height 0.3s ease, opacity 0.2s ease, padding 0.3s ease;
                    max-height: 500px;
                    opacity: 1;
                }

                .progress-block.collapsed .progress-steps {
                    max-height: 0;
                    opacity: 0;
                    padding: 0 14px;
                }

                .progress-step {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 5px 0;
                    font-size: 0.8rem;
                    color: var(--muted);
                    animation: fadeInUp 0.2s ease;
                    border-right: 2px solid color-mix(in srgb, var(--primary) 20%, transparent);
                    padding-right: 10px;
                }

                [dir="ltr"] .progress-step,
                :host(:not([rtl])) .progress-step {
                    border-right: none;
                    border-left: 2px solid color-mix(in srgb, var(--primary) 20%, transparent);
                    padding-right: 0;
                    padding-left: 10px;
                }

                .progress-step .step-icon { font-size: 0.85rem; flex-shrink: 0; }
                .progress-step.thinking .step-icon { animation: statusPulse 1s infinite ease-in-out; }
                .progress-step.success { color: var(--text); }
                .progress-step.error { color: #ef4444; }

                .progress-block.done { border-color: color-mix(in srgb, #22c55e 25%, transparent); }
                .progress-block.has-error { border-color: color-mix(in srgb, #ef4444 25%, transparent); }

                @keyframes statusPulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.3; }
                }

                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(6px); }
                    to { opacity: 1; transform: translateY(0); }
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
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .widget-send:hover { background: var(--primary-dark); }
                .widget-send.stop { background: #ef4444; }
                .widget-send.stop:hover { background: #dc2626; }

                .widget-input:disabled { opacity: 0.6; cursor: not-allowed; }

                /* Scrollbar */
                .widget-messages::-webkit-scrollbar { width: 6px; }
                .widget-messages::-webkit-scrollbar-track { background: transparent; }
                .widget-messages::-webkit-scrollbar-thumb { 
                    background: var(--border); 
                    border-radius: 3px; 
                }

                /* Conversations Panel */
                .conversations-panel {
                    display: none;
                    flex-direction: column;
                    height: 100%;
                    background: var(--bg);
                }
                .conversations-panel.open { display: flex; }

                .conversations-panel-header {
                    padding: 14px 16px;
                    background: var(--card);
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .conversations-panel-header h4 {
                    font-size: 0.9rem;
                    font-weight: 600;
                    color: var(--text);
                }
                .conv-back-btn {
                    background: none;
                    border: none;
                    color: var(--muted);
                    cursor: pointer;
                    font-size: 1.2rem;
                    padding: 4px 8px;
                    border-radius: 6px;
                    transition: all 0.2s;
                }
                .conv-back-btn:hover { color: var(--text); background: var(--border); }

                .conv-new-btn {
                    padding: 10px 16px;
                    margin: 12px;
                    background: var(--primary);
                    color: white;
                    border: none;
                    border-radius: 10px;
                    cursor: pointer;
                    font-size: 0.85rem;
                    font-weight: 500;
                    transition: background 0.2s;
                }
                .conv-new-btn:hover { background: var(--primary-dark); }

                .conversations-list {
                    flex: 1;
                    overflow-y: auto;
                    padding: 0 12px 12px;
                }

                .conv-item {
                    display: flex;
                    align-items: center;
                    padding: 12px;
                    margin-bottom: 4px;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: background 0.15s;
                    gap: 10px;
                }
                .conv-item:hover { background: var(--card); }
                .conv-item.active { background: var(--card); border: 1px solid var(--primary); }

                .conv-item-info {
                    flex: 1;
                    min-width: 0;
                }
                .conv-item-title {
                    font-size: 0.85rem;
                    color: var(--text);
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .conv-item-time {
                    font-size: 0.7rem;
                    color: var(--muted);
                    margin-top: 2px;
                }
                .conv-delete-btn {
                    background: none;
                    border: none;
                    color: var(--muted);
                    cursor: pointer;
                    font-size: 0.85rem;
                    padding: 4px 6px;
                    border-radius: 6px;
                    opacity: 0;
                    transition: all 0.2s;
                }
                .conv-item:hover .conv-delete-btn { opacity: 1; }
                .conv-delete-btn:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

                .conv-empty {
                    text-align: center;
                    padding: 40px 20px;
                    color: var(--muted);
                    font-size: 0.85rem;
                }

                /* Header conversations button */
                .widget-header-actions {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }
                .widget-conv-btn {
                    background: rgba(255,255,255,0.2);
                    border: none;
                    color: white;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 0.9rem;
                    transition: background 0.2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .widget-conv-btn:hover { background: rgba(255,255,255,0.3); }

                /* Chat view container */
                .chat-view {
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                }
                .conversations-panel.open ~ .chat-view { display: none; }

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
                <!-- Conversations Panel -->
                <div class="conversations-panel" part="conversations">
                    <div class="conversations-panel-header">
                        <h4>${this.t('conversations')}</h4>
                        <button class="conv-back-btn">${rtl ? '→' : '←'}</button>
                    </div>
                    <button class="conv-new-btn">${this.t('newChat')}</button>
                    <div class="conversations-list"></div>
                </div>

                <!-- Chat View -->
                <div class="chat-view">
                    <div class="widget-header" part="header">
                        <div class="widget-header-info">
                            <h3>${title}</h3>
                            ${subtitle ? `<p>${subtitle}</p>` : ''}
                        </div>
                        <div class="widget-header-actions">
                            <button class="widget-conv-btn" title="${this.t('conversations')}">☰</button>
                            <button class="widget-close" part="close-button">×</button>
                        </div>
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
                            ${rtl ? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transform: scaleX(-1);"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send-horizontal-icon lucide-send-horizontal"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderMessages() {
        return this.messages.map(msg => this.renderSingleMessage(msg)).join('');
    }

    renderSingleMessage(msg) {
        const content = msg.role === 'bot' ? this.parseMarkdown(msg.content) : this.escapeHtml(msg.content);
        return `
            <div class="message message-${msg.role}">
                <div class="message-content">${content}</div>
                <div class="message-time">${msg.time}</div>
            </div>
        `;
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
                window: 'bottom: 20px; right: 20px;',
            },
            'bottom-left': {
                button: 'bottom: 20px; left: 20px;',
                window: 'bottom: 20px; left: 20px;',
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
        sendBtn.addEventListener('click', () => {
            this.isSending ? this.stopMessage() : this.sendMessage();
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });

        // Conversations panel
        const convBtn = this.shadowRoot.querySelector('.widget-conv-btn');
        const backBtn = this.shadowRoot.querySelector('.conv-back-btn');
        const newBtn = this.shadowRoot.querySelector('.conv-new-btn');

        if (convBtn) convBtn.addEventListener('click', () => this.showConversations());
        if (backBtn) backBtn.addEventListener('click', () => this.hideConversations());
        if (newBtn) newBtn.addEventListener('click', () => this.newConversation());
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

        if (!message || this.isSending) return;

        input.value = '';
        this.addMessage(message, 'user');
        this.setSending(true);

        try {
            if (this.config.stream) {
                // SSE mode: show status indicator instead of typing dots
                await this.fetchStreamResponse(message);
            } else {
                // Classic mode: show typing dots, wait for full response
                this.showTyping(true);
                const response = await this.fetchResponse(message);
                this.showTyping(false);
                this.addMessage(response, 'bot');
            }
        } catch (error) {
            this.showTyping(false);
            this.clearSteps();
            if (error.name === 'AbortError') {
                this.addMessage(this.t('stopped'), 'bot');
            } else {
                this.addMessage(this.t('error', { msg: error.message }), 'bot');
                this.dispatchEvent(new CustomEvent('error', { detail: error }));
            }
        } finally {
            this.setSending(false);
        }
    }

    stopMessage() {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    setSending(sending) {
        this.isSending = sending;
        const input = this.shadowRoot.querySelector('.widget-input');
        const sendBtn = this.shadowRoot.querySelector('.widget-send');
        if (input) input.disabled = sending;
        if (sendBtn) {
            sendBtn.classList.toggle('stop', sending);
            sendBtn.innerHTML = sending
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>'
                : this.getSendIcon();
        }
    }

    getSendIcon() {
        const rtl = this.config.rtl;
        return rtl
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transform: scaleX(-1);"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>';
    }

    async fetchResponse(message) {
        this.abortController = new AbortController();

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
            signal: this.abortController.signal,
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
        this.abortController = null;
        let result = data.response || data.message || data.content || '';
        // Handle case where response is an object (e.g. AgentResponse serialized)
        if (typeof result === 'object' && result !== null) {
            result = result.content || JSON.stringify(result);
        }
        return result || JSON.stringify(data);
    }

    // ================================
    // SSE Streaming
    // ================================

    getStreamEndpoint() {
        const endpoint = this.config.endpoint;
        // /ai-agent/chat → /ai-agent/chat-stream
        if (endpoint.endsWith('/chat')) {
            return endpoint + '-stream';
        }
        return endpoint + '-stream';
    }

    async fetchStreamResponse(message) {
        this.abortController = new AbortController();

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(this.getStreamEndpoint(), {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            signal: this.abortController.signal,
            body: JSON.stringify({
                message,
                conversation_id: this.conversationId,
            }),
        });

        if (!response.ok) {
            let errorMessage = `HTTP ${response.status}`;
            try {
                const errorText = await response.text();
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.error || errorData.message || errorMessage;
            } catch (e) { }
            throw new Error(errorMessage);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const events = this.parseSSEEvents(buffer);
            buffer = events.remaining;

            for (const event of events.parsed) {
                this.handleSSEEvent(event.event, event.data);
            }
        }

        this.abortController = null;
        this._activeProgressBlock = null;
    }

    parseSSEEvents(buffer) {
        const parsed = [];
        const blocks = buffer.split('\n\n');
        // Last block might be incomplete
        const remaining = blocks.pop() || '';

        for (const block of blocks) {
            if (!block.trim()) continue;
            let event = 'message';
            let data = '';

            for (const line of block.split('\n')) {
                if (line.startsWith('event: ')) {
                    event = line.slice(7).trim();
                } else if (line.startsWith('data: ')) {
                    data = line.slice(6);
                }
            }

            if (data) {
                try {
                    parsed.push({ event, data: JSON.parse(data) });
                } catch (e) {
                    parsed.push({ event, data: { raw: data } });
                }
            }
        }

        return { parsed, remaining };
    }

    handleSSEEvent(event, data) {
        const block = this.getOrCreateProgressBlock();
        const stepsContainer = block.querySelector('.progress-steps');

        switch (event) {
            case 'thinking':
                this.removeThinkingStep(stepsContainer);
                this.addProgressStep(stepsContainer, '🤔', this.t('thinking'), 'thinking');
                this.updateProgressHeader(block, '⏳', this.t('thinking'));
                break;

            case 'tool_start': {
                this.removeThinkingStep(stepsContainer);
                const label = this.getToolLabel(data);
                this.addProgressStep(stepsContainer, '🔧', this.t('executing', { name: label }), 'running', `tool-${data.name}`);
                this.updateProgressHeader(block, '⏳', this.t('executing', { name: label }));
                break;
            }

            case 'tool_done': {
                const label = this.getToolLabel(data);
                const stepId = `tool-${data.name}`;
                const existing = stepsContainer.querySelector(`.progress-step[data-id="${stepId}"]`);
                if (existing) {
                    existing.className = `progress-step ${data.success ? 'success' : 'error'}`;
                    existing.querySelector('.step-icon').textContent = data.success ? '✅' : '❌';
                    existing.querySelector('.step-text').textContent = data.success
                        ? this.t('executed', { name: label })
                        : `❌ ${label}`;
                } else {
                    this.addProgressStep(stepsContainer,
                        data.success ? '✅' : '❌',
                        data.success ? this.t('executed', { name: label }) : `❌ ${label}`,
                        data.success ? 'success' : 'error'
                    );
                }
                if (!data.success) block.classList.add('has-error');
                break;
            }

            case 'done':
                this.removeThinkingStep(stepsContainer);
                this.finalizeProgressBlock(block);
                if (data.content) {
                    this.addMessage(data.content, 'bot');
                }
                this._activeProgressBlock = null;
                break;

            case 'error':
                this.removeThinkingStep(stepsContainer);
                block.classList.add('has-error');
                this.addProgressStep(stepsContainer, '❌', data.message || 'Error', 'error');
                this.finalizeProgressBlock(block, true);
                this.addMessage(this.t('error', { msg: data.message || 'Unknown error' }), 'bot');
                this._activeProgressBlock = null;
                break;
        }

        const messagesContainer = this.shadowRoot.querySelector('.widget-messages');
        if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

        this.dispatchEvent(new CustomEvent('sse-event', {
            detail: { event, data }
        }));
    }

    getToolLabel(data) {
        return data.description || this.humanizeName(data.name || '');
    }

    humanizeName(name) {
        // showProduct → Show Product, searchProducts → Search Products
        return name
            .replace(/([A-Z])/g, ' $1')
            .replace(/^./, c => c.toUpperCase())
            .trim();
    }

    getOrCreateProgressBlock() {
        if (this._activeProgressBlock) return this._activeProgressBlock;

        const container = this.shadowRoot.querySelector('.widget-messages');
        if (!container) return null;

        const block = document.createElement('div');
        block.className = 'progress-block';
        block.innerHTML = `
            <div class="progress-header">
                <span class="progress-arrow">▼</span>
                <span class="progress-summary">⏳ ${this.t('thinking')}</span>
            </div>
            <div class="progress-steps"></div>
        `;

        // Insert before typing indicator
        const typingIndicator = container.querySelector('.typing-indicator');
        if (typingIndicator) {
            container.insertBefore(block, typingIndicator);
        } else {
            container.appendChild(block);
        }

        // Toggle collapse on header click
        block.querySelector('.progress-header').addEventListener('click', () => {
            block.classList.toggle('collapsed');
        });

        this._activeProgressBlock = block;
        return block;
    }

    addProgressStep(container, icon, text, className = '', dataId = '') {
        const step = document.createElement('div');
        step.className = `progress-step ${className}`;
        if (dataId) step.setAttribute('data-id', dataId);
        step.innerHTML = `<span class="step-icon">${icon}</span><span class="step-text">${text}</span>`;
        container.appendChild(step);
    }

    updateProgressHeader(block, icon, text) {
        const summary = block.querySelector('.progress-summary');
        if (summary) summary.textContent = `${icon} ${text}`;
    }

    removeThinkingStep(container) {
        if (!container) return;
        container.querySelectorAll('.progress-step.thinking').forEach(el => el.remove());
    }

    finalizeProgressBlock(block, hasError = false) {
        const steps = block.querySelectorAll('.progress-step');
        const successCount = block.querySelectorAll('.progress-step.success').length;
        const errorCount = block.querySelectorAll('.progress-step.error').length;
        const total = successCount + errorCount;

        block.classList.add('collapsed', hasError ? 'has-error' : 'done');

        const summary = block.querySelector('.progress-summary');
        if (summary) {
            if (hasError) {
                summary.textContent = `❌ ${this.t('error', { msg: '' }).replace(': ', '')} (${errorCount})`;
            } else {
                const lang = this.config.lang;
                const stepsWord = lang === 'ar' ? 'خطوات' : lang === 'fr' ? 'étapes' : lang === 'es' ? 'pasos' : lang === 'zh' ? '步骤' : 'steps';
                summary.textContent = `✅ ${total} ${stepsWord}`;
            }
        }
    }

    clearSteps() {
        // Only used on error/abort in sendMessage catch block
        if (this._activeProgressBlock) {
            this._activeProgressBlock.remove();
            this._activeProgressBlock = null;
        }
    }

    addMessage(content, role) {
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        this.messages.push({ content, role, time });

        // Append only the new message to the DOM (don't re-render everything)
        const container = this.shadowRoot.querySelector('.widget-messages');
        if (container) {
            const html = this.renderSingleMessage({ content, role, time });
            const typingIndicator = container.querySelector('.typing-indicator');
            if (typingIndicator) {
                typingIndicator.insertAdjacentHTML('beforebegin', html);
            } else {
                container.insertAdjacentHTML('beforeend', html);
            }
            container.scrollTop = container.scrollHeight;
        }

        this.saveMessages();

        this.dispatchEvent(new CustomEvent(role === 'user' ? 'message-sent' : 'message-received', {
            detail: { content, role, time }
        }));
    }

    updateMessages() {
        const container = this.shadowRoot.querySelector('.widget-messages');
        if (!container) return;

        // Remove all messages AND progress blocks (full re-render)
        container.querySelectorAll('.message, .progress-block').forEach(el => el.remove());

        const typingIndicator = container.querySelector('.typing-indicator');
        if (typingIndicator) {
            typingIndicator.insertAdjacentHTML('beforebegin', this.renderMessages());
        } else {
            container.insertAdjacentHTML('beforeend', this.renderMessages());
        }

        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    showTyping(show) {
        this.isTyping = show;
        const indicator = this.shadowRoot.querySelector('.typing-indicator');
        if (!indicator) return;
        indicator.classList.toggle('show', show);

        if (show) {
            const container = this.shadowRoot.querySelector('.widget-messages');
            if (container) container.scrollTop = container.scrollHeight;
        }
    }

    // ================================
    // Conversations Management
    // ================================

    async showConversations() {
        const panel = this.shadowRoot.querySelector('.conversations-panel');
        panel.classList.add('open');
        await this.loadConversationsList();
    }

    hideConversations() {
        const panel = this.shadowRoot.querySelector('.conversations-panel');
        panel.classList.remove('open');
    }

    getConversationsEndpoint() {
        if (this.config.historyEndpoint) {
            return this.config.historyEndpoint.replace(/\/history$/, '/conversations');
        }
        const endpoint = this.config.endpoint;
        if (endpoint.endsWith('/chat')) {
            return endpoint.replace(/\/chat$/, '/conversations');
        }
        return endpoint + '/conversations';
    }

    async loadConversationsList() {
        const list = this.shadowRoot.querySelector('.conversations-list');
        list.innerHTML = `<div class="conv-empty">...</div>`;

        try {
            const headers = { 'Accept': 'application/json' };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            const response = await fetch(this.getConversationsEndpoint(), {
                headers, credentials: 'same-origin'
            });

            if (!response.ok) {
                list.innerHTML = `<div class="conv-empty">${this.t('loadFailed')}</div>`;
                return;
            }

            const data = await response.json();
            if (!data.success || !data.conversations || data.conversations.length === 0) {
                list.innerHTML = `<div class="conv-empty">${this.t('noConversations')}</div>`;
                return;
            }

            list.innerHTML = data.conversations.map(conv => `
                <div class="conv-item ${conv.id === this.conversationId ? 'active' : ''}" data-id="${conv.id}">
                    <div class="conv-item-info">
                        <div class="conv-item-title">${this.escapeHtml(conv.title)}</div>
                        <div class="conv-item-time">${this.formatTime(conv.updated_at)}</div>
                    </div>
                    <button class="conv-delete-btn" data-id="${conv.id}" title="${this.t('delete')}">🗑</button>
                </div>
            `).join('');

            // Click to switch
            list.querySelectorAll('.conv-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('.conv-delete-btn')) return;
                    this.switchConversation(item.dataset.id);
                });
            });

            // Delete
            list.querySelectorAll('.conv-delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.deleteConversation(btn.dataset.id);
                });
            });
        } catch (e) {
            list.innerHTML = `<div class="conv-empty">${this.t('errorLoading')}</div>`;
            console.warn('AI Agent: Failed to load conversations', e);
        }
    }

    async switchConversation(id) {
        this.conversationId = id;
        this.messages = [];
        this.saveMessages();
        this.updateMessagesUI();
        this.hideConversations();

        // Load messages from server
        await this.loadHistoryFromServer();

        if (this.messages.length === 0) {
            const welcomeMsg = this.getAttribute('welcome-message');
            if (welcomeMsg) this.addMessage(welcomeMsg, 'bot');
        }
    }

    async newConversation() {
        this.conversationId = this.generateId();
        this.messages = [];
        this.saveMessages();
        this.updateMessagesUI();
        this.hideConversations();

        const welcomeMsg = this.getAttribute('welcome-message');
        if (welcomeMsg) this.addMessage(welcomeMsg, 'bot');
    }

    async deleteConversation(id) {
        try {
            const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            await fetch(this.getHistoryEndpoint(), {
                method: 'DELETE',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ conversation_id: id }),
            });

            // If deleted current conversation, start new one
            if (id === this.conversationId) {
                await this.newConversation();
            }

            // Refresh list
            await this.loadConversationsList();
        } catch (e) {
            console.warn('AI Agent: Failed to delete conversation', e);
        }
    }

    formatTime(dateStr) {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMin = Math.floor(diffMs / 60000);
            const diffHr = Math.floor(diffMs / 3600000);
            const diffDay = Math.floor(diffMs / 86400000);

            if (diffMin < 1) return this.t('justNow');
            if (diffMin < 60) return this.t('minutesAgo', { n: diffMin });
            if (diffHr < 24) return this.t('hoursAgo', { n: diffHr });
            if (diffDay < 7) return this.t('daysAgo', { n: diffDay });
            return date.toLocaleDateString();
        } catch {
            return '';
        }
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
