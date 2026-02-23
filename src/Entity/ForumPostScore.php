<?php

namespace App\Entity;

use App\Repository\ForumPostScoreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

#[ORM\Entity(repositoryClass: ForumPostScoreRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ForumPostScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'score', targetEntity: Forum::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $calculatedScore = '0.0000';

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $likesCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $dislikesCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $commentsCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $viewsCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $baseScore = '1.0000';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $lastCalculatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    public function __construct()
    {
        $this->lastCalculatedAt = new \DateTimeImmutable();
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForum(): ?Forum
    {
        return $this->forum;
    }

    public function setForum(?Forum $forum): static
    {
        $this->forum = $forum;
        return $this;
    }

    public function getCalculatedScore(): ?string
    {
        return $this->calculatedScore;
    }

    public function setCalculatedScore(string $calculatedScore): static
    {
        $this->calculatedScore = $calculatedScore;
        return $this;
    }

    public function getLikesCount(): ?int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): static
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function getDislikesCount(): ?int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): static
    {
        $this->dislikesCount = $dislikesCount;
        return $this;
    }

    public function getCommentsCount(): ?int
    {
        return $this->commentsCount;
    }

    public function setCommentsCount(int $commentsCount): static
    {
        $this->commentsCount = $commentsCount;
        return $this;
    }

    public function getViewsCount(): ?int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }

    public function getBaseScore(): ?string
    {
        return $this->baseScore;
    }

    public function setBaseScore(string $baseScore): static
    {
        $this->baseScore = $baseScore;
        return $this;
    }

    public function getLastCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->lastCalculatedAt;
    }

    public function setLastCalculatedAt(\DateTimeImmutable $lastCalculatedAt): static
    {
        $this->lastCalculatedAt = $lastCalculatedAt;
        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        if ($this->lastCalculatedAt === null) {
            $this->lastCalculatedAt = new \DateTimeImmutable();
        }
        if ($this->lastActivityAt === null) {
            $this->lastActivityAt = new \DateTimeImmutable();
        }
    }

    public function incrementViews(): void
    {
        $this->viewsCount++;
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function incrementLikes(): void
    {
        $this->likesCount++;
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function decrementLikes(): void
    {
        $this->likesCount = max(0, $this->likesCount - 1);
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function incrementDislikes(): void
    {
        $this->dislikesCount++;
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function decrementDislikes(): void
    {
        $this->dislikesCount = max(0, $this->dislikesCount - 1);
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function incrementComments(): void
    {
        $this->commentsCount++;
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function decrementComments(): void
    {
        $this->commentsCount = max(0, $this->commentsCount - 1);
        $this->lastActivityAt = new \DateTimeImmutable();
    }
}
