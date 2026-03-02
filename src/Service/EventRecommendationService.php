<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class EventRecommendationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Recommande des événements à un utilisateur en utilisant un Moteur Hybride :
     * 1. NLP Content-Based (TF-IDF sur le Titre et la Description)
     * 2. Filtrage Collaboratif (Similarité Cosinus entre Utilisateurs)
     *
     * @param User $targetUser L'utilisateur cible
     * @param int $limit Le nombre maximum de recommandations à retourner
     * @return array La liste des événements recommandés
     */
    public function getHybridRecommendations(User $targetUser, int $limit = 4): array
    {
        $conn = $this->em->getConnection();

        // 1. Récupérer toutes les interactions pertinentes : Utilisateurs -> Événements réservés
        $sqlInteractions = "SELECT r.participant_id as user_id, r.evenement_id 
                            FROM reservation r WHERE r.status = 'CONFIRMED'";
        $interactionsDb = $conn->fetchAllAssociative($sqlInteractions);

        $targetUserId = $targetUser->getId();
        if (!$targetUserId) {
            return $this->getPopularEvents($limit);
        }

        // Construire les profils Utilisateurs (Filtrage collaboratif)
        $userProfiles = []; // [user_id => [event_id => 1]]
        // Et lister les événements réservés par l'utilisateur cible (Filtrage Content-Based NLP)
        $targetReservedEventIds = []; 

        foreach ($interactionsDb as $row) {
            $uId = (int)$row['user_id'];
            $eId = (int)$row['evenement_id'];
            if (!isset($userProfiles[$uId])) {
                $userProfiles[$uId] = [];
            }
            $userProfiles[$uId][$eId] = 1;
            
            if ($uId === $targetUserId) {
                $targetReservedEventIds[] = $eId;
            }
        }

        // Cold Start : si l'utilisateur n'a aucune réservation
        if (empty($targetReservedEventIds)) {
            return $this->getPopularEvents($limit);
        }

        // --- PARTIE 1 : FILTRAGE COLLABORATIF (Les autres utilisateurs similaires) ---
        $collaborativeScores = [];
        $targetProfile = $userProfiles[$targetUserId];
        
        foreach ($userProfiles as $otherUserId => $otherProfile) {
            if ($otherUserId === $targetUserId) continue;
            
            $similarity = $this->calculateCosineSimilarity($targetProfile, $otherProfile);
            if ($similarity > 0) {
                foreach ($otherProfile as $eventId => $value) {
                    if (!isset($targetProfile[$eventId])) { // S'il ne l'a pas déjà réservé
                        $collaborativeScores[$eventId] = ($collaborativeScores[$eventId] ?? 0) + $similarity;
                    }
                }
            }
        }

        // Normalisation des scores collaboratifs (Max = 1.0)
        $maxCollabScore = empty($collaborativeScores) ? 1 : max($collaborativeScores);
        foreach ($collaborativeScores as $eId => $score) {
            $collaborativeScores[$eId] = $score / $maxCollabScore;
        }

        // --- PARTIE 2 : FILTRAGE PAR CONTENU NLP (TF-IDF Sémantique) ---
        // Récupérer le texte (Titre + Desc) de TOUS les événements actifs
        $sqlEventsText = "SELECT id, titre, description FROM evenement WHERE annule = 0 AND date_debut > NOW()";
        $eventsDb = $conn->fetchAllAssociative($sqlEventsText);

        $eventTexts = []; // [event_id => "texte concaténé"]
        $documentCorpus = []; // Liste indexée de tous les textes
        $mapIndexToEventId = []; // Pour retrouver l'ID depuis l'index du corpus

        foreach ($eventsDb as $index => $row) {
            $eId = (int)$row['id'];
            $text = strtolower(strip_tags($row['titre'] . ' ' . $row['description']));
            $eventTexts[$eId] = $text;
            $documentCorpus[$index] = $text;
            $mapIndexToEventId[$index] = $eId;
        }

        // Construire le "Profil Textuel" de l'utilisateur (Concaténation de tous les événements achetés)
        $userTextProfile = "";
        $sqlTargetEvents = "SELECT titre, description FROM evenement WHERE id IN (?)";
        $stmt = $conn->executeQuery($sqlTargetEvents, [$targetReservedEventIds], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
        while ($row = $stmt->fetchAssociative()) {
            $userTextProfile .= " " . strtolower(strip_tags($row['titre'] . ' ' . $row['description']));
        }

        // On calcule un dictionnaire simple des mots (TF rudimentaire sans dépendance lourde)
        $userWordsFreq = $this->extractWordFrequencies($userTextProfile);
        
        $contentScores = [];
        foreach ($mapIndexToEventId as $index => $eventId) {
            if (in_array($eventId, $targetReservedEventIds)) continue; // Ne pas recommander ce qu'il a déjà
            
            $eventWordsFreq = $this->extractWordFrequencies($documentCorpus[$index]);
            // Calcul Similarité Cosinus entre le vocabulaire de l'utilisateur et l'événement
            $sim = $this->calculateCosineSimilarity($userWordsFreq, $eventWordsFreq);
            if ($sim > 0) {
                $contentScores[$eventId] = $sim;
            }
        }

        // --- PARTIE 3 : MOTEUR HYBRIDE (Combinaison NLP 50% + Collab 50%) ---
        $hybridScores = [];
        // Fusionner toutes les clés uniques d'événements potentiels
        $allPotentialEventIds = array_unique(array_merge(array_keys($collaborativeScores), array_keys($contentScores)));
        
        foreach ($allPotentialEventIds as $eId) {
            $collabWeight = $collaborativeScores[$eId] ?? 0;
            $contentWeight = $contentScores[$eId] ?? 0;
            
            // Equation Hybride pondérée
            $hybridScores[$eId] = ($collabWeight * 0.5) + ($contentWeight * 0.5);
        }

        // Trier par score global hybride
        arsort($hybridScores);
        
        // 4. Récupérer les entités
        $topEventIds = array_slice(array_keys($hybridScores), 0, $limit);
        
        if (empty($topEventIds)) {
            return $this->getPopularEvents($limit);
        }
        
        $query = $this->em->createQuery('
            SELECT e FROM App\Entity\Evenement e
            WHERE e.id IN (:ids)
        ')->setParameter('ids', $topEventIds);
        
        $results = $query->getResult();
        
        // Re-trier les objets ORM dans l'ordre exact demandé par l'algorithme hybride
        usort($results, function($a, $b) use ($topEventIds) {
            $posA = array_search($a->getId(), $topEventIds);
            $posB = array_search($b->getId(), $topEventIds);
            return $posA <=> $posB;
        });

        // Combler avec les populaires si on n'a pas atteint la limite
        if (count($results) < $limit) {
            $populars = $this->getPopularEvents($limit - count($results));
            foreach ($populars as $pop) {
                if (!in_array($pop->getId(), array_map(fn($e) => $e->getId(), $results))) {
                    $results[] = $pop;
                }
            }
        }
        
        return $results;
    }

    /**
     * Helper TF-IDF basique : Extrait la fréquence des mots (Term Frequency) dans un texte.
     */
    private function extractWordFrequencies(string $text): array
    {
        // Enlève ponctuation, s'assure d'avoir des mots de plus de 3 lettres
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = mb_split('\s+', $text);
        
        $freqs = [];
        $stopWords = ['les', 'des', 'une', 'qui', 'que', 'quoi', 'pour', 'dans', 'sur', 'avec'];
        
        foreach ($words as $w) {
            if (mb_strlen($w) > 3 && !in_array($w, $stopWords)) {
                $freqs[$w] = ($freqs[$w] ?? 0) + 1;
            }
        }
        return $freqs;
    }

    /**
     * Calcule la Similarité Cosinus entre deux vecteurs utilisateurs (historique des réservations).
     * Plus le score est proche de 1, plus les goûts sont similaires.
     */
    private function calculateCosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        // Créer l'univers de tous les événements vus par ces deux utilisateurs
        $allKeys = array_unique(array_merge(array_keys($vec1), array_keys($vec2)));

        foreach ($allKeys as $key) {
            $val1 = $vec1[$key] ?? 0;
            $val2 = $vec2[$key] ?? 0;

            $dotProduct += ($val1 * $val2);
            $normA += ($val1 ** 2);
            $normB += ($val2 ** 2);
        }

        if ($normA === 0 || $normB === 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Fallback Algorithm : Cold Start (utilisé quand l'IA ne peut pas encore recommander)
     */
    public function getPopularEvents(int $limit = 4): array
    {
        // Récupère les événements avec le plus de réservations (Les plus populaires)
        $query = $this->em->createQuery('
            SELECT e, COUNT(r.id) as HIDDEN resCount
            FROM App\Entity\Evenement e
            LEFT JOIN e.reservations r
            WHERE e.annule = false
            AND e.dateDebut > :now
            GROUP BY e.id
            ORDER BY resCount DESC
        ')
        ->setParameter('now', new \DateTimeImmutable())
        ->setMaxResults($limit);

        return $query->getResult();
    }
}
