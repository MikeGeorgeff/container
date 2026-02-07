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
}
