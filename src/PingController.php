<?php

declare(strict_types=1);

namespace Libero\PingController;

use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function call_user_func;
use function restore_error_handler;
use function set_error_handler;

final class PingController
{
    private $test;
    private $logger;

    public function __construct(callable $test = null, LoggerInterface $logger = null)
    {
        $this->test = $test;
        $this->logger = $logger;
    }

    public function __invoke() : Response
    {
        if ($this->test) {
            set_error_handler(
                function (int $severity, string $message, string $file, int $line) : bool {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                }
            );

            try {
                call_user_func($this->test);
            } catch (RuntimeException $e) {
                if ($this->logger) {
                    $this->logger->critical('Ping failed', ['exception' => $e]);
                }

                return $this->createResponse(Response::HTTP_SERVICE_UNAVAILABLE);
            } catch (Throwable $e) {
                if ($this->logger) {
                    $this->logger->log(
                        $e instanceof Exception ? LogLevel::ALERT : LogLevel::EMERGENCY,
                        'Ping failed',
                        ['exception' => $e]
                    );
                }

                return $this->createResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
            } finally {
                restore_error_handler();
            }
        }

        return $this->createResponse(Response::HTTP_OK, 'pong');
    }

    private function createResponse(int $statusCode, ?string $content = null) : Response
    {
        return new Response(
            $content ?? Response::$statusTexts[$statusCode],
            $statusCode,
            [
                'Cache-Control' => 'must-revalidate, no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'Expires' => '0',
            ]
        );
    }
}
