<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;

final class Configuration
{
    /**
     * @param array<int|float> $magicNumberAllowlist
     * @param array<int|float> $magicNumberAllowlistExtra
     * @param array<string>|null $enabledDetectors
     * @param array<string> $disabledDetectors
     */
    private function __construct(
        private readonly int $longTestThreshold,
        private readonly array $magicNumberAllowlist,
        private readonly array $magicNumberAllowlistExtra,
        private readonly ?array $enabledDetectors,
        private readonly array $disabledDetectors,
        private readonly string $baselinePath,
    ) {}

    public static function defaults(): self
    {
        return new self(
            longTestThreshold: 40,
            magicNumberAllowlist: MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES,
            magicNumberAllowlistExtra: [],
            enabledDetectors: null,
            disabledDetectors: [],
            baselinePath: '.tq-baseline.json',
        );
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: {$path}");
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new RuntimeException("Failed to parse configuration file: {$e->getMessage()}", 0, $e);
        }

        $data = $data ?? [];
        $thresholds = $data['thresholds'] ?? [];
        $detectors = $data['detectors'] ?? [];

        $longTestThreshold = isset($thresholds['long_test'])
            ? (int) $thresholds['long_test']
            : 40;

        if ($longTestThreshold <= 0) {
            throw new InvalidArgumentException(
                "Invalid long_test threshold: must be greater than 0, got {$longTestThreshold}"
            );
        }

        $allowlist = isset($thresholds['magic_number_allowlist'])
            ? array_values($thresholds['magic_number_allowlist'])
            : MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES;

        $allowlistExtra = isset($thresholds['magic_number_allowlist_extra'])
            ? array_values($thresholds['magic_number_allowlist_extra'])
            : [];

        $enabledDetectors = isset($detectors['enabled']) && is_array($detectors['enabled'])
            ? array_values($detectors['enabled'])
            : null;

        $disabledDetectors = isset($detectors['disabled']) && is_array($detectors['disabled'])
            ? array_values($detectors['disabled'])
            : [];

        $baselinePath = $data['baseline'] ?? '.tq-baseline.json';

        return new self(
            longTestThreshold: $longTestThreshold,
            magicNumberAllowlist: $allowlist,
            magicNumberAllowlistExtra: $allowlistExtra,
            enabledDetectors: $enabledDetectors,
            disabledDetectors: $disabledDetectors,
            baselinePath: $baselinePath,
        );
    }

    /**
     * Merge this configuration with an override. The override wins for scalars,
     * lists are unioned where appropriate.
     */
    public function merge(self $override): self
    {
        $defaults = self::defaults();

        // Scalar: override wins if it differs from defaults (i.e. was explicitly set)
        $longTestThreshold = $override->longTestThreshold !== $defaults->longTestThreshold
            ? $override->longTestThreshold
            : $this->longTestThreshold;

        $baselinePath = $override->baselinePath !== $defaults->baselinePath
            ? $override->baselinePath
            : $this->baselinePath;

        // Allowlist: override replaces if it differs from the default
        $allowlist = $override->magicNumberAllowlist !== $defaults->magicNumberAllowlist
            ? $override->magicNumberAllowlist
            : $this->magicNumberAllowlist;

        // Extra allowlist: union (strict dedup to preserve float identity)
        $allowlistExtra = $this->magicNumberAllowlistExtra;
        foreach ($override->magicNumberAllowlistExtra as $value) {
            if (!in_array($value, $allowlistExtra, true)) {
                $allowlistExtra[] = $value;
            }
        }
        $allowlistExtra = array_values($allowlistExtra);

        // Enabled detectors: override replaces if set
        $enabledDetectors = $override->enabledDetectors !== null
            ? $override->enabledDetectors
            : $this->enabledDetectors;

        // Disabled detectors: union
        $disabledDetectors = array_values(array_unique(array_merge(
            $this->disabledDetectors,
            $override->disabledDetectors,
        )));

        return new self(
            longTestThreshold: $longTestThreshold,
            magicNumberAllowlist: $allowlist,
            magicNumberAllowlistExtra: $allowlistExtra,
            enabledDetectors: $enabledDetectors,
            disabledDetectors: $disabledDetectors,
            baselinePath: $baselinePath,
        );
    }

    public function getLongTestThreshold(): int
    {
        return $this->longTestThreshold;
    }

    /** @return array<int|float> */
    public function getMagicNumberAllowlist(): array
    {
        if ($this->magicNumberAllowlistExtra === []) {
            return $this->magicNumberAllowlist;
        }

        // Merge extras, deduplicating by strict value comparison to avoid
        // dropping floats that compare loosely equal to ints (e.g. 0.0 vs 0).
        $result = $this->magicNumberAllowlist;
        foreach ($this->magicNumberAllowlistExtra as $value) {
            if (!in_array($value, $result, true)) {
                $result[] = $value;
            }
        }
        return array_values($result);
    }

    /** @return array<string>|null */
    public function getEnabledDetectors(): ?array
    {
        return $this->enabledDetectors;
    }

    /** @return array<string> */
    public function getDisabledDetectors(): array
    {
        return $this->disabledDetectors;
    }

    public function getBaselinePath(): string
    {
        return $this->baselinePath;
    }
}
