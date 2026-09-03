<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TestQualityAnalyzer\Configuration;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsReturnExpectedValues(): void
    {
        $config = Configuration::defaults();

        self::assertSame(40, $config->getLongTestThreshold());
        self::assertSame(MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES, $config->getMagicNumberAllowlist());
        self::assertNull($config->getEnabledDetectors());
        self::assertSame([], $config->getDisabledDetectors());
        self::assertSame('.tq-baseline.json', $config->getBaselinePath());
    }

    public function testFromFileLoadsLongTestThreshold(): void
    {
        $file = $this->writeTempYaml("thresholds:\n  long_test: 20\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame(20, $config->getLongTestThreshold());
        } finally {
            unlink($file);
        }
    }

    public function testMagicNumberAllowlistReplacesDefault(): void
    {
        $file = $this->writeTempYaml("thresholds:\n  magic_number_allowlist: [0, 1, 42]\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame([0, 1, 42], $config->getMagicNumberAllowlist());
        } finally {
            unlink($file);
        }
    }

    public function testMagicNumberAllowlistExtraAppendsToDefault(): void
    {
        $file = $this->writeTempYaml("thresholds:\n  magic_number_allowlist_extra: [42, 99]\n");

        try {
            $config = Configuration::fromFile($file);
            $allowlist = $config->getMagicNumberAllowlist();
            // Base defaults are all present
            foreach (MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES as $value) {
                self::assertContains($value, $allowlist);
            }
            // Extras are appended
            self::assertContains(42, $allowlist);
            self::assertContains(99, $allowlist);
            // Total count: defaults + 2 extras (42 and 99 are not in defaults)
            $expectedCount = count(MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES) + 2;
            self::assertCount($expectedCount, $allowlist);
        } finally {
            unlink($file);
        }
    }

    public function testMagicNumberAllowlistWithExtraCombines(): void
    {
        $file = $this->writeTempYaml(
            "thresholds:\n  magic_number_allowlist: [0, 1]\n  magic_number_allowlist_extra: [42]\n"
        );

        try {
            $config = Configuration::fromFile($file);
            self::assertSame([0, 1, 42], $config->getMagicNumberAllowlist());
        } finally {
            unlink($file);
        }
    }

    public function testMergeOverridesScalars(): void
    {
        $base = Configuration::defaults();
        $override = Configuration::fromFile($this->writeTempYaml("thresholds:\n  long_test: 10\n"));

        $merged = $base->merge($override);

        self::assertSame(10, $merged->getLongTestThreshold());
    }

    public function testMergeReplacesAllowlist(): void
    {
        $base = Configuration::defaults();
        $overrideFile = $this->writeTempYaml("thresholds:\n  magic_number_allowlist: [7, 8, 9]\n");

        try {
            $override = Configuration::fromFile($overrideFile);
            $merged = $base->merge($override);
            self::assertSame([7, 8, 9], $merged->getMagicNumberAllowlist());
        } finally {
            unlink($overrideFile);
        }
    }

    public function testMergeUnionsAllowlistExtra(): void
    {
        $baseFile = $this->writeTempYaml("thresholds:\n  magic_number_allowlist_extra: [42]\n");
        $overrideFile = $this->writeTempYaml("thresholds:\n  magic_number_allowlist_extra: [99]\n");

        try {
            $base = Configuration::fromFile($baseFile);
            $override = Configuration::fromFile($overrideFile);
            $merged = $base->merge($override);
            $allowlist = $merged->getMagicNumberAllowlist();
            self::assertContains(42, $allowlist);
            self::assertContains(99, $allowlist);
        } finally {
            unlink($baseFile);
            unlink($overrideFile);
        }
    }

    public function testMergeUnionsDisabledDetectors(): void
    {
        $baseFile = $this->writeTempYaml("detectors:\n  disabled: [no_assertions]\n");
        $overrideFile = $this->writeTempYaml("detectors:\n  disabled: [long_test]\n");

        try {
            $base = Configuration::fromFile($baseFile);
            $override = Configuration::fromFile($overrideFile);
            $merged = $base->merge($override);
            self::assertContains('no_assertions', $merged->getDisabledDetectors());
            self::assertContains('long_test', $merged->getDisabledDetectors());
        } finally {
            unlink($baseFile);
            unlink($overrideFile);
        }
    }

    public function testDetectorsEnabledList(): void
    {
        $file = $this->writeTempYaml("detectors:\n  enabled: [no_assertions, long_test]\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame(['no_assertions', 'long_test'], $config->getEnabledDetectors());
        } finally {
            unlink($file);
        }
    }

    public function testDetectorsDisabledList(): void
    {
        $file = $this->writeTempYaml("detectors:\n  disabled: [conditional_test_logic]\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame(['conditional_test_logic'], $config->getDisabledDetectors());
        } finally {
            unlink($file);
        }
    }

    public function testEnabledAllTreatedAsEnableAllDetectors(): void
    {
        $file = $this->writeTempYaml("detectors:\n  enabled: all\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertNull($config->getEnabledDetectors());
        } finally {
            unlink($file);
        }
    }

    public function testDisabledAllTreatedAsDisableAllDetectors(): void
    {
        $file = $this->writeTempYaml("detectors:\n  disabled: all\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame([], $config->getDisabledDetectors());
        } finally {
            unlink($file);
        }
    }

    public function testInvalidThresholdThrows(): void
    {
        $file = $this->writeTempYaml("thresholds:\n  long_test: -1\n");

        try {
            $this->expectException(InvalidArgumentException::class);
            Configuration::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testInvalidYamlThrows(): void
    {
        $file = $this->writeTempYaml("thresholds:\n  bad: [\n");

        try {
            $this->expectException(RuntimeException::class);
            Configuration::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Configuration::fromFile('/nonexistent/path/config.yaml');
    }

    public function testBaselinePathFromConfig(): void
    {
        $file = $this->writeTempYaml("baseline: custom-baseline.json\n");

        try {
            $config = Configuration::fromFile($file);
            self::assertSame('custom-baseline.json', $config->getBaselinePath());
        } finally {
            unlink($file);
        }
    }

    private function writeTempYaml(string $content): string
    {
        $file = sys_get_temp_dir() . '/tq-config-' . uniqid() . '.yaml';
        file_put_contents($file, $content);
        return $file;
    }
}
