<?php

declare(strict_types=1);

namespace Robopuff\ConfigAggregator\ClassProvider;

use Zend\ConfigAggregator\GlobTrait;

class ClassDiscoveryProvider
{
    use GlobTrait;

    /**
     * Find fully qualified class name using preg (reads file line by line)
     */
    public const METHOD_PREG = 0x01;

    /**
     * Find fully qualified class name using php tokens (loads whole file into memory)
     */
    public const METHOD_TOKENS = 0x02;

    /**
     * Find fully qualified class name using file path (you can specify `baseSrc`, `prefix` and `extension`
     */
    public const METHOD_PATH = 0x04;

    /**
     * A namespace matching regex
     */
    private const REGEX_NAMESPACE = '/^\s*namespace\s+(?<namespace>[a-z_][a-z0-9\\\_]*)(?:[\s;{])*$/i';

    /**
     * A class matching regex
     */
    private const REGEX_CLASS = '/^(?:final\s+)?class\s+(?<class>[a-z_]\w+)/i';

    /**
     * @var array
     */
    private static $defaultOptions = [
        'method' => self::METHOD_PREG
    ];

    /**3
     * @var string|string[]
     */
    private $pattern;

    /**
     * @var array
     */
    private $options;

    /**
     * Set default options
     * @param array $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = $options + [ //@codeCoverageIgnore
            'method' => self::METHOD_PREG
        ];
    }

    /**
     * Class discovery provider constructor
     * @param string|string[] $pattern
     * @param array $options
     * @throws Exception\BadMethodCallException
     */
    public function __construct($pattern, array $options = [])
    {
        if (\is_array($pattern)) {
            $pattern = sprintf('{%s}', implode(',', $pattern));
        }

        if (!\is_string($pattern)) {
            throw new Exception\BadMethodCallException(sprintf(
                'Pattern must be string or array of strings, %s given',
                \is_object($pattern) ? \get_class($pattern) : gettype($pattern)
            ));
        }

        $this->pattern = $pattern;
        $this->options = array_merge(self::$defaultOptions, $options);
    }

    /**
     * Find classes based on pattern
     * @throws Exception\BadMethodCallException
     * @throws Exception\ClassNameAmbiguousException
     * @throws Exception\InvalidFileException
     */
    public function __invoke(): \Generator
    {
        switch ($this->options['method'] ?? null) {
            case self::METHOD_TOKENS:
                $parser = $this->getParseTokenCallable();
                break;
            case self::METHOD_PREG:
                $parser = $this->getParsePregCallable();
                break;
            case self::METHOD_PATH:
                $parser = $this->getParsePathCallable();
                break;
            default:
                throw new Exception\BadMethodCallException('Invalid parse method selected');
        }

        foreach ($this->glob($this->pattern) as $file) {
            $fqcn = $parser($file);
            if (!$fqcn || !\class_exists($fqcn)) {
                throw new Exception\ClassNameAmbiguousException(
                    "Determined FQCN `{$fqcn}` does not seem to be correct for file `{$file}`"
                );
            }

            $instance = new $fqcn;
            if (!\is_callable($instance)) {
                throw new Exception\InvalidFileException("Class `{$fqcn}` does not seem to be callable");
            }

            yield $instance();
        }
    }

    /**
     * Get FQCN by parsing file path (PSR-0, PSR-4)
     */
    private function getParsePathCallable(): callable
    {
        return function (string $file) : string {
            $baseSrc = $this->options['baseSrc'] ?? false;
            $prefix = $this->options['prefix'] ?? null;
            $extension = $this->options['extension'] ?? pathinfo($file, PATHINFO_EXTENSION);

            if ($baseSrc && \substr($baseSrc, -1) !== DIRECTORY_SEPARATOR) {
                $baseSrc .= DIRECTORY_SEPARATOR;
            }

            if ($extension[0] !== '.') {
                $extension = '.' . $extension;
            }

            return $prefix . str_replace([$extension, $baseSrc, DIRECTORY_SEPARATOR], ['', '', '\\'], $file);
        };
    }

    /**
     * Get FQCN by parsing file with php tokens (loads whole file into memory)
     * @throws Exception\InvalidFileException
     */
    private function getParseTokenCallable(): callable
    {
        return function (string $file) : string {
            $fileContent = file_get_contents($file);

            //@codeCoverageIgnoreStart
            if (false === $fileContent) {
                throw new Exception\InvalidFileException(
                    "Cannot read `{$file}`"
                );
            }
            //@codeCoverageIgnoreEnd

            $tokens = token_get_all($fileContent);
            $tokenStart = $class = $namespace = null;
            foreach ($tokens as $i => $token) {
                if (!\is_array($token)) {
                    $tokenStart = $tokenStart ? false : $tokenStart;
                    continue;
                }

                [$index, $line] = $token;
                if ($index === T_WHITESPACE) {
                    continue;
                }

                if ($tokenStart) {
                    $namespace .= $line;
                    continue;
                }

                if ($index === T_NAMESPACE) {
                    $tokenStart = $index;
                }

                if ($index === T_CLASS) { // n+1 is whitespace, and n+2 is actual class name
                    $class = $tokens[$i + 2][1];
                    break;
                }
            }

            if (!$class) {
                throw new Exception\InvalidFileException(
                    "Cannot determinate class name using file `{$file}` and method `token`"
                );
            }

            return "$namespace\\$class";
        };
    }

    /**
     * Get FQCN by parsing file with regular expression (loads file line by line)
     * @throws Exception\InvalidFileException
     */
    private function getParsePregCallable(): callable
    {
        return function (string $file) : string {
            $fp = fopen($file, 'rb');

            //@codeCoverageIgnoreStart
            if (false === $fp) {
                throw new Exception\InvalidFileException(
                    "Cannot read `{$file}`"
                );
            }
            //@codeCoverageIgnoreEnd

            $inComment = $namespace = $class = null;
            while ((!$class || !$namespace) && ($line = fgets($fp)) !== false) {
                if (false === $line) {
                    break;
                }

                $commentEnd = strpos($line, '*/') !== false;
                if ($inComment) {
                    $inComment = !$commentEnd;
                    continue;
                }

                if (!$namespace && preg_match(self::REGEX_NAMESPACE, $line, $nsMatch) === 1) {
                    $namespace = $nsMatch['namespace'];
                }

                if (!$class && preg_match(self::REGEX_CLASS, $line, $cnMatch) === 1) {
                    $class = $cnMatch['class'];
                }

                if (!$commentEnd && strpos($line, '/*') !== false) {
                    $inComment = true;
                }
            }

            if (!$class) {
                throw new Exception\InvalidFileException(
                    "Cannot determinate class name using file `{$file}` and method `preg`"
                );
            }

            return "$namespace\\$class";
        };
    }
}
