<?php

namespace LaravelAIAgent;

/**
 * Generic widget renderer that reads configuration from config/ai-agent.php.
 * Use this when you don't need a class-based agent.
 *
 * Usage in Blade:
 *   {!! \LaravelAIAgent\Widget::render() !!}
 *   <script src="/ai-agent/widget.js"></script>
 *
 * Or with the Blade directive:
 *   @aiAgentWidget
 *   <script src="/ai-agent/widget.js"></script>
 */
class Widget
{
    /**
     * Render the generic widget HTML tag using config values.
     *
     * @param array $extra Extra attributes to override config values
     * @return string
     */
    public static function render(array $extra = []): string
    {
        $prefix = config('ai-agent.widget.prefix', 'ai-agent');

        $attrs = [
            'stream' => '',
            'endpoint' => "/{$prefix}/chat",
            'history-endpoint' => "/{$prefix}/history",
            'persist-messages' => '',
        ];

        // Map config keys to HTML attributes
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

        foreach ($attrMap as $configKey => $htmlAttr) {
            $value = config("ai-agent.widget.{$configKey}");
            if ($value !== null && $value !== '') {
                $attrs[$htmlAttr] = $value;
            }
        }

        // RTL: explicit setting or auto-detect from lang
        $rtl = config('ai-agent.widget.rtl');
        if ($rtl === true) {
            $attrs['rtl'] = '';
        } elseif ($rtl === null) {
            $lang = config('ai-agent.widget.lang');
            $rtlLangs = ['ar', 'he', 'fa', 'ur'];
            if ($lang && in_array($lang, $rtlLangs)) {
                $attrs['rtl'] = '';
            }
        }

        // Merge extra attributes (overrides)
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
