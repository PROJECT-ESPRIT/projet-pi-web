<?php

namespace App\EventSubscriber;

use App\Entity\Forum;
use App\Entity\ForumLike;
use App\Entity\ForumReponse;
use App\Service\ForumScoringService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

class ForumScoreEventSubscriber implements EventSubscriberInterface
{
    private ForumScoringService $scoringService;

    public function __construct(ForumScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postRemove,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ForumLike) {
            $this->scoringService->updatePostScore($entity->getForum());
        } elseif ($entity instanceof ForumReponse) {
            $this->scoringService->updatePostScore($entity->getForum());
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ForumLike) {
            $this->scoringService->updatePostScore($entity->getForum());
        } elseif ($entity instanceof ForumReponse) {
            $this->scoringService->updatePostScore($entity->getForum());
        }
    }
}
