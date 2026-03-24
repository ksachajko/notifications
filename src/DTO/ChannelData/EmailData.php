<?php

declare(strict_types=1);

namespace App\DTO\ChannelData;

use Symfony\Component\Validator\Constraints as Assert;

class EmailData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $to;

    #[Assert\NotBlank]
    public string $subject;

    #[Assert\NotBlank]
    public string $body;

    private function __construct()
    {
    }

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->to = $data['to'] ?? '';
        $dto->subject = $data['subject'] ?? '';
        $dto->body = $data['body'] ?? '';

        return $dto;
    }
}
