<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Strip whitespace (or other characters) from the beginning and end of a string
 */
class ClearEmptyInput implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request data for filter
     * @param RequestHandlerInterface $handler handler (next action)
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request
            ->withParsedBody(self::filterStrings($request->getParsedBody()))
            ->withUploadedFiles(self::filterFiles($request->getUploadedFiles()));

        return $handler->handle($request);
    }

    /**
     * Filter for strings
     *
     * @param null|array|object $items
     * @return null|array|object
     */
    private static function filterStrings($items)
    {
        if (!is_array($items)) {
            return $items;
        }

        $result = [];

        /**
         * @var string $key
         * @var null|string|object $item
         */
        foreach ($items as $key => $item) {
            if (is_string($item)) {
                $result[$key] = trim($item);
            } else {
                $result[$key] = self::filterStrings($item);
            }
        }

        return $result;
    }

    /**
     * Filter for files
     * Clear empty files
     *
     * @param array $items
     *
     * @return array
     */
    private static function filterFiles(array $items): array
    {
        $result = [];

        /**
         * @var string $key
         * @var array|UploadedFileInterface $item
         */
        foreach ($items as $key => $item) {
            if ($item instanceof UploadedFileInterface) {
                if ($item->getError() !== UPLOAD_ERR_NO_FILE) {
                    $result[$key] = $item;
                }
            } else {
                $result[$key] = self::filterFiles($item);
            }
        }

        return $result;
    }
}
