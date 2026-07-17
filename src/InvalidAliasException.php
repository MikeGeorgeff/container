<?php

namespace Georgeff\Container;

use Psr\Container\ContainerExceptionInterface;

final class InvalidAliasException extends \InvalidArgumentException implements ContainerExceptionInterface {}
