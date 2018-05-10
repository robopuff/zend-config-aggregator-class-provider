<?php

namespace RobopuffTest\ConfigAggregator\ClassProvider;

use PHPUnit\Framework\TestCase;
use Robopuff\ConfigAggregator\ClassProvider\Exception;
use Robopuff\ConfigAggregator\ClassProvider\ClassDiscoveryProvider;

class ClassDiscoveryProviderTest extends TestCase
{
    public function setUp()
    {
        // Reset options to default
        ClassDiscoveryProvider::setDefaultOptions([]);
    }

    public function testSetDefaultOptions()
    {
        $default = [
            'method' => ClassDiscoveryProvider::METHOD_PATH,
            'prefix' => 'src',
            'random' => 'value'
        ];
        ClassDiscoveryProvider::setDefaultOptions($default);
        $stub = new ClassDiscoveryProvider('');

        $options = (function () {
            return $this->options;
        })->call($stub);

        $this->assertEquals($default, $options);
    }

    public function testSetDefaultOptionsWithEmptyArray()
    {
        ClassDiscoveryProvider::setDefaultOptions([]);
        $stub = new ClassDiscoveryProvider('');

        $options = (function () {
            return $this->options;
        })->call($stub);

        $this->assertEquals([
            'method' => ClassDiscoveryProvider::METHOD_PREG
        ], $options);
    }

    public function testInvalidMethod()
    {
        $glob = __DIR__ . '/TestAssets/{EmptyDir,NoConfig,Rest,Rpc}/*/ConfigProvider.php';
        $stub = new ClassDiscoveryProvider($glob, [
            'method' => 999
        ]);

        $this->expectException(Exception\BadMethodCallException::class);
        $stub()->next();
    }

    public function testPregMethod()
    {
        // Load class without namespace
        require __DIR__ . '/TestAssets/Rpc/Action/ConfigProvider.php';

        $glob = __DIR__ . '/TestAssets/{EmptyDir,NoConfig,Rest,Rpc}/*/ConfigProvider.php';
        $stub = new ClassDiscoveryProvider($glob, [
            'method' => ClassDiscoveryProvider::METHOD_PREG
        ]);

        $countResponses = 0;
        foreach ($stub() as $generator) {
            $this->assertEquals(['ConfigProviderCorrectResponse'], $generator);
            $countResponses++;
        }

        $this->assertEquals(3, $countResponses);
    }

    public function testTokenMethod()
    {
        $glob = __DIR__ . '/TestAssets/{EmptyDir,NoConfig,Rest,Rpc}/*/ConfigProvider.php';
        $stub = new ClassDiscoveryProvider($glob, [
            'method' => ClassDiscoveryProvider::METHOD_TOKENS
        ]);

        $countResponses = 0;
        foreach ($stub() as $generator) {
            $this->assertEquals(['ConfigProviderCorrectResponse'], $generator);
            $countResponses++;
        }

        $this->assertEquals(3, $countResponses);
    }

    public function testPathMethod()
    {
        $glob = __DIR__ . '/TestAssets/{EmptyDir,NoConfig,Rest}/*/ConfigProvider.php';
        $stub = new ClassDiscoveryProvider($glob, [
            'method' => ClassDiscoveryProvider::METHOD_PATH,
            'baseSrc' => __DIR__,
            'prefix' => 'RobopuffTest\\ConfigAggregator\\ClassProvider\\'
        ]);

        $countResponses = 0;
        foreach ($stub() as $generator) {
            $this->assertEquals(['ConfigProviderCorrectResponse'], $generator);
            $countResponses++;
        }

        $this->assertEquals(1, $countResponses);
    }

    public function testInvokeWithInvalidClass()
    {
        $stub = new ClassDiscoveryProvider(__DIR__ . '/TestAssets/Rest/*/RestDifferentActionResource.php');

        $this->expectException(Exception\InvalidFileException::class);
        $stub()->next();
    }

    public function testInvokeWithAmbiguousFile()
    {
        $stub = new ClassDiscoveryProvider(__DIR__ . '/TestAssets/Rest/*/ConfigProvider.cs.php');

        $this->expectException(Exception\ClassNameAmbiguousException::class);
        $stub()->next();
    }

    public function testInvokeWithInvalidFile()
    {
        $stub = new ClassDiscoveryProvider(__DIR__ . '/TestAssets/Rest/*/CoolFileName.php.cs');

        $this->expectException(Exception\InvalidFileException::class);
        $stub()->next();
    }
}
