<?php

declare(strict_types=1);

namespace CreditResourceBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;

final class CreateResourceBillMessage implements AsyncMessageInterface
{
    private string $bizUserId;

    private string $resourcePriceId;

    private string $time;

    public function getBizUserId(): string
    {
        return $this->bizUserId;
    }

    public function setBizUserId(string $bizUserId): void
    {
        $this->bizUserId = $bizUserId;
    }

    public function getResourcePriceId(): string
    {
        return $this->resourcePriceId;
    }

    public function setResourcePriceId(string $resourcePriceId): void
    {
        $this->resourcePriceId = $resourcePriceId;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function setTime(string $time): void
    {
        $this->time = $time;
    }
}
