<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse as LaravelJsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddServerResponseMessage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $serverNumber = (string) config('app.server_number', '1');
        $message = "Respondio servidor: {$serverNumber}.";

        $response->headers->set('X-Server-Number', $serverNumber);
        $response->headers->set('X-Server-Response', $message);

        if ($this->cannotModifyBody($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse || $response instanceof LaravelJsonResponse) {
            $this->addJsonMessage($response, $message);

            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        $content = (string) $response->getContent();

        if (str_contains($contentType, 'text/html')) {
            $this->setContent($response, $this->addHtmlMessage($content, $message));

            return $response;
        }

        if (str_contains($contentType, 'text/plain')) {
            $this->setContent($response, rtrim($content).PHP_EOL.$message);
        }

        return $response;
    }

    private function cannotModifyBody(Response $response): bool
    {
        return $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->isRedirection()
            || $response->isEmpty();
    }

    private function addJsonMessage(JsonResponse $response, string $message): void
    {
        $data = $response->getData(true);

        if (is_array($data) && ! array_is_list($data)) {
            $data['respondio_servidor'] = $message;
        } else {
            $data = [
                'data' => $data,
                'respondio_servidor' => $message,
            ];
        }

        $response->setData($data);
        $response->headers->remove('Content-Length');
    }

    private function addHtmlMessage(string $content, string $message): string
    {
        $html = '<div class="server-response" style="position:fixed;right:12px;bottom:12px;z-index:9999;padding:6px 10px;border-radius:6px;background:#111827;color:#fff;font:12px/1.4 system-ui,sans-serif;">'
            .e($message).
            '</div>';

        $updated = str_ireplace('</body>', $html.'</body>', $content, $count);

        return $count > 0 ? $updated : $content.PHP_EOL.$html;
    }

    private function setContent(Response $response, string $content): void
    {
        $response->setContent($content);
        $response->headers->remove('Content-Length');
    }
}
