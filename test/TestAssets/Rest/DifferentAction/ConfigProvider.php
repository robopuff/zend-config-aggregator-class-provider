<?php

/*
namespace InvalidNamespaceThatShouldNotBeIncluded;
 */
       	namespace RobopuffTest\ConfigAggregator\ClassProvider\TestAssets\Rest\DifferentAction;

/*
class InvalidClassNameThatShouldNotBeIncluded *\/
 */

class ConfigProvider
{
    public function __invoke(): array
    {
        return ['ConfigProviderCorrectResponse'];
    }
}
