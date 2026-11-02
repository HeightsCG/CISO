<?php
class Assert {

    public static function true($condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function same($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message.' (expected '.json_encode($expected).', got '.json_encode($actual).')');
        }
    }

}
