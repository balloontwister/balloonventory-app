<?php

namespace Tests\Unit;

use App\Support\BusinessContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BusinessContextTest extends TestCase
{
    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_run_for_sets_context_inside_and_restores_after(): void
    {
        BusinessContext::set('biz-a');

        $result = BusinessContext::runFor('biz-b', function () {
            $this->assertSame('biz-b', BusinessContext::currentId());

            return 'returned';
        });

        $this->assertSame('returned', $result);
        $this->assertSame('biz-a', BusinessContext::currentId());
    }

    public function test_run_for_restores_previous_context_when_callback_throws(): void
    {
        BusinessContext::set('biz-a');

        try {
            BusinessContext::runFor('biz-b', function () {
                throw new RuntimeException('boom');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('biz-a', BusinessContext::currentId());
    }

    public function test_run_for_restores_null_context(): void
    {
        BusinessContext::clear();

        BusinessContext::runFor('biz-b', fn () => null);

        $this->assertNull(BusinessContext::currentId());
    }
}
