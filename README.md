# zend-config-aggregator-class-provider

[![Build Status](https://travis-ci.org/robopuff/zend-config-aggregator-class-provider.svg?branch=master)](https://travis-ci.org/robopuff/zend-config-aggregator-class-provider)
[![Coverage Status](https://coveralls.io/repos/github/robopuff/zend-config-aggregator-class-provider/badge.svg?branch=master)](https://coveralls.io/github/robopuff/zend-config-aggregator-class-provider?branch=master)

Provides an extension to the  `zendframework/zend-config-aggregator` to allow config class auto discovery based
on glob pattern provided.

```bash
$ composer require robopuff/zend-config-aggregator-class-provider
```

## Usage

```php
use Robopuff\ConfigAggregator\ClassProvider\ClassDiscoveryProvider;
use Zend\ConfigAggregator\ConfigAggregator;

$aggregator = new ConfigAggregator([
    new ClassDiscoveryProvider(__DIR__ . '/src/{Dir1,Dir2}/*/ConfigProvider.php'),
]);

return $aggregator->getMergedConfig();
```

For more details, please refer to the [documentation]().
---
- [Issues](https://github.com/robopuff/zend-config-aggregator-class-provider/issues)
- [Documentation](https://robopuff.github.io/zend-config-aggregator-class-provider) 