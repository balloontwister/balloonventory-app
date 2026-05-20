<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogTranslationParityTest extends TestCase
{
    public function test_en_and_es_catalog_translations_have_identical_keys(): void
    {
        $en = $this->flatten(require $this->root().'/lang/en/catalog.php');
        $es = $this->flatten(require $this->root().'/lang/es/catalog.php');

        sort($en);
        sort($es);

        $this->assertSame(
            $en,
            $es,
            'lang/en/catalog.php and lang/es/catalog.php must define the same keys.',
        );
    }

    public function test_catalog_sku_pages_reference_only_defined_translation_keys(): void
    {
        $defined = $this->flatten(require $this->root().'/lang/en/catalog.php');

        $pages = [
            'resources/js/Pages/SuperAdmin/Catalog/Index.vue',
            'resources/js/Pages/SuperAdmin/Catalog/SkuShow.vue',
            'resources/js/Pages/SuperAdmin/Catalog/SkuForm.vue',
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($this->root().'/'.$page);

            preg_match_all(
                '/(?:\$t|trans)\(\s*[\'"]catalog\.([a-z0-9_.]+)[\'"]/i',
                $contents,
                $matches,
            );

            foreach (array_unique($matches[1]) as $key) {
                $this->assertContains(
                    $key,
                    $defined,
                    "{$page} references catalog.{$key} which is missing from lang/en/catalog.php.",
                );
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Flatten a nested translation array into dot-notation keys.
     *
     * @param  array<string, mixed>  $array
     * @return array<int, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }
}
