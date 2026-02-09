<?php

namespace LaravelAIAgent\Tools;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class ResultTransformer
{
    /**
     * Transform any result into AI-friendly data.
     * Extracts meaningful data from Views, Redirects, Responses, Models, etc.
     */
    public function transform(mixed $result): mixed
    {
        // Already AI-friendly types
        if (is_string($result) || is_numeric($result) || is_bool($result) || is_null($result)) {
            return $result;
        }

        if (is_array($result)) {
            return $result;
        }

        // Eloquent Model → toArray()
        if ($result instanceof Model) {
            return $result->toArray();
        }

        // Collection → toArray()
        if ($result instanceof Collection) {
            return $result->toArray();
        }

        // View → extract the data passed to the view
        if ($result instanceof View) {
            return $this->transformView($result);
        }

        // JsonResponse → extract JSON data
        if ($result instanceof JsonResponse) {
            return $this->transformJsonResponse($result);
        }

        // RedirectResponse → extract flash data & target URL
        if ($result instanceof RedirectResponse) {
            return $this->transformRedirect($result);
        }

        // Generic Response → extract content
        if ($result instanceof Response) {
            return $this->transformResponse($result);
        }

        // Responsable (e.g. API Resources) → convert to response then extract
        if ($result instanceof Responsable) {
            return $this->transformResponsable($result);
        }

        // Arrayable (e.g. Paginator, ResourceCollection)
        if ($result instanceof Arrayable) {
            return $result->toArray();
        }

        // Jsonable
        if ($result instanceof Jsonable) {
            return json_decode($result->toJson(), true);
        }

        // Stringable objects
        if (is_object($result) && method_exists($result, '__toString')) {
            return (string) $result;
        }

        // Last resort: try json encode/decode
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && $encoded !== '{}' && $encoded !== 'null') {
            return json_decode($encoded, true) ?? $encoded;
        }

        return ['_notice' => 'Tool executed successfully but returned non-serializable result', '_type' => get_class($result)];
    }

    /**
     * Extract data from a View object.
     * Gets the variables passed to the view, not the rendered HTML.
     */
    protected function transformView(View $view): array
    {
        $data = $view->getData();

        // Remove internal Laravel variables
        unset($data['__env'], $data['app'], $data['errors']);

        // Convert any nested objects to arrays
        $transformed = [];
        foreach ($data as $key => $value) {
            if ($value instanceof Model) {
                $transformed[$key] = $value->toArray();
            } elseif ($value instanceof Collection) {
                $transformed[$key] = $value->toArray();
            } elseif ($value instanceof Arrayable) {
                $transformed[$key] = $value->toArray();
            } elseif (is_object($value)) {
                $transformed[$key] = json_decode(json_encode($value), true) ?? (string) $value;
            } else {
                $transformed[$key] = $value;
            }
        }

        // Return data directly — AI doesn't need to know it came from a view
        return $transformed;
    }

    /**
     * Extract data from a JsonResponse.
     */
    protected function transformJsonResponse(JsonResponse $response): mixed
    {
        $data = $response->getData(true);

        return is_array($data) ? $data : ['result' => $data];
    }

    /**
     * Extract flash data from a RedirectResponse.
     * AI agents don't do redirects — extract the meaningful messages only.
     */
    protected function transformRedirect(RedirectResponse $response): array
    {
        $result = [];

        // Extract flash data from session — this is the real "data"
        $session = $response->getSession();
        if ($session) {
            foreach (['message', 'success', 'error', 'warning', 'info', 'status', 'notification'] as $key) {
                $value = $session->get($key);
                if ($value !== null) {
                    $result[$key] = $value;
                }
            }

            // Also check _flash.new for any custom flash keys
            $flashKeys = $session->get('_flash.new', []);
            foreach ($flashKeys as $key) {
                if (!isset($result[$key])) {
                    $result[$key] = $session->get($key);
                }
            }
        }

        // If no flash data found, provide a generic success message
        if (empty($result)) {
            $result['success'] = true;
            $result['message'] = 'Operation completed successfully.';
        }

        return $result;
    }

    /**
     * Extract content from a generic Response.
     */
    protected function transformResponse(Response $response): mixed
    {
        $content = $response->getContent();

        // Try to parse as JSON first
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If it's HTML, strip tags and get text
        if (str_contains($content, '<html') || str_contains($content, '<body')) {
            $text = strip_tags($content);
            $text = preg_replace('/\s+/', ' ', trim($text));
            return ['_source' => 'html_response', 'content' => mb_substr($text, 0, 1000)];
        }

        return ['content' => mb_substr($content, 0, 2000)];
    }

    /**
     * Transform a Responsable object (e.g. API Resources).
     */
    protected function transformResponsable(Responsable $result): mixed
    {
        try {
            $response = $result->toResponse(request());

            if ($response instanceof JsonResponse) {
                return $this->transformJsonResponse($response);
            }

            if ($response instanceof Response) {
                return $this->transformResponse($response);
            }

            return $this->transform($response);
        } catch (\Throwable $e) {
            return ['_notice' => 'Responsable conversion failed', '_error' => $e->getMessage()];
        }
    }
}
