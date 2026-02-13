<?php

namespace LaravelAIAgent\Concerns;

trait HasWidgetConfig
{
    /**
     * Get the widget title.
     */
    public function widgetTitle(): ?string
    {
        return null;
    }

    /**
     * Get the widget subtitle.
     */
    public function widgetSubtitle(): ?string
    {
        return null;
    }

    /**
     * Get the widget theme ('light' or 'dark').
     */
    public function widgetTheme(): ?string
    {
        return null;
    }

    /**
     * Get the widget welcome message.
     */
    public function widgetWelcomeMessage(): ?string
    {
        return null;
    }

    /**
     * Get the widget placeholder text.
     */
    public function widgetPlaceholder(): ?string
    {
        return null;
    }

    /**
     * Get the widget language.
     */
    public function widgetLang(): ?string
    {
        return null;
    }

    /**
     * Get the widget primary color.
     */
    public function widgetPrimaryColor(): ?string
    {
        return null;
    }

    /**
     * Get the widget position ('bottom-right', 'bottom-left', etc.).
     */
    public function widgetPosition(): ?string
    {
        return null;
    }

    /**
     * Get the widget width (e.g. '400px', '24rem').
     */
    public function widgetWidth(): ?string
    {
        return null;
    }

    /**
     * Get the widget height (e.g. '600px', '80vh').
     */
    public function widgetHeight(): ?string
    {
        return null;
    }

    /**
     * Get the widget button icon (URL or emoji).
     */
    public function widgetButtonIcon(): ?string
    {
        return null;
    }

    /**
     * Get the widget button size (e.g. '60px').
     */
    public function widgetButtonSize(): ?string
    {
        return null;
    }

    /**
     * Force RTL layout. Null = auto-detect from lang.
     */
    public function widgetRtl(): ?bool
    {
        return null;
    }

    /**
     * Get all widget configuration as an array.
     * Only includes non-null values (overridden methods).
     */
    public function widgetConfig(): array
    {
        return array_filter([
            'title' => $this->widgetTitle(),
            'subtitle' => $this->widgetSubtitle(),
            'theme' => $this->widgetTheme(),
            'welcome_message' => $this->widgetWelcomeMessage(),
            'placeholder' => $this->widgetPlaceholder(),
            'lang' => $this->widgetLang(),
            'primary_color' => $this->widgetPrimaryColor(),
            'position' => $this->widgetPosition(),
            'width' => $this->widgetWidth(),
            'height' => $this->widgetHeight(),
            'button_icon' => $this->widgetButtonIcon(),
            'button_size' => $this->widgetButtonSize(),
            'rtl' => $this->widgetRtl(),
        ], fn($v) => $v !== null);
    }

    /**
     * Render the widget HTML tag with all configuration attributes.
     *
     * @param array $extra Extra attributes to add to the tag
     */
    public static function widget(array $extra = []): string
    {
        $instance = new static;
        $agentName = static::routeName();
        $config = $instance->widgetConfig();

        $prefix = $instance->routePrefix();

        $attrs = [
            'stream' => '',
            'endpoint' => "/{$prefix}/{$agentName}/chat",
            'history-endpoint' => "/{$prefix}/{$agentName}/history",
            'persist-messages' => '',
        ];

        // Map config to HTML attributes
        $attrMap = [
            'title' => 'title',
            'subtitle' => 'subtitle',
            'theme' => 'theme',
            'welcome_message' => 'welcome-message',
            'placeholder' => 'placeholder',
            'lang' => 'lang',
            'primary_color' => 'primary-color',
            'position' => 'position',
            'width' => 'width',
            'height' => 'height',
            'button_icon' => 'button-icon',
            'button_size' => 'button-size',
        ];

        foreach ($attrMap as $key => $attr) {
            if (isset($config[$key])) {
                $attrs[$attr] = $config[$key];
            }
        }

        // RTL: explicit setting or auto-detect from lang
        if (isset($config['rtl']) && $config['rtl'] === true) {
            $attrs['rtl'] = '';
        } elseif (!isset($config['rtl'])) {
            $rtlLangs = ['ar', 'he', 'fa', 'ur'];
            if (isset($config['lang']) && in_array($config['lang'], $rtlLangs)) {
                $attrs['rtl'] = '';
            }
        }

        // Merge extra attributes
        $attrs = array_merge($attrs, $extra);

        // Build HTML
        $html = '<ai-agent-chat';
        foreach ($attrs as $key => $value) {
            $html .= $value === '' ? " {$key}" : ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }
        $html .= '></ai-agent-chat>';

        return $html;
    }
}
