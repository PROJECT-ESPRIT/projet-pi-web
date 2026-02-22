<?php

namespace App\Command;

use App\Entity\Commande;
use App\Entity\Donation;
use App\Entity\Evenement;
use App\Entity\Forum;
use App\Entity\ForumReponse;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Reservation;
use App\Entity\TypeDon;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Seed the database with realistic sample data',
)]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Purge all existing data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('purge')) {
            $io->warning('Purging all existing data…');
            $this->purge();
            $io->info('Database purged.');
        }

        $io->section('Creating users');
        [$admin, $artists, $participants] = $this->createUsers($io);

        $io->section('Creating donation types');
        $types = $this->createTypeDons($io);

        $io->section('Creating events');
        $events = $this->createEvenements($io, $artists);

        $io->section('Creating products');
        $produits = $this->createProduits($io);

        $io->section('Creating reservations');
        $this->createReservations($io, $events, $participants);

        $io->section('Creating orders');
        $this->createCommandes($io, $produits, $participants);

        $io->section('Creating donations');
        $this->createDonations($io, $types, array_merge($artists, $participants));

        $io->section('Creating forum posts & replies');
        $this->createForums($io, $participants, $admin);

        $this->em->flush();

        $io->success('Database seeded successfully!');
        $io->table(['Entity', 'Count'], [
            ['Users', 1 + count($artists) + count($participants) + 2],
            ['TypeDon', count($types)],
            ['Evenements', count($events)],
            ['Produits', count($produits)],
            ['Reservations', count($events) * 2],
            ['Commandes', count($participants)],
            ['Donations', count($types) * 2],
            ['Forum posts', 5],
        ]);

        $io->note('All passwords are: password123');

        return Command::SUCCESS;
    }

    private function purge(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'forum_reponse', 'forum',
            'ligne_commande', 'commande',
            'donation', 'type_don',
            'reservation', 'evenement',
            'produit', 'password_reset_token', 'user',
        ] as $table) {
            $conn->executeStatement("TRUNCATE TABLE `$table`");
        }

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    // -----------------------------------------------------------------
    //  USERS
    // -----------------------------------------------------------------

    private function createUsers(SymfonyStyle $io): array
    {
        $admin = $this->makeUser('admin@art.com', 'Admin', 'Super', ['ROLE_ADMIN']);
        $io->text(' + admin@art.com (ADMIN)');

        $artistData = [
            ['youssefjobrane98@gmail.com', 'Jobrane', 'Youssef'],
            ['leila.art@mail.tn', 'Ben Salah', 'Leila'],
            ['karim.design@mail.tn', 'Trabelsi', 'Karim'],
            ['nour.music@mail.tn', 'Gharbi', 'Nour'],
        ];

        $artists = [];
        foreach ($artistData as [$email, $nom, $prenom]) {
            $artists[] = $this->makeUser($email, $nom, $prenom, ['ROLE_ARTISTE']);
            $io->text(" + $email (ARTISTE)");
        }

        $participantData = [
            ['sara.parent@mail.tn', 'Hammami', 'Sara'],
            ['mehdi.family@mail.tn', 'Jebali', 'Mehdi'],
            ['amina.test@mail.tn', 'Riahi', 'Amina'],
            ['youssef.play@mail.tn', 'Bouazizi', 'Youssef'],
        ];

        $participants = [];
        foreach ($participantData as [$email, $nom, $prenom]) {
            $participants[] = $this->makeUser($email, $nom, $prenom, ['ROLE_PARTICIPANT']);
            $io->text(" + $email (PARTICIPANT)");
        }

        $pendingArtist = $this->makeUser('pending.artist@mail.tn', 'Maatoug', 'Yasmine', ['ROLE_ARTISTE'], User::STATUS_EMAIL_VERIFIED);
        $io->text(' + pending.artist@mail.tn (ARTISTE - EMAIL_VERIFIED, awaiting admin)');

        $emailPending = $this->makeUser('new.user@mail.tn', 'Chaabane', 'Omar', ['ROLE_PARTICIPANT'], User::STATUS_EMAIL_PENDING);
        $emailPending->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $io->text(' + new.user@mail.tn (PARTICIPANT - EMAIL_PENDING)');

        $this->em->flush();

        return [$admin, $artists, $participants];
    }

    private function makeUser(string $email, string $nom, string $prenom, array $roles, string $status = User::STATUS_APPROVED): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setRoles($roles);
        $user->setStatus($status);
        $user->setTelephone('+216 ' . random_int(20, 99) . ' ' . random_int(100, 999) . ' ' . random_int(100, 999));
        $user->setPassword($this->hasher->hashPassword($user, 'password123'));
        $user->setProfileImageUrl('https://i.pravatar.cc/200?u=' . urlencode($email));
        $this->em->persist($user);

        return $user;
    }

    // -----------------------------------------------------------------
    //  TYPE DON
    // -----------------------------------------------------------------

    private function createTypeDons(SymfonyStyle $io): array
    {
        $labels = ['Matériel artistique', 'Argent', 'Vêtements', 'Meubles', 'Jouets éducatifs', 'Livres'];
        $types = [];

        foreach ($labels as $label) {
            $t = new TypeDon();
            $t->setLibelle($label);
            $this->em->persist($t);
            $types[] = $t;
            $io->text(" + $label");
        }

        $this->em->flush();

        return $types;
    }

    // -----------------------------------------------------------------
    //  EVENEMENTS
    // -----------------------------------------------------------------

    private function createEvenements(SymfonyStyle $io, array $artists): array
    {
        $images = [
            'https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=800',
            'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800',
            'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800',
            'https://images.unsplash.com/photo-1607457561901-e6ec3a6d16cf?w=800',
            'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?w=800',
            'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?w=800',
            'https://images.unsplash.com/photo-1554048612-b6a482bc67e5?w=800',
            'https://images.unsplash.com/photo-1508700929628-666bc8bd84ea?w=800',
            'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800',
            'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800',
            'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800',
            'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=800',
        ];

        $youssefArtist = $artists[0];

        $data = [
            ['Atelier Aquarelle pour enfants', 'Découvrez les techniques de base de l\'aquarelle dans un cadre ludique et bienveillant. Adapté aux enfants de 5 à 12 ans.', 'Espace Culturel El Menzah, Tunis', 25, 0, 5, 12, 15.00],
            ['Initiation à la Poterie', 'Apprenez à modeler l\'argile et créez vos premières œuvres en poterie. Un moment de détente et de créativité.', 'Maison des Arts, La Marsa', 20, 0, 6, 14, 20.00],
            ['Concert Inclusif : Musique pour Tous', 'Un concert interactif où les enfants découvrent les instruments et participent à la création musicale collective.', 'Théâtre Municipal de Tunis', 100, 0, 3, null, null],
            ['Atelier Dessin Manga', 'Plongez dans l\'univers du manga ! Les enfants apprendront les bases du dessin manga avec un illustrateur professionnel.', 'Centre Culturel Hammam-Lif', 18, 0, 8, 16, 12.00],
            ['Peinture Murale Collaborative', 'Projet artistique collectif : les enfants créent une fresque murale sur le thème de la nature et de l\'inclusion.', 'École Primaire Ariana, Ariana', 30, 0, 6, 15, 18.00],
            ['Spectacle de Marionnettes', 'Un spectacle coloré de marionnettes suivi d\'un atelier où les enfants fabriquent leurs propres marionnettes.', 'Bibliothèque Nationale, Tunis', 40, 0, 4, 10, 8.00],
            ['Atelier Photo Créative', 'Les enfants explorent la photographie artistique. Thème : mon quartier vu par mes yeux.', 'Cité de la Culture, Tunis', 15, 0, 10, 16, 25.00],
            ['Danse Inclusive : Bouge avec moi', 'Atelier de danse où chaque enfant trouve sa propre expression corporelle. Accessible à tous.', 'Salle Omnisports Mégrine', 35, 0, 4, 14, 10.00],
            ['Stage Théâtre Ados (payant)', 'Stage intensif de théâtre pour adolescents : improvisation, texte et mise en scène. Encadrement professionnel.', 'Théâtre El Hamra, Tunis', 20, 0, 12, 17, 45.00],
            ['Concert Jazz & World (payant)', 'Soirée jazz et musiques du monde avec musiciens invités. Bar et restauration sur place.', 'Café des Nuits, La Marsa', 60, 0, 16, null, 35.00],
            ['Atelier Calligraphie Arabe (payant)', 'Initiation à la calligraphie arabe : outils, gestes et réalisation d\'une œuvre encadrée.', 'Institut des Beaux-Arts, Tunis', 12, 0, 14, null, 40.00],
            ['Festival Street Art (payant)', 'Week-end street art : démonstrations live, ateliers bombes et pochoirs, exposition. Prévoir tenue adaptée.', 'Friche industrielle, Ben Arous', 80, 0, 10, null, 22.00],
        ];

        $events = [];
        foreach ($data as $i => [$titre, $desc, $lieu, $places, $ageMin, $ageMinVal, $ageMax, $prix]) {
            $ev = new Evenement();
            $ev->setTitre($titre);
            $ev->setDescription($desc);
            $ev->setLieu($lieu);
            $ev->setNbPlaces($places);
            $ev->setAgeMin($ageMinVal > 0 ? $ageMinVal : null);
            $ev->setAgeMax($ageMax);
            $ev->setPrix($prix);

            $start = new \DateTime('+' . ($i * 5 + 3) . ' days');
            $start->setTime(random_int(9, 15), 0);
            $ev->setDateDebut($start);
            $ev->setDateFin((clone $start)->modify('+2 hours'));
            $ev->setOrganisateur(($i < 6 || $i >= 8) ? $youssefArtist : $artists[$i % count($artists)]);
            $ev->setImage($images[$i % count($images)]);

            if ($i === 2) {
                $ev->setLayoutType('theatre');
                $ev->setLayoutRows(8);
                $ev->setLayoutCols(12);
                $ev->setNbPlaces(96);
            } elseif ($i === 5) {
                $ev->setLayoutType('banquet');
                $ev->setLayoutRows(5);
                $ev->setLayoutCols(6);
                $ev->setNbPlaces(30);
            }

            $this->em->persist($ev);
            $events[] = $ev;
            $io->text(" + $titre");
        }

        $this->em->flush();

        return $events;
    }

    // -----------------------------------------------------------------
    //  PRODUITS
    // -----------------------------------------------------------------

    private function createProduits(SymfonyStyle $io): array
    {
        $data = [
            ['Kit Aquarelle Enfant', 'Boîte de 24 couleurs aquarelle avec pinceaux et papier spécial, idéale pour les petits artistes.', 35.00, 50],
            ['Tablier Artiste Junior', 'Tablier en coton imperméable avec poches, décoré de motifs artistiques. Taille enfant.', 18.00, 30],
            ['Carnet de Croquis A4', 'Carnet 100 pages papier épais 200g, parfait pour le dessin et l\'aquarelle.', 12.50, 80],
            ['Set de Peinture Acrylique', '12 tubes de peinture acrylique non toxique aux couleurs vives. Séchage rapide.', 28.00, 40],
            ['Chevalet de Table Pliable', 'Chevalet en bois de hêtre, compact et léger. Idéal pour les ateliers mobiles.', 45.00, 15],
            ['Argile Auto-durcissante 1kg', 'Argile blanche qui sèche à l\'air libre. Parfaite pour la poterie sans four.', 9.90, 60],
            ['Lot de Feutres Lavables', '36 feutres de couleurs différentes, lavables à l\'eau. Pointe moyenne.', 15.00, 45],
            ['Tote Bag Art Connect', 'Sac en toile bio avec le logo Art Connect. Parfait pour transporter son matériel.', 22.00, 25],
        ];

        $produits = [];
        foreach ($data as [$nom, $desc, $prix, $stock]) {
            $p = new Produit();
            $p->setNom($nom);
            $p->setDescription($desc);
            $p->setPrix($prix);
            $p->setStock($stock);
            $this->em->persist($p);
            $produits[] = $p;
            $io->text(" + $nom ($prix TND)");
        }

        $this->em->flush();

        return $produits;
    }

    // -----------------------------------------------------------------
    //  RESERVATIONS
    // -----------------------------------------------------------------

    private function createReservations(SymfonyStyle $io, array $events, array $participants): void
    {
        $count = 0;
        foreach ($events as $event) {
            $shuffled = $participants;
            shuffle($shuffled);
            $pick = array_slice($shuffled, 0, min(2, count($shuffled)));

            $seatIndex = 0;
            foreach ($pick as $participant) {
                $r = new Reservation();
                $r->setEvenement($event);
                $r->setParticipant($participant);

                if ($event->getLayoutType()) {
                    $seatIndex++;
                    if ($event->getLayoutType() === 'theatre') {
                        $row = (int) ceil($seatIndex / $event->getLayoutCols());
                        $col = $seatIndex - ($row - 1) * $event->getLayoutCols();
                        $r->setSeatLabel('R' . $row . '-S' . $col);
                    } else {
                        $table = (int) ceil($seatIndex / $event->getLayoutCols());
                        $seat = $seatIndex - ($table - 1) * $event->getLayoutCols();
                        $r->setSeatLabel('T' . $table . '-S' . $seat);
                    }
                }

                $this->em->persist($r);
                $count++;
            }
        }

        $this->em->flush();
        $io->text(" + $count reservations created");
    }

    // -----------------------------------------------------------------
    //  COMMANDES
    // -----------------------------------------------------------------

    private function createCommandes(SymfonyStyle $io, array $produits, array $participants): void
    {
        $count = 0;
        foreach ($participants as $participant) {
            $shuffled = $produits;
            shuffle($shuffled);
            $items = array_slice($shuffled, 0, random_int(1, 3));

            $commande = new Commande();
            $commande->setUser($participant);
            $commande->setStatut((['EN_ATTENTE', 'CONFIRMEE', 'LIVREE'])[random_int(0, 2)]);

            $total = 0.0;
            foreach ($items as $prod) {
                $qty = random_int(1, 2);
                $ligne = new LigneCommande();
                $ligne->setProduit($prod);
                $ligne->setQuantite($qty);
                $ligne->setPrixUnitaire($prod->getPrix());
                $commande->addLigneCommande($ligne);
                $total += $prod->getPrix() * $qty;
            }

            $commande->setTotal($total);
            $this->em->persist($commande);
            $count++;
        }

        $this->em->flush();
        $io->text(" + $count orders created");
    }

    // -----------------------------------------------------------------
    //  DONATIONS
    // -----------------------------------------------------------------

    private function createDonations(SymfonyStyle $io, array $types, array $users): void
    {
        $descriptions = [
            'Matériel artistique' => ['Boîte de 48 crayons de couleur et 3 blocs de dessin', 'Lot de pinceaux et tubes de peinture acrylique'],
            'Argent' => ['Don de 50 TND pour soutenir les ateliers', 'Contribution de 100 TND au fonds de bourses'],
            'Vêtements' => ['10 tabliers d\'artiste en bon état', 'Lot de t-shirts blancs pour la peinture'],
            'Meubles' => ['2 tables basses en bois pour atelier enfants', 'Étagère murale pour ranger le matériel'],
            'Jouets éducatifs' => ['Puzzle 3D en bois thème animaux', 'Jeu de construction créatif 200 pièces'],
            'Livres' => ['Collection de 15 livres d\'art pour enfants', 'Encyclopédie illustrée de la peinture'],
        ];

        $count = 0;
        foreach ($types as $type) {
            $descs = $descriptions[$type->getLibelle()] ?? ['Don généreux', 'Contribution solidaire'];
            foreach ($descs as $j => $desc) {
                $d = new Donation();
                $d->setType($type);
                $d->setDescription($desc);
                $d->setDonateur($users[($count) % count($users)]);
                $d->setDateDon((new \DateTimeImmutable())->modify('-' . random_int(1, 30) . ' days'));
                $this->em->persist($d);
                $count++;
            }
        }

        $this->em->flush();
        $io->text(" + $count donations created");
    }

    // -----------------------------------------------------------------
    //  FORUM + REPLIES
    // -----------------------------------------------------------------

    private function createForums(SymfonyStyle $io, array $participants, User $admin): void
    {
        $posts = [
            ['Sara', 'Hammami', 'sara.parent@mail.tn', 'Quel atelier pour un enfant de 5 ans ?', 'Bonjour, ma fille a 5 ans et adore dessiner. Quel atelier me conseillez-vous pour une première expérience artistique ? Elle est un peu timide mais très créative.'],
            ['Mehdi', 'Jebali', 'mehdi.family@mail.tn', 'Retour d\'expérience : Atelier Poterie', 'Nous avons participé à l\'atelier poterie la semaine dernière et c\'était fantastique ! Les animateurs étaient très patients avec mon fils qui a des besoins spécifiques. Je recommande vivement.'],
            ['Amina', 'Riahi', 'amina.test@mail.tn', 'Transport vers les ateliers', 'Est-ce que des solutions de transport sont prévues pour les familles qui habitent loin des centres culturels ? Ce serait vraiment utile pour faciliter l\'accès.'],
            ['Youssef', 'Bouazizi', 'youssef.play@mail.tn', 'Suggestion : Atelier musique pour ados', 'Mon fils de 14 ans aimerait participer à un atelier de musique. Y a-t-il des projets pour les adolescents ? Il joue de la guitare et aimerait apprendre la batterie.'],
            ['Sara', 'Hammami', 'sara.parent@mail.tn', 'Merci Art Connect !', 'Je voulais simplement remercier toute l\'équipe pour le travail incroyable. Ma fille attend chaque atelier avec impatience. Vous changez des vies !'],
        ];

        $replies = [
            'Merci pour votre message ! L\'atelier Aquarelle serait parfait pour commencer. Les enfants de 5 ans s\'y sentent très à l\'aise.',
            'Ravi que votre expérience ait été positive ! Nous travaillons dur pour l\'inclusion de tous les enfants.',
            'Nous étudions actuellement des partenariats avec des services de transport. Restez à l\'écoute !',
            'Bonne nouvelle ! Un atelier musique pour adolescents est prévu le mois prochain. Les inscriptions ouvriront bientôt.',
            'Merci beaucoup Sara ! Des messages comme le vôtre nous motivent chaque jour. À très bientôt !',
        ];

        $count = 0;
        foreach ($posts as $i => [$prenom, $nom, $email, $sujet, $message]) {
            $f = new Forum();
            $f->setNom($nom);
            $f->setPrenom($prenom);
            $f->setEmail($email);
            $f->setSujet($sujet);
            $f->setMessage($message);
            $f->setDateCreation((new \DateTimeImmutable())->modify('-' . (count($posts) - $i) . ' days'));
            $this->em->persist($f);

            $r = new ForumReponse();
            $r->setForum($f);
            $r->setAuteur($admin);
            $r->setContenu($replies[$i]);
            $r->setDateReponse((new \DateTimeImmutable())->modify('-' . (count($posts) - $i) . ' days +2 hours'));
            $this->em->persist($r);

            $count++;
            $io->text(" + \"$sujet\" (+ 1 reply)");
        }

        $this->em->flush();
    }
}
