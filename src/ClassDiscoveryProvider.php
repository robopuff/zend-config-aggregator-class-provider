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
     * @var string
     */
    private $pattern;

    /**
     * @var array
     */
    private $options;

    /**
     * Set default options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = $options + [
            'method' => self::METHOD_PREG
        ];
    }

    /**
     * Class discovery provider constructor
     */
    public function __construct(string $pattern, array $options = [])
    {
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
        foreach ($this->glob($this->pattern) as $file) {
            switch ($this->options['method'] ?? null) {
                case self::METHOD_TOKENS:
                    $fqcn = $this->parseToken($file);
                    break;
                case self::METHOD_PREG:
                    $fqcn = $this->parsePreg($file);
                    break;
                case self::METHOD_PATH:
                    $fqcn = $this->parsePath($file);
                    break;
                default:
                    throw new Exception\BadMethodCallException("Invalid parse method selected");
            }

            if (!$fqcn || !class_exists($fqcn)) {
                throw new Exception\ClassNameAmbiguousException(
                    "Determined FQCN `{$fqcn}` does not seem to be correct for file `{$file}`"
                );
            }

            $instance = new $fqcn;
            if (!is_callable($instance)) {
                throw new Exception\InvalidFileException("Class `{$fqcn}` does not seem to be callable");
            }

            yield $instance();
        }
    }

    /**
     * Get FQCN by parsing file path (PSR-0, PSR-4)
     */
    private function parsePath(string $file): string
    {
        $baseSrc = $this->options['baseSrc'] ?? false;
        $prefix = $this->options['prefix'] ?? null;
        $extension = $this->options['extension'] ?? pathinfo($file, PATHINFO_EXTENSION);

        if ($baseSrc && substr($baseSrc, -1) != DIRECTORY_SEPARATOR) {
            $baseSrc .= DIRECTORY_SEPARATOR;
        }

        if (substr($extension, 0, 1) != '.') {
            $extension = '.' . $extension;
        }

        return $prefix . str_replace([$extension, $baseSrc, DIRECTORY_SEPARATOR], ['', '','\\'], $file);
    }

    /**
     * Get FQCN by parsing file with php tokens (loads whole file into memory)
     * @throws Exception\InvalidFileException
     */
    private function parseToken(string $file): string
    {
        $tokens = token_get_all(file_get_contents($file));
        $tokenStart = $class = $namespace = null;
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                $tokenStart = $tokenStart ? false : $tokenStart;
                continue;
            }

            [$index, $line] = $token;
            if ($index == T_WHITESPACE) {
                continue;
            }

            if ($tokenStart) {
                $namespace .= $line;
                continue;
            }

            if ($index == T_NAMESPACE) {
                $tokenStart = $index;
            }

            if ($index == T_CLASS) { // n+1 is whitespace, and n+2 is actual class name
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
    }

    /**
     * Get FQCN by parsing file with regular expression (loads file line by line)
     * @throws Exception\InvalidFileException
     */
    private function parsePreg(string $file): string
    {
        $fp = fopen($file, 'rb');

        $inComment = $namespace = $class = null;
        while ((!$class || !$namespace) && ($line = fgets($fp)) !== false) {
            $commentEnd = strpos($line, '*/') !== false;
            if ($inComment) {
                $inComment = !$commentEnd;
                continue;
            }

            if (!$namespace && preg_match(self::REGEX_NAMESPACE, (string) $line, $nsMatch) === 1) {
                $namespace = $nsMatch['namespace'];
            }

            if (!$class && preg_match(self::REGEX_CLASS, (string) $line, $cnMatch) === 1) {
                $class = $cnMatch['class'];
            }

            if (strpos($line, '/*') !== false && !$commentEnd) {
                $inComment = true;
            }
        }

        if (!$class) {
            throw new Exception\InvalidFileException(
                "Cannot determinate class name using file `{$file}` and method `preg`"
            );
        }

        return "$namespace\\$class";
    }
}
