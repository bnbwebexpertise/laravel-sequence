<?php
/**
 * laravel
 *
 * @author    Jérémy GAULIN <jeremy@bnb.re>
 * @copyright 2017 - B&B Web Expertise
 */

namespace Bnb\Laravel\Sequence\Tests;

use Bnb\Laravel\Sequence\Exceptions\SequenceOutOfRangeException;
use Bnb\Laravel\Sequence\Tests\Models\Foo;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use ReflectionClass;
use Tests\TestCase;

class SequenceTest extends TestCase
{

    use DatabaseMigrations;


    private function formatSequence($date, $next)
    {
        return sprintf('%1$04d%2$06d', $date, $next);
    }


    protected function setUp(): void
    {
        if ( ! $this->app) {
            $this->refreshApplication();
        }

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(__DIR__ . '/migrations');
        });

        parent::setUp();
    }


    public function test_it_initialize_sequence()
    {
        Foo::truncate();

        $foo = Foo::create(['bar' => 1])->fresh();

        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START), $foo->sequence);
    }


    public function test_it_increments_sequence()
    {
        $foo1 = Foo::create(['bar' => 1])->fresh();
        $foo2 = Foo::create(['bar' => 2])->fresh();

        $this->assertGreaterThan(0, $foo1->id);
        $this->assertGreaterThan(0, $foo2->id);
        $this->assertGreaterThan($foo1->id, $foo2->id);
        $this->assertGreaterThan($foo1->sequence, $foo2->sequence);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START), $foo1->sequence);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START + 1), $foo2->sequence);
    }


    public function test_it_does_not_override_sequence()
    {
        $foo = Foo::create(['bar' => 1])->fresh();
        $foo->bar = 2;
        $foo->save();

        $foo = $foo->fresh();

        $this->assertEquals(2, $foo->bar);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START), $foo->sequence);
    }


    public function test_it_fails_when_out_of_range()
    {
        Foo::create(['bar' => 1])->fresh();
        Foo::create(['bar' => 2])->fresh();
        Foo::create(['bar' => 3])->fresh();

        $this->expectException(SequenceOutOfRangeException::class);

        Foo::create(['bar' => 4])->fresh();
    }


    public function test_it_fires_model_event()
    {
        Foo::sequenceGenerated('sequence', function ($model) {
            $model->bar = 'event';
            $model->save();
        });

        $foo = Foo::create(['bar' => 'bar']);

        $this->assertEquals('bar', $foo->bar);

        $foo = $foo->fresh();

        $this->assertEquals('event', $foo->bar);
    }


    public function test_it_does_not_generate_read_only_sequence()
    {
        $foo = Foo::create(['bar' => 'bar'])->fresh();

        $this->assertNull($foo->read_only);

        $foo->bar = 'yes';
        $foo->save();

        $foo = $foo->fresh();

        $this->assertEquals(1, $foo->read_only);
    }


    /*
     * Ensure documented example works
     */
    public function test_year_change()
    {
        $last = sprintf('%1$04d%2$06d', date('Y') - 1, 10);
        $next = sprintf('%1$04d%2$06d', date('Y') - 1, 11);

        $class = new ReflectionClass(Foo::class);
        $method = $class->getMethod('formatSequenceSequence');
        $method->setAccessible(true);

        $next = $method->invokeArgs(new Foo, [$next, $last]);

        $this->assertEquals(sprintf('%1$04d%2$06d', date('Y'), Foo::SEQ_SEQUENCE_START), $next);
    }


    public function test_it_fills_gap()
    {
        $foo1 = Foo::create(['bar' => 1])->fresh();
        $foo2 = Foo::create(['bar' => 2])->fresh();
        $foo3 = Foo::create(['bar' => 3])->fresh();

        $this->assertGreaterThan(0, $foo1->id);
        $this->assertGreaterThan(0, $foo2->id);
        $this->assertGreaterThan(0, $foo3->id);
        $this->assertGreaterThan($foo1->id, $foo2->id);
        $this->assertGreaterThan($foo1->sequence, $foo2->sequence);
        $this->assertGreaterThan($foo2->sequence, $foo3->sequence);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START), $foo1->sequence);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START + 1), $foo2->sequence);
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START + 2), $foo3->sequence);

        $foo2->delete();

        $this->assertNotNull($foo2->deleted_at);
        $this->assertNull($foo2->sequence);

        $foo4 = Foo::create(['bar' => 4])->fresh();
        $this->assertEquals($this->formatSequence(date('Y'), Foo::SEQ_SEQUENCE_START + 1), $foo4->sequence);

        $this->expectException(SequenceOutOfRangeException::class);

        Foo::create(['bar' => 5]);
    }
}