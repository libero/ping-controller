<?php

declare(strict_types=1);

namespace Libero\PingController;

use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function call_user_func;
use function in_array;
use function restore_error_handler;
use function set_error_handler;
use const E_DEPRECATED;
use const E_USER_DEPRECATED;

final class PingController
{
    private $test;
    private $logger;

    public function __construct(callable $test = null, LoggerInterface $logger = null)
    {
        $this->test = $test;
        $this->logger = $logger;
    }

    public function __invoke(Request $request) : Response
    {
        if ($this->test) {
            $this->ensureLogger();

            set_error_handler(
                function (int $severity, string $message, string $file, int $line) : bool {
                    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                        $this->logger->log(LogLevel::NOTICE, $message);

                        return true;
                    }

                    throw new ErrorException($message, 0, $severity, $file, $line);
                }
            );

            try {
                call_user_func($this->test);
            } catch (RuntimeException $e) {
                $this->logger->critical('Ping failed', ['exception' => $e]);

                return $this->createResponse($request, Response::HTTP_SERVICE_UNAVAILABLE);
            } catch (Throwable $e) {
                $this->logger->log(
                    $e instanceof Exception ? LogLevel::ERROR : LogLevel::CRITICAL,
                    'Ping failed',
                    ['exception' => $e]
                );

                return $this->createResponse($request, Response::HTTP_INTERNAL_SERVER_ERROR);
            } finally {
                restore_error_handler();
            }
        }

        return $this->createResponse($request, Response::HTTP_OK, 'pong');
    }

    private function createResponse(Request $request, int $statusCode, ?string $content = null) : Response
    {
        $response = new Response(
            $content ?? Response::$statusTexts[$statusCode],
            $statusCode,
            [
                'Cache-Control' => 'must-revalidate, no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
            ]
        );

        if ('HTTP/1.0' === $request->getProtocolVersion()) {
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function ensureLogger() : void
    {
        $this->logger = $this->logger ?? new NullLogger();
    }
}
