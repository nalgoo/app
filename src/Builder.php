<?php

namespace Nalgoo\App;

use ErrorException;
use Nalgoo\App\Error\ErrorRenderer;
use Nalgoo\App\Error\ShutdownHandler;
use Nalgoo\App\Handlers\HttpErrorHandler;
use Nalgoo\App\Interfaces\ResponseEmitterInterface;
use Nalgoo\App\Middleware\ReverseProxyMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\CallableResolver;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Interfaces\AdvancedCallableResolverInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteResolverInterface;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\ResponseEmitter;
use Slim\Routing\RouteCollector;
use Slim\Routing\RouteResolver;

class Builder
{
	/**
	 * Sets sane PHP defaults for error reporting and timezone
	 */
	public static function setDefaults(): void
	{
		// throw Exceptions on error
		set_error_handler(static function ($severity, $message, $file, $line) {
			if (!(error_reporting() & $severity)) {
				// This error code is not included in error_reporting
				return;
			}
			throw new ErrorException($message, 0, $severity, $file, $line);
		});

		// report all errors
		error_reporting(E_ALL);
		ini_set('display_errors', 'off');
		ini_set('display_startup_errors', 'off');
		error_clear_last();

		// timezone
		date_default_timezone_set('Europe/Bratislava');
	}

	/**
	 * Returns definition array for Slim App and all dependencies. To be used with DI container
	 */
	public static function getDefinitions(): array
	{
		return [
			AdvancedCallableResolverInterface::class => fn(ContainerInterface $container)
				=> new CallableResolver($container),

			HttpErrorHandler::class => function (ContainerInterface $container) {
		        $handler = new HttpErrorHandler(
					$container->get(AdvancedCallableResolverInterface::class),
					$container->get(ResponseFactoryInterface::class),
					$container->get(LoggerInterface::class),
				);
				$handler->setLogErrorRenderer(new ErrorRenderer());
				return $handler;
			},

			SlimApp::class => function (ContainerInterface $container) {
				// Instantiate the app
				$app = AppFactory::create(
					$container->get(ResponseFactoryInterface::class),
					$container,
					$container->get(AdvancedCallableResolverInterface::class),
					$container->get(RouteCollectorInterface::class),
					$container->get(RouteResolverInterface::class),
				);
				$app->add(new ReverseProxyMiddleware());
				$app->addBodyParsingMiddleware();
				$app->addRoutingMiddleware();

				$isProduction = getenv('ENV') !== 'development'; // todo, clean
				$displayErrorDetails = !$isProduction;

				$errorMiddleware = $app->addErrorMiddleware(
					$displayErrorDetails,
					true,
					true,
					$container->get(LoggerInterface::class),
				);
				$errorMiddleware->setDefaultErrorHandler($container->get(HttpErrorHandler::class));

				return $app;
			},

			ResponseFactoryInterface::class => fn(ContainerInterface $container) => new ResponseFactory(),

			RouteCollectorInterface::class => function (ContainerInterface $container) {
				$collector = new RouteCollector(
					$container->get(ResponseFactoryInterface::class),
					$container->get(AdvancedCallableResolverInterface::class),
					$container,
				);
				return $collector;
			},

			RouteResolverInterface::class => fn(ContainerInterface $container)
				=> new RouteResolver($container->get(RouteCollectorInterface::class)),

			ServerRequestCreatorInterface::class => fn()
				=> ServerRequestCreatorFactory::create(),

			ResponseEmitter::class => fn()
				// just to have our interface implemented
				=> new class extends ResponseEmitter implements ResponseEmitterInterface {},

			App::class => function(ContainerInterface $container) {
				return new App(
					$container->get(SlimApp::class),
					$container->get(ServerRequestCreatorInterface::class),
					$container->get(ResponseEmitter::class),
					$container->get(HttpErrorHandler::class),
				);
			}
		];
	}
}
