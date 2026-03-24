<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\NotificationRequest;
use App\Service\NotificationDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NotificationController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly NotificationDispatcher $dispatcher,
        private readonly array $enabledChannels,
    ) {
    }

    #[Route('/api/notifications', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dto = NotificationRequest::fromArray($payload, $this->enabledChannels);

        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->dispatcher->dispatch($dto);

        return $this->json(null, Response::HTTP_ACCEPTED);
    }
}
