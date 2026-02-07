<?php

namespace Georgeff\Container;

use RuntimeException;
use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends RuntimeException implements ContainerExceptionInterface {}
