<?php

namespace Georgeff\Container\Test;

use Georgeff\Container\CircularDependencyException;
use Georgeff\Container\Container;
use Georgeff\Container\ContainerException;
use Georgeff\Container\DefinitionNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ContainerTest extends TestCase
{
    // has()

    public function test_has_returns_false_for_unregistered_id(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('foo'));
    }

    public function test_has_returns_true_for_registered_definition(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $this->assertTrue($container->has('foo'));
    }

    public function test_has_returns_true_for_alias(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());
        $container->addAlias('foo', 'bar');

        $this->assertTrue($container->has('bar'));
    }

    // get()

    public function test_get_resolves_definition(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $result = $container->get('foo');

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function test_get_throws_not_found_for_unregistered_id(): void
    {
        $container = new Container();

        $this->expectException(DefinitionNotFoundException::class);

        $container->get('foo');
    }

    public function test_get_returns_new_instance_each_call_for_non_shared(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $a = $container->get('foo');
        $b = $container->get('foo');

        $this->assertNotSame($a, $b);
    }

    public function test_get_wraps_factory_exception_in_container_exception(): void
    {
        $container = new Container();
        $original = new \RuntimeException('factory failed');
        $container->add('foo', function () use ($original) {
            throw $original;
        });

        try {
            $container->get('foo');
            $this->fail('Expected ContainerException');
        } catch (ContainerException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // Shared definitions

    public function test_shared_definition_returns_same_instance(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());

        $a = $container->get('foo');
        $b = $container->get('foo');

        $this->assertSame($a, $b);
    }

    public function test_add_with_shared_flag_returns_same_instance(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass(), true);

        $a = $container->get('foo');
        $b = $container->get('foo');

        $this->assertSame($a, $b);
    }

    public function test_is_shared_returns_true_for_shared_definition(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());

        $this->assertTrue($container->isShared('foo'));
    }

    public function test_is_shared_returns_false_for_non_shared_definition(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $this->assertFalse($container->isShared('foo'));
    }

    // Aliases

    public function test_get_resolves_alias_to_original_definition(): void
    {
        $expected = new \stdClass();
        $container = new Container();
        $container->add('foo', fn () => $expected);
        $container->addAlias('foo', 'bar');

        $this->assertSame($expected, $container->get('bar'));
    }

    public function test_alias_of_shared_definition_returns_same_instance(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());
        $container->addAlias('foo', 'bar');

        $a = $container->get('foo');
        $b = $container->get('bar');

        $this->assertSame($a, $b);
    }

    public function test_is_shared_returns_true_for_alias_of_shared_definition(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());
        $container->addAlias('foo', 'bar');

        $this->assertTrue($container->isShared('bar'));
    }

    public function test_add_alias_throws_for_non_existing_definition(): void
    {
        $container = new Container();

        $this->expectException(DefinitionNotFoundException::class);

        $container->addAlias('foo', 'bar');
    }

    // Circular dependency detection

    public function test_get_throws_on_circular_dependency(): void
    {
        $container = new Container();
        $container->add('a', fn (ContainerInterface $c) => $c->get('b'));
        $container->add('b', fn (ContainerInterface $c) => $c->get('a'));

        $this->expectException(CircularDependencyException::class);

        $container->get('a');
    }

    public function test_resolving_state_is_cleaned_up_after_exception(): void
    {
        $container = new Container();
        $calls = 0;
        $container->add('foo', function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('first call fails');
            }

            return new \stdClass();
        });

        try {
            $container->get('foo');
        } catch (ContainerException) {
            // expected
        }

        $result = $container->get('foo');

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    // PSR-11 interface compliance

    public function test_container_implements_psr_container_interface(): void
    {
        $container = new Container();

        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function test_not_found_exception_implements_psr_not_found_interface(): void
    {
        $e = new DefinitionNotFoundException();

        $this->assertInstanceOf(NotFoundExceptionInterface::class, $e);
    }

    public function test_container_exception_implements_psr_container_exception_interface(): void
    {
        $e = new ContainerException();

        $this->assertInstanceOf(ContainerExceptionInterface::class, $e);
    }

    public function test_circular_dependency_exception_implements_psr_container_exception_interface(): void
    {
        $e = new CircularDependencyException();

        $this->assertInstanceOf(ContainerExceptionInterface::class, $e);
    }

    // onResolving() — global pre-resolution hook

    public function test_on_resolving_fires_before_resolution(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $fired = [];
        $container->onResolving(function (string $id) use (&$fired) {
            $fired[] = $id;
        });

        $container->get('foo');

        $this->assertSame(['foo'], $fired);
    }

    public function test_on_resolving_fires_on_every_call_including_cache_hits(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());

        $count = 0;
        $container->onResolving(function () use (&$count) {
            $count++;
        });

        $container->get('foo');
        $container->get('foo');

        $this->assertSame(2, $count);
    }

    public function test_on_resolving_fires_with_canonical_id_when_resolved_via_alias(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());
        $container->addAlias('foo', 'bar');

        $fired = [];
        $container->onResolving(function (string $id) use (&$fired) {
            $fired[] = $id;
        });

        $container->get('bar');

        $this->assertSame(['foo'], $fired);
    }

    public function test_multiple_on_resolving_hooks_all_fire(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $fired = [];
        $container->onResolving(function () use (&$fired) { $fired[] = 'a'; });
        $container->onResolving(function () use (&$fired) { $fired[] = 'b'; });

        $container->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    // onResolvingId() — service-specific pre-resolution hook

    public function test_on_resolving_id_fires_only_for_target_id(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());
        $container->add('bar', fn () => new \stdClass());

        $fired = [];
        $container->onResolvingId('foo', function (string $id) use (&$fired) {
            $fired[] = $id;
        });

        $container->get('foo');
        $container->get('bar');

        $this->assertSame(['foo'], $fired);
    }

    public function test_multiple_on_resolving_id_hooks_for_same_id_all_fire(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $fired = [];
        $container->onResolvingId('foo', function () use (&$fired) { $fired[] = 'a'; });
        $container->onResolvingId('foo', function () use (&$fired) { $fired[] = 'b'; });

        $container->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    // afterResolved() — global post-resolution hook

    public function test_after_resolved_fires_after_resolution_with_id_and_instance(): void
    {
        $expected = new \stdClass();
        $container = new Container();
        $container->add('foo', fn () => $expected);

        $calls = [];
        $container->afterResolved(function (string $id, mixed $instance) use (&$calls) {
            $calls[] = [$id, $instance];
        });

        $container->get('foo');

        $this->assertCount(1, $calls);
        $this->assertSame('foo', $calls[0][0]);
        $this->assertSame($expected, $calls[0][1]);
    }

    public function test_after_resolved_does_not_fire_on_cache_hit(): void
    {
        $container = new Container();
        $container->addShared('foo', fn () => new \stdClass());
        $container->get('foo');

        $count = 0;
        $container->afterResolved(function () use (&$count) {
            $count++;
        });

        $container->get('foo');

        $this->assertSame(0, $count);
    }

    public function test_multiple_after_resolved_hooks_all_fire(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $fired = [];
        $container->afterResolved(function () use (&$fired) { $fired[] = 'a'; });
        $container->afterResolved(function () use (&$fired) { $fired[] = 'b'; });

        $container->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    // afterResolvedId() — service-specific post-resolution hook

    public function test_after_resolved_id_fires_only_for_target_id(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());
        $container->add('bar', fn () => new \stdClass());

        $fired = [];
        $container->afterResolvedId('foo', function (string $id) use (&$fired) {
            $fired[] = $id;
        });

        $container->get('foo');
        $container->get('bar');

        $this->assertSame(['foo'], $fired);
    }

    public function test_multiple_after_resolved_id_hooks_for_same_id_all_fire(): void
    {
        $container = new Container();
        $container->add('foo', fn () => new \stdClass());

        $fired = [];
        $container->afterResolvedId('foo', function () use (&$fired) { $fired[] = 'a'; });
        $container->afterResolvedId('foo', function () use (&$fired) { $fired[] = 'b'; });

        $container->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    // Hook firing order

    public function test_hook_firing_order_is_global_pre_specific_pre_factory_specific_post_global_post(): void
    {
        $container = new Container();

        $order = [];

        $container->onResolving(function () use (&$order) { $order[] = 'global-pre'; });
        $container->onResolvingId('foo', function () use (&$order) { $order[] = 'specific-pre'; });
        $container->afterResolvedId('foo', function () use (&$order) { $order[] = 'specific-post'; });
        $container->afterResolved(function () use (&$order) { $order[] = 'global-post'; });

        $container->add('foo', function () use (&$order) {
            $order[] = 'factory';

            return new \stdClass();
        });

        $container->get('foo');

        $this->assertSame(['global-pre', 'specific-pre', 'factory', 'specific-post', 'global-post'], $order);
    }
}
