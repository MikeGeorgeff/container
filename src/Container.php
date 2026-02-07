<?php

namespace Georgeff\Container;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * Container definitions
     *
     * @var array<string, callable>
     */
    protected array $definitions = [];

    /**
     * Resolved shared definitions
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * Indicates if a definition is shared
     *
     * @var array<string, bool>
     */
    protected array $shared = [];

    /**
     * Definition aliases
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Definitions currently being resolved
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @inheritdoc
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->aliases[$id]);
    }

    /**
     * @inheritdoc
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new DefinitionNotFoundException("Container definition with the ID [$id] was not found");
        }

        $id = $this->getId($id);

        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new CircularDependencyException("Circular dependency detected for [$id]");
        }

        $this->resolving[$id] = true;

        try {
            $instance = ($this->definitions[$id])($this);
        } catch (CircularDependencyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ContainerException("Error resolving definition [$id]: {$e->getMessage()}", 0, $e);
        } finally {
            unset($this->resolving[$id]);
        }

        if ($this->isShared($id)) {
            $this->resolved[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Add a definition to the container
     *
     * @param string   $id
     * @param callable $definition
     * @param bool     $shared
     *
     * @return void
     */
    public function add(string $id, callable $definition, bool $shared = false): void
    {
        $this->definitions[$id] = $definition;

        if ($shared) {
            $this->shared[$id] = true;
        }
    }

    /**
     * Add a shared definition to the container
     *
     * @param string   $id
     * @param callable $definition
     *
     * @return void
     */
    public function addShared(string $id, callable $definition): void
    {
        $this->add($id, $definition, true);
    }

    /**
     * Add a definition alias
     *
     * @param string $id
     * @param string $alias
     *
     * @return void
     */
    public function addAlias(string $id, string $alias): void
    {
        if (!$this->has($id)) {
            throw new DefinitionNotFoundException("Cannot alias a non-existing definition [$id]");
        }

        $this->aliases[$alias] = $id;
    }

    /**
     * Indicates if the definition is shared
     *
     * @param string $id
     *
     * @return bool
     */
    public function isShared(string $id): bool
    {
        $id = $this->getId($id);

        return isset($this->shared[$id]);
    }

    /**
     * Get an Id from an alias or return the original ID
     *
     * @param string $id
     *
     * @return string
     */
    protected function getId(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }
}
