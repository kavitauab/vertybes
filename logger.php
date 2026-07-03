<?php
/**
 * Minimal file logger — writes JSON lines to logs/app.log.
 */

class Logger {
    private $dir;

    public function __construct() {
        $this->dir = __DIR__ . '/logs';
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);
    }

    private function write($level, $message, array $context = [], $channel = 'app') {
        $line = json_encode([
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context ?: null,
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents($this->dir . "/$channel.log", $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public function info($msg, array $ctx = [], $channel = 'app')     { $this->write('info', $msg, $ctx, $channel); }
    public function warning($msg, array $ctx = [], $channel = 'app')  { $this->write('warning', $msg, $ctx, $channel); }
    public function error($msg, array $ctx = [], $channel = 'app')    { $this->write('error', $msg, $ctx, $channel); }
    public function critical($msg, array $ctx = [], $channel = 'app') { $this->write('critical', $msg, $ctx, $channel); }
}

function getLogger() {
    static $logger = null;
    if ($logger === null) $logger = new Logger();
    return $logger;
}
