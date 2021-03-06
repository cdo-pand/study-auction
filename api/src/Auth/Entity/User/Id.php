<?php

declare(strict_types=1);

namespace App\Auth\Entity\User;

use Ramsey\Uuid\Uuid;
use Webmozart\Assert\Assert;

class Id
{
    private string $value;

    /**
     * Id constructor.
     * @param string $value
     * @noinspection PhpFieldAssignmentTypeMismatchInspection
     */
    public function __construct(string $value)
    {
        Assert::uuid($value);
        $this->value = mb_strtolower($value);
    }

    /**
     * Generate id
     * @return self
     */
    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    /**
     * Return id value
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Convert object to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getValue();
    }
}
