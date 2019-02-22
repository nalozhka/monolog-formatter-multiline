<?php

namespace Nalogka\Monolog\Formatter;

use Monolog\Formatter\LineFormatter as BaseLineFormatter;

/**
 * Форматирует запись с выводом контекста и дополнительной информации
 * с отступом для удобного разбора.
 * 
 * Первая строка записи начинается с метки времени, заключенной в
 * квадратые скобки, а многострочный текст сообщения вытягивается
 * в одну строку. Со второй строки с отступом распечатывается в
 * удобочтаемом виде структура контекста и ниже дополнительные
 * данные записи.
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
        $this->allowInlineLineBreaks(false);
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

        return $str . $extraStr . "\n";
    }

    private function stringifyIndented($data, $indentation = '')
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
                    $string .= $indentation . $key . ":\n" . $this->stringifyIndented($value, $nestedIndent);
                }
            }

            return $string ? $string . "\n" : '';
        } else {
            $string = '[' . gettype($data) . ']';
        }

        return $this->indent($string, $indentation) . "\n";
    }

    private function indent($string, $indentation)
    {
        return $indentation . str_replace(["\r\n", "\n"], ["\n", "\n" . $indentation], trim($string));
    }
}
