<?php

declare(strict_types=1);

namespace App\DTO;

use App\DTO\ChannelData\EmailData;
use App\DTO\ChannelData\SmsData;
use Symfony\Component\Validator\Constraints as Assert;

class NotificationRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public int $user;

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    public array $channels = [];

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public array $data = [];

    /** @var string[] */
    private array $enabledChannels;

    private function __construct()
    {
    }

    public static function fromArray(array $payload, array $enabledChannels): self
    {
        $dto = new self();
        $dto->enabledChannels = array_keys(array_filter($enabledChannels));
        $dto->user = isset($payload['user']) && is_int($payload['user']) ? $payload['user'] : 0;
        $dto->channels = $payload['channels'] ?? [];
        $dto->data = $payload['data'] ?? [];

        return $dto;
    }

    #[Assert\IsTrue(message: 'channels contains unsupported or disabled channel(s)')]
    public function isChannelsValid(): bool
    {
        if (!is_array($this->channels) || empty($this->channels)) {
            return true; // handled by NotBlank/Count constraints
        }

        foreach ($this->channels as $channel) {
            if (!in_array($channel, $this->enabledChannels, true)) {
                return false;
            }
        }

        return true;
    }

    #[Assert\IsTrue(message: 'data must contain an entry for each requested channel')]
    public function isDataValid(): bool
    {
        if (!is_array($this->channels) || !is_array($this->data)) {
            return true; // handled by other constraints
        }

        foreach ($this->channels as $channel) {
            if (!array_key_exists($channel, $this->data)) {
                return false;
            }
        }

        return true;
    }

    public function getEmailData(): ?EmailData
    {
        if (!isset($this->data['email']) || !is_array($this->data['email'])) {
            return null;
        }

        return EmailData::fromArray($this->data['email']);
    }

    public function getSmsData(): ?SmsData
    {
        if (!isset($this->data['sms']) || !is_array($this->data['sms'])) {
            return null;
        }

        return SmsData::fromArray($this->data['sms']);
    }

    public function getEnabledChannels(): array
    {
        return $this->enabledChannels;
    }
}
