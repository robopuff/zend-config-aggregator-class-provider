# Usage

```php
use Robopuff\ConfigAggregator\ClassProvider\ClassDiscoveryProvider;
use Zend\ConfigAggregator\ConfigAggregator;

$aggregator = new ConfigAggregator([
    new ClassDiscoveryProvider(__DIR__ . '/src/{Dir1,Dir2}/*/ConfigProvider.php'),
]);

return $aggregator->getMergedConfig();
```

## Modes

By default `ClassDiscoveryProvider` will be working using _PREG_ method of discovering fully qualified class name,
this can be changed to:

* `ClassDiscoveryProvider::METHOD_PREG` - **Default**, reads file line by line
* `ClassDiscoveryProvider::METHOD_TOKENS` - Reads whole file at once and then uses built-in php tokenizer
* `ClassDiscoveryProvider::METHOD_PATH` - tries to determinate a FQCN based on it's path (PSR-4), it has a few options:
    * `baseSrc` - removes string from path
    * `prefix` - adds this as a prefix to a namespace
    * `extension` - by default it will get each file extension and it'll be removed but you can specify it manually

## Setting options

**Be aware** that options set in class constructor extend settings set as default.

### Global

```php
use Robopuff\ConfigAggregator\ClassProvider\ClassDiscoveryProvider;
use Zend\ConfigAggregator\ConfigAggregator;

// Set up default for each instance of class
ClassDiscoveryProvider::setDefaultOptions([
    'mode' => ClassDiscoveryProvider::METHOD_PATH
])

$aggregator = new ConfigAggregator([
    new ClassDiscoveryProvider(__DIR__ . '/src/*/ConfigProvider.php', [
        'baseSrc' => __DIR__ . '/src',
        'prefix' => 'Random\\Namespace\\'
    ]),
]);

return $aggregator->getMergedConfig();
```

### Per instance

```php
use Robopuff\ConfigAggregator\ClassProvider\ClassDiscoveryProvider;
use Zend\ConfigAggregator\ConfigAggregator;

$aggregator = new ConfigAggregator([
    new ClassDiscoveryProvider('src/*/ConfigProvider.php', [
        'mode' => ClassDiscoveryProvider::METHOD_PATH,
        'baseSrc' => 'src',
        'prefix' => 'Random\\Namespace\\'
    ]),
]);

return $aggregator->getMergedConfig();
```