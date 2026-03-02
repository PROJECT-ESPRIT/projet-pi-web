<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserSegmentationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Segments all users using Rubix ML K-Means clustering.
     */
    public function segmentAllUsers(): int
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $users = $userRepository->findAll();

        if (count($users) === 0) {
            return 0;
        }

        // 1. Extract Features (Samples)
        $samples = [];
        $now = new \DateTimeImmutable();

        foreach ($users as $user) {
            $commandes = count($user->getCommandes());
            $reservations = count($user->getReservations());
            $donations = count($user->getDonations());
            $forumReponses = count($user->getForumReponses());
            
            $daysSinceRegistration = $user->getCreatedAt() ? $user->getCreatedAt()->diff($now)->days : 0;
            
            // The Feature Vector
            $samples[] = [
                $commandes, 
                $reservations, 
                $donations, 
                $forumReponses,
                $daysSinceRegistration
            ];
        }

        // 2. Build Dataset
        $dataset = new \Rubix\ML\Datasets\Unlabeled($samples);

        // We want 4 clusters ideally: VIP, ACTIF, CHURN_RISK, DORMANT
        $k = min(4, count($users)); 
        if ($k < 1) {
            $k = 1;
        }

        // 3. Train the K-Means Model
        $estimator = new \Rubix\ML\Clusterers\KMeans($k);
        $estimator->train($dataset);

        // 4. Predict the clusters for our users
        $predictions = $estimator->predict($dataset);
        
        // 5. Semantic Mapping
        // The K-Means algorithm outputs arbitrary cluster IDs (0, 1, 2...). 
        // We need to figure out which cluster is "VIP" and which is "DORMANT".
        // We evaluate the cluster centroids (the centers) based on activity.
        $centroids = $estimator->centroids();
        $clusterScores = [];
        
        foreach ($centroids as $index => $centroid) {
            // A heuristic score to rank the clusters found by the IA
            // more actions = higher score, more days = slightly lower score
            $score = ($centroid[0] * 3) + ($centroid[1] * 2) + $centroid[2] + $centroid[3] - ($centroid[4] / 30);
            $clusterScores[$index] = $score;
        }
        
        // Sort clusters by score descending
        arsort($clusterScores);
        
        $segmentMap = [];
        $rank = 0;
        $segmentsNames = [
            User::SEGMENT_VIP, 
            User::SEGMENT_ACTIF, 
            User::SEGMENT_CHURN_RISK, 
            User::SEGMENT_DORMANT
        ];
        
        foreach ($clusterScores as $clusterId => $score) {
            $segmentMap[$clusterId] = $segmentsNames[$rank] ?? User::SEGMENT_DORMANT;
            $rank++;
        }

        // 6. Apply Segments to Users and Save
        foreach ($users as $index => $user) {
            $clusterId = $predictions[$index];
            $segment = $segmentMap[$clusterId];
            
            $user->setSegment($segment);
            
            // Batch flush
            if (($index % 100) === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        return count($users);
    }
}
