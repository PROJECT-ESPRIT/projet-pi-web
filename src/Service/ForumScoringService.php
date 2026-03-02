<?php

namespace App\Service;

use App\Entity\Forum;

class ForumScoringService
{
    public function calculateScore(Forum $forum): int
    {
        $score = 0;
        
        // Points de base pour l'âge du post (plus récent = plus de points)
        $score += $this->calculateAgeScore($forum);
        
        // Points pour les likes
        $score += $forum->getLikesCount() * 10;
        
        // Points pour les réponses
        $score += $forum->getReponses()->count() * 5;
        
        // Pénalité pour les dislikes
        $score -= $forum->getDislikesCount() * 3;
        
        // Pénalité pour les signalements
        $score -= $forum->getSignalementsCount() * 8;
        
        // Bonus pour les posts avec beaucoup d'engagement
        $score += $this->calculateEngagementBonus($forum);
        
        return max(0, $score); // Le score ne peut pas être négatif
    }
    
    private function calculateAgeScore(Forum $forum): int
    {
        $now = new \DateTimeImmutable();
        $createdAt = $forum->getDateCreation();
        
        if (!$createdAt) {
            return 0;
        }
        
        $interval = $createdAt->diff($now);
        $hoursOld = $interval->h + ($interval->days * 24);
        
        // Plus le post est récent, plus il a de points
        if ($hoursOld < 1) {
            return 50; // Moins d'une heure
        } elseif ($hoursOld < 24) {
            return 30; // Moins d'un jour
        } elseif ($hoursOld < 168) {
            return 15; // Moins d'une semaine
        } elseif ($hoursOld < 720) {
            return 5; // Moins d'un mois
        } else {
            return 1; // Plus d'un mois
        }
    }
    
    private function calculateEngagementBonus(Forum $forum): int
    {
        $totalInteractions = $forum->getLikesCount() + 
                           $forum->getDislikesCount() + 
                           $forum->getReponses()->count() + 
                           $forum->getSignalementsCount();
        
        // Bonus pour les posts très populaires
        if ($totalInteractions >= 20) {
            return 20;
        } elseif ($totalInteractions >= 10) {
            return 10;
        } elseif ($totalInteractions >= 5) {
            return 5;
        }
        
        return 0;
    }
    
    public function updateScore(Forum $forum): void
    {
        $score = $this->calculateScore($forum);
        $forum->setScore($score);
    }
}
