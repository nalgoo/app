# Nalgoo App

## Example usage with PHP DI

```php
use DI\ContainerBuilder;
use Nalgoo\App;
use Nalgoo\App\Builder;

// optional, highly recommended
Builder::setDefaults();

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(Builder::getDefinitions());

$container = $containerBuilder->build();

$app = $container->get(App::class);
$app->register($routes);
$app->run();
```
