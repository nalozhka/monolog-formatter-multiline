<?php

namespace Nalogka\Monolog\Formatter;

use Monolog\Formatter\LineFormatter as BaseLineFormatter;
use Monolog\Utils;

/**
 * Форматирует запись с выводом контекста и дополнительной информации
 * с отступом для удобного разбора.
 *
 * Первая строка записи начинается с метки времени, заключенной в
 * квадратые скобки. Многострочный текст сообщения распечатывается с отступом в
 * удобочтаемом виде. После сообщения так же с отступом распечатывается
 * структура контекста и дополнительные данные записи.
 */
class MultilineFormatter extends BaseLineFormatter
{
    const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message%";
    const DATE_FORMAT = 'Y-m-d\\TH:i:s.uP';
    const INDENT_STRING = " |  ";

    public function __construct()
    {
        parent::__construct(static::SIMPLE_FORMAT, static::DATE_FORMAT, false, true);
        $this->includeStacktraces();
        $this->allowInlineLineBreaks(true);
    }

    public function format(array $record)
    {
        $str = parent::format($record);

        $extra = [];
        if (!empty($record['context'])) {
            $extra['context'] = $record['context'];
        }
        if (!empty($record['extra'])) {
            $extra['extra'] = $record['extra'];
        }

        $extraStr = $extra ? "\n" . $this->stringifyIndented($this->normalize($extra), self::INDENT_STRING) : '';

        $formatted = $this->stringifyIndented($str, self::INDENT_STRING, '') . $extraStr;

        return trim($formatted, "\n") . "\n";
    }

    protected function normalizeException($e)
    {
        if (!$e instanceof \Exception && !$e instanceof \Throwable) {
            throw new \InvalidArgumentException(
                'Exception/Throwable expected, got ' . gettype($e) . ' / ' . Utils::getClass($e)
            );
        }

        $data = [
                'class' => Utils::getClass($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ] + $this->getPublicPropertiesException($e);

        if ($this->includeStacktraces) {
            $trace = $e->getTrace();
            $traceLen = count($trace);
            foreach ($trace as $idx => $frame) {
                $idx = sprintf('#%02u', $traceLen - $idx);
                if (isset($frame['file'])) {
                    $args = isset($frame['args']) ? $this->stringifyCallArgs($frame['args']) : '';
                    $call = (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . (!empty($frame['function']) ? $frame['function'] : '') . '(' . $args . ')';
                    $data['trace'][$idx] = $call . ' в ' . $frame['file'] . ':' . $frame['line'];
                } elseif (isset($frame['function']) && $frame['function'] === '{closure}') {
                    $data['trace'][$idx] = $frame['function'];
                } else {
                    // We should again normalize the frames, because it might contain invalid items
                    $data['trace'][$idx] = $this->normalize($frame);
                }
            }
        }

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous);
        }

        return $data;
    }

    protected function getPublicPropertiesException(\Throwable $exception): array
    {
        $class = new \ReflectionClass($exception);
        $publicProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($publicProperties as $property) {
            $results[$property->getName()] = $this->normalize($exception->{$property->getName()});
        }

        return $results ?? [];
    }

    private function stringifyCallArgs(array $args)
    {
        return implode(', ', array_map([$this, 'stringifyCallArg'], $args ?? []));
    }

    private function stringifyCallArg($argValue)
    {
        switch (gettype($argValue)) {
            case 'NULL':
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
            case 'array':
                return json_encode($argValue);
            case 'object':
                $json_encode = json_encode($argValue);

                return get_class($argValue) . '#' . spl_object_hash($argValue) . ' ' . $json_encode;
            case 'resource':
            case 'resource (closed)':
                return (string)$argValue . '(' . get_resource_type($argValue) . ')';
            default:
                return '?' . gettype($argValue) . '?';
        }
    }

    private function stringifyIndented($data, $indentation = '', $leadingIndentation = null)
    {
        if (null === $data || is_bool($data)) {
            $string = var_export($data, true);
        } elseif (is_scalar($data)) {
            $string = (string) $data;
        } elseif (is_array($data)) {
            $string = '';
            if (array_keys($data) === array_keys(array_values($data))) { // список
                $nestedIndent = $indentation . '    ';
                foreach ($data as $value) {
                    $string .= $indentation
                        . '- ' . substr($this->stringifyIndented($value, $nestedIndent), strlen($nestedIndent));
                }
            } else { // ассоциативный массив
                $nestedIndent = $indentation . '  ';
                foreach ($data as $key => $value) {
                    $strValue = $this->stringifyIndented($value, $nestedIndent);
                    if (false !== strpos(rtrim($strValue), "\n")) {
                        $string .= $indentation . $key . ":\n" . $strValue;
                    } else {
                        $string .= $indentation . $key . ': ' . substr($strValue, strlen($nestedIndent));
                    }
                }
            }

            return $string ? $string . "\n" : '';
        } else {
            $string = '[' . gettype($data) . ']';
        }

        return $this->indent($string, $indentation, $leadingIndentation) . "\n";
    }

    private function indent($string, $indentation, $leadingIndentation = null)
    {
        return (null === $leadingIndentation ? $indentation : $leadingIndentation)
            . str_replace(
                ["\r\n", "\n"],
                ["\n", "\n" . $indentation],
                trim($string)
            );
    }
}
