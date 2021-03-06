<?php

declare(strict_types=1);

use App\Http\Middleware;
use Middlewares\ContentLanguage;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;

// Connect middlewares with desc order(ErrorMiddleware should be last).
return static function (App $app): void {
    $app->add(Middleware\DomainExceptionHandler::class); // Added wrap with "try-catch", for catch DomainExceptions.
    $app->add(Middleware\ValidationExceptionHandler::class); // Wrap with "try-catch", for catch Validation exceptions.
    $app->add(Middleware\ClearEmptyInput::class); // Strip whitespace and empty files.
    $app->add(Middleware\TranslatorLocale::class); // Added translator locale.
    $app->add(ContentLanguage::class); // Added translator locales.
    $app->addBodyParsingMiddleware();
    $app->add(ErrorMiddleware::class);
};
