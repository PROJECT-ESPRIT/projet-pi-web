<?php
// src/Repository/LigneCommandeRepository.php
namespace App\Repository;

use App\Entity\LigneCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    /**
     * Ventes d'un produit pour un mois donné (native SQL)
     */
    public function findQuantitesByProduitAndMonth(int $produitId, int $mois): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT lc.quantite, c.date_commande
            FROM ligne_commande lc
            JOIN commande c ON lc.commande_id = c.id
            WHERE lc.produit_id = ? 
              AND MONTH(c.date_commande) = ?
        ';

        // Passe les paramètres dans un tableau positionnel
        return $conn->fetchAllAssociative($sql, [$produitId, $mois]);
    }

    /**
     * Ventes d'un produit sur toutes les commandes
     */
    public function findQuantitesByProduit(int $produitId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT lc.quantite, c.date_commande
            FROM ligne_commande lc
            JOIN commande c ON lc.commande_id = c.id
            WHERE lc.produit_id = ?
        ';

        return $conn->fetchAllAssociative($sql, [$produitId]);
    }
}