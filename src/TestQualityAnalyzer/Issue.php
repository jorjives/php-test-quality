<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

final readonly class Issue
{
    public function __construct(
        public string $file,
        public string $testName,
        public string $type,
        public string $message,
    ) {}

    /** @return array{file: string, test: string, type: string, message: string} */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'test' => $this->testName,
            'type' => $this->type,
            'message' => $this->message,
        ];
    }
}
