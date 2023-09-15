<?php

namespace App\ObjectConstructor;

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Visitor\DeserializationVisitorInterface;

class ExistingObjectConstructor implements ObjectConstructorInterface
{
    public const ATTRIBUTE = 'deserialization-constructor-target';

    public function __construct(private ObjectConstructorInterface $fallbackConstructor)
    {
    }
    /**
     * @inheritDoc
     */
    public function construct(DeserializationVisitorInterface $visitor,
                              ClassMetadata $metadata,
                              $data,
                              array $type,
                              DeserializationContext $context
    ): ?object
    {
        if ($context->hasAttribute(self::ATTRIBUTE)) {
            return $context->getAttribute(self::ATTRIBUTE);
        }

        return $this->fallbackConstructor->construct($visitor, $metadata, $data, $type, $context);
    }
}