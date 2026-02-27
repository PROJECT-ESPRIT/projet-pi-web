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
        [$admin, $artists, $participants, $extras] = $this->createUsers($io);

        $io->section('Creating donation types');
        $types = $this->createTypeDons($io);

        $io->section('Creating events');
        [$events, $hotEvents, $fillPctMap] = $this->createEvenements($io, $artists);

        $io->section('Creating products');
        $produits = $this->createProduits($io);

        $io->section('Creating reservations');
        $allParticipants = array_merge($participants, $extras);
        $this->createReservations($io, $events, $hotEvents, $fillPctMap, $allParticipants);

        $io->section('Creating orders');
        $this->createCommandes($io, $produits, $participants);

        $io->section('Creating donations');
        $this->createDonations($io, $types, array_merge($artists, $participants));

        $io->section('Creating forum posts & replies');
        $this->createForums($io, $participants, $admin);

        $this->em->flush();

        $io->success('Database seeded successfully!');
        $io->table(['Entity', 'Count'], [
            ['Users (core)',    1 + count($artists) + count($participants) + 1],
            ['Users (extras)',  count($extras)],
            ['TypeDon',         count($types)],
            ['Evenements (upcoming)', 16],
            ['Evenements (expired)',  12],
            ['Produits',        count($produits)],
            ['Commandes',       count($participants)],
            ['Donations',       count($types) * 2],
            ['Forum posts',     5],
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
        // Helper: random date within the past 6 months
        $randCreated = fn() => '-' . random_int(1, 180) . ' days';

        $admin = $this->makeUser('admin@art.com', 'Admin', 'Super', ['ROLE_ADMIN'], User::STATUS_APPROVED, '1985-03-15', '-180 days');
        $io->text(' + admin@art.com (ADMIN)');

        // [email, nom, prenom, birthDate, createdOffset]
        $artistData = [
            ['leila.art@mail.tn',          'Ben Salah',  'Leila',   '1990-07-22', '-170 days'],
            ['karim.design@mail.tn',       'Trabelsi',   'Karim',   '1988-11-05', '-155 days'],
            ['nour.music@mail.tn',         'Gharbi',     'Nour',    '1995-02-14', '-130 days'],
            ['yasminemaatougui9@gmail.com', 'Maatougui',  'Yasmine', '1992-09-30', '-110 days'],
        ];

        $artists = [];
        foreach ($artistData as [$email, $nom, $prenom, $birth, $created]) {
            $artists[] = $this->makeUser($email, $nom, $prenom, ['ROLE_ARTISTE'], User::STATUS_APPROVED, $birth, $created);
            $io->text(" + $email (ARTISTE, born $birth)");
        }

        // [email, nom, prenom, birthDate, createdOffset]
        $participantData = [
            ['sara.parent@mail.tn',   'Hammami',  'Sara',    '1983-05-10', '-160 days'],
            ['mehdi.family@mail.tn',  'Jebali',   'Mehdi',   '1980-12-28', '-140 days'],
            ['amina.test@mail.tn',    'Riahi',    'Amina',   '1998-04-03', '-95 days'],
            ['youssef.play@mail.tn',  'Bouazizi', 'Youssef', '2001-08-17', '-60 days'],
        ];

        $participants = [];
        foreach ($participantData as [$email, $nom, $prenom, $birth, $created]) {
            $participants[] = $this->makeUser($email, $nom, $prenom, ['ROLE_PARTICIPANT'], User::STATUS_APPROVED, $birth, $created);
            $io->text(" + $email (PARTICIPANT, born $birth)");
        }

        $emailPending = $this->makeUser('new.user@mail.tn', 'Chaabane', 'Omar', ['ROLE_PARTICIPANT'], User::STATUS_EMAIL_PENDING, '1999-06-21', '-3 days');
        $emailPending->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $io->text(' + new.user@mail.tn (PARTICIPANT - EMAIL_PENDING)');

        // Extra participants — createdAt spread randomly across past 6 months
        // [email, nom, prenom, birthDate]
        $extraData = [
            ['ines.b@mail.tn',       'Belhaj',    'Inès',      '1997-03-12'],
            ['omar.k@mail.tn',       'Khelifi',   'Omar',      '1994-09-05'],
            ['fatma.s@mail.tn',      'Saidi',     'Fatma',     '1989-06-18'],
            ['bilel.m@mail.tn',      'Mansouri',  'Bilel',     '2000-01-30'],
            ['rania.t@mail.tn',      'Tlili',     'Rania',     '1996-11-22'],
            ['hatem.g@mail.tn',      'Gargouri',  'Hatem',     '1982-07-14'],
            ['sonia.z@mail.tn',      'Zouari',    'Sonia',     '1991-04-08'],
            ['walid.b@mail.tn',      'Bouzid',    'Walid',     '2003-12-01'],
            ['meriem.c@mail.tn',     'Chaari',    'Meriem',    '1987-08-25'],
            ['nabil.h@mail.tn',      'Hamdi',     'Nabil',     '1979-05-17'],
            ['asma.r@mail.tn',       'Romdhane',  'Asma',      '2002-02-09'],
            ['khaled.f@mail.tn',     'Ferchichi', 'Khaled',    '1993-10-03'],
            ['leila.n@mail.tn',      'Nasr',      'Leila',     '1986-03-27'],
            ['tarek.b@mail.tn',      'Baccouche', 'Tarek',     '1999-07-11'],
            ['rim.a@mail.tn',        'Ayari',     'Rim',       '2001-09-19'],
            ['sofien.m@mail.tn',     'Mzoughi',   'Sofien',    '1984-12-06'],
            ['dorra.k@mail.tn',      'Karray',    'Dorra',     '1998-06-14'],
            ['amine.b@mail.tn',      'Brahmi',    'Amine',     '1992-01-23'],
            ['nesrine.h@mail.tn',    'Haddad',    'Nesrine',   '2004-04-16'],
            ['zied.t@mail.tn',       'Turki',     'Zied',      '1977-08-30'],
            ['cyrine.s@mail.tn',     'Sfar',      'Cyrine',    '1995-11-07'],
            ['mourad.b@mail.tn',     'Boughanmi', 'Mourad',    '1988-02-21'],
            ['yasmine.d@mail.tn',    'Dridi',     'Yasmine',   '2000-07-04'],
            ['hedi.c@mail.tn',       'Chaabane',  'Hédi',      '1981-05-29'],
            ['olfa.m@mail.tn',       'Mejri',     'Olfa',      '1990-10-13'],
            ['sami.g@mail.tn',       'Ghannouchi','Sami',      '2002-03-08'],
            ['wafa.b@mail.tn',       'Bensalem',  'Wafa',      '1985-09-24'],
            ['anis.k@mail.tn',       'Kammoun',   'Anis',      '1997-06-17'],
            ['hela.r@mail.tn',       'Rezgui',    'Héla',      '1993-12-31'],
            ['fares.m@mail.tn',      'Maaloul',   'Farès',     '2003-08-05'],
            ['sabrine.b@mail.tn',    'Belhassen', 'Sabrine',   '1980-04-19'],
            ['yassine.t@mail.tn',    'Toumi',     'Yassine',   '1996-01-26'],
            ['nadia.h@mail.tn',      'Hajji',     'Nadia',     '1989-07-08'],
            ['chiheb.a@mail.tn',     'Abid',      'Chiheb',    '2001-11-14'],
            ['emna.s@mail.tn',       'Soussi',    'Emna',      '1994-03-02'],
            ['lotfi.b@mail.tn',      'Bouzaiene', 'Lotfi',     '1983-08-16'],
            ['marwa.k@mail.tn',      'Khedher',   'Marwa',     '1999-05-28'],
            ['ghassen.m@mail.tn',    'Mbarki',    'Ghassen',   '1991-02-11'],
            ['sirine.f@mail.tn',     'Fehri',     'Sirine',    '2005-10-22'],
            ['bassem.t@mail.tn',     'Trabelsi',  'Bassem',    '1987-06-03'],
            ['azza.b@mail.tn',       'Brik',      'Azza',      '1978-01-15'],
            ['malek.g@mail.tn',      'Gueddiche', 'Malek',     '2000-09-09'],
            ['hana.z@mail.tn',       'Zribi',     'Hana',      '1995-04-27'],
            ['skander.m@mail.tn',    'Mahjoub',   'Skander',   '1986-12-18'],
            ['imen.b@mail.tn',       'Belhadj',   'Imen',      '2002-07-31'],
            ['riadh.k@mail.tn',      'Karoui',    'Riadh',     '1975-03-06'],
            ['ameni.s@mail.tn',      'Slimane',   'Ameni',     '1998-10-20'],
            ['hamza.b@mail.tn',      'Bouslama',  'Hamza',     '2004-02-14'],
            ['chiraz.m@mail.tn',     'Missaoui',  'Chiraz',    '1992-08-07'],
            ['seifeddine.h@mail.tn', 'Hammami',   'Seifeddine','1984-05-23'],
            ['ranim.b@mail.tn',      'Baccari',   'Ranim',     '2001-01-10'],
            ['mehdi.z@mail.tn',      'Zaied',     'Mehdi',     '1997-07-17'],
            ['chaima.t@mail.tn',     'Tounsi',    'Chaïma',    '1990-11-29'],
            ['aymen.b@mail.tn',      'Belhaj',    'Aymen',     '1988-04-04'],
            ['rahma.g@mail.tn',      'Ghribi',    'Rahma',     '2003-09-12'],
            ['foued.m@mail.tn',      'Meddeb',    'Foued',     '1982-06-26'],
            ['jihen.k@mail.tn',      'Khelil',    'Jihen',     '1996-02-08'],
            ['youssef.h@mail.tn',    'Hamouda',   'Youssef',   '2000-12-21'],
            ['sarra.b@mail.tn',      'Bensaid',   'Sarra',     '1993-05-15'],
            ['nizar.t@mail.tn',      'Tabbabi',   'Nizar',     '1979-10-01'],
            ['manel.r@mail.tn',      'Rhouma',    'Manel',     '1995-08-13'],
        ];

        $extras = [];
        foreach ($extraData as [$email, $nom, $prenom, $birth]) {
            $extras[] = $this->makeUser($email, $nom, $prenom, ['ROLE_PARTICIPANT'], User::STATUS_APPROVED, $birth, $randCreated());
        }
        $io->text(' + ' . count($extras) . ' extra participants (createdAt spread over past 6 months)');

        $this->em->flush();

        return [$admin, $artists, $participants, $extras];
    }

    private function makeUser(string $email, string $nom, string $prenom, array $roles, string $status = User::STATUS_APPROVED, ?string $birthDate = null, ?string $createdOffset = null): User
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
        if ($birthDate !== null) {
            $user->setDateNaissance(new \DateTimeImmutable($birthDate));
        }
        if ($createdOffset !== null) {
            $user->setCreatedAt(new \DateTimeImmutable($createdOffset));
        }
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
            'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=800',
            'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=800',
            'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=800',
            'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800',
            'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800',
            'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800',
            'https://images.unsplash.com/photo-1505236858219-8359eb29e329?w=800',
            'https://images.unsplash.com/photo-1563841930606-67e2bce48b78?w=800',
        ];

        $yasmine = $artists[3];

        // [layoutType, rows, cols] — null = free seating
        $layouts = [
            ['theatre', 8, 12],   // 96 seats
            ['tables',  5,  6],   // 30 seats
            [null,      0,  0],   // free seating
        ];

        // ----------------------------------------------------------------
        // Upcoming events
        // [titre, desc, lieu, nbPlaces, ageMin, ageMax, prix, hot80pct, createdDaysAgo]
        // hot80pct = true → exactly 80 % of seats will be reserved
        // ----------------------------------------------------------------
        $upcomingData = [
            ['Atelier Aquarelle pour enfants',       'Découvrez les techniques de base de l\'aquarelle dans un cadre ludique et bienveillant. Adapté aux enfants de 5 à 12 ans.',                                   'Espace Culturel El Menzah, Tunis',    25,  5, 12, 15.00, false, 45],
            ['Initiation à la Poterie',               'Apprenez à modeler l\'argile et créez vos premières œuvres en poterie. Un moment de détente et de créativité.',                                              'Maison des Arts, La Marsa',            20,  6, 14, 20.00, false, 38],
            // HOT #1 — theatre layout (96 seats) → exactly 80 % = 77 reserved
            ['Grand Concert Inclusif : Musique pour Tous', 'Un concert interactif où les enfants découvrent les instruments et participent à la création musicale collective.',                                    'Théâtre Municipal de Tunis',           96,  3, 99,  null,  true, 60],
            ['Atelier Dessin Manga',                  'Plongez dans l\'univers du manga ! Les enfants apprendront les bases du dessin manga avec un illustrateur professionnel.',                                   'Centre Culturel Hammam-Lif',           18,  8, 16, 12.00, false, 30],
            ['Peinture Murale Collaborative',         'Projet artistique collectif : les enfants créent une fresque murale sur le thème de la nature et de l\'inclusion.',                                         'École Primaire Ariana, Ariana',        30,  6, 15, 18.00, false, 25],
            ['Spectacle de Marionnettes',             'Un spectacle coloré de marionnettes suivi d\'un atelier où les enfants fabriquent leurs propres marionnettes.',                                             'Bibliothèque Nationale, Tunis',        40,  4, 10,  8.00, false, 20],
            ['Atelier Photo Créative',                'Les enfants explorent la photographie artistique. Thème : mon quartier vu par mes yeux.',                                                                   'Cité de la Culture, Tunis',            15, 10, 16, 25.00, false, 18],
            ['Danse Inclusive : Bouge avec moi',      'Atelier de danse où chaque enfant trouve sa propre expression corporelle. Accessible à tous.',                                                              'Salle Omnisports Mégrine',             35,  4, 14, 10.00, false, 15],
            ['Stage Théâtre Ados',                    'Stage intensif de théâtre pour adolescents : improvisation, texte et mise en scène. Encadrement professionnel.',                                            'Théâtre El Hamra, Tunis',              20, 12, 17, 45.00, false, 12],
            ['Concert Jazz & World',                  'Soirée jazz et musiques du monde avec musiciens invités. Bar et restauration sur place.',                                                                   'Café des Nuits, La Marsa',             60, 16, 60, 35.00, false, 10],
            ['Atelier Calligraphie Arabe',            'Initiation à la calligraphie arabe : outils, gestes et réalisation d\'une œuvre encadrée.',                                                                'Institut des Beaux-Arts, Tunis',       12, 14, 50, 40.00, false,  8],
            ['Festival Street Art',                   'Week-end street art : démonstrations live, ateliers bombes et pochoirs, exposition. Prévoir tenue adaptée.',                                                'Friche industrielle, Ben Arous',       80, 10, 50, 22.00, false,  7],
            ['Gala de Danse Contemporaine',           'Soirée exceptionnelle de danse contemporaine avec les meilleurs danseurs de Tunisie. Tenue de soirée recommandée.',                                         'Opéra de Tunis',                      100, 16, 99, 50.00, false,  6],
            ['Atelier Percussion Africaine',          'Découvrez les rythmes africains et apprenez à jouer du djembé et du balafon. Aucune expérience requise.',                                                  'Maison de la Culture, Sousse',         25,  8, 50, 20.00, false,  5],
            ['Exposition & Vente d\'Art Jeunes',      'Vernissage et vente des œuvres de jeunes artistes tunisiens. Entrée libre, œuvres à partir de 30 TND.',                                                    'Galerie Gorgi, Tunis',                 60, 16, 99,  null, false,  4],
            ['Festival des Arts de Rue',              'Deux jours de performances artistiques en plein air : jongleurs, musiciens, danseurs et artistes plasticiens.',                                             'Avenue Habib Bourguiba, Tunis',       150,  5, 99,  null, false,  3],
        ];

        // ----------------------------------------------------------------
        // Expired events
        // [titre, desc, lieu, nbPlaces, ageMin, ageMax, prix, pastOffset, fillPct, createdDaysAgo]
        // fillPct: confirmed fill rate (0.0–1.0) used to train the ML model with varied labels:
        //   >= 0.75 → "En feu"  |  >= 0.50 → "Populaire"  |  >= 0.25 → "Tiède"  |  < 0.25 → "Calme"
        // ----------------------------------------------------------------
        $expiredData = [
            // "En feu" tier (fill ≥ 75 %)
            ['Nuit des Musées – Art Contemporain',    'Visite nocturne guidée des galeries d\'art contemporain de Tunis. Rencontres avec les artistes.',                                                          'Cité de la Culture, Tunis',           100, 16, 99,  null, '-6 months',          0.90, 180],
            ['Concert de Fin d\'Année 2025',          'Grand concert de clôture de la saison artistique avec les groupes formés lors des ateliers musique.',                                                       'Théâtre de l\'Opéra, Tunis',          200,  6, 99,  null, '-3 months',          0.85, 130],
            ['Festival Musique Traditionnelle',       'Deux jours de concerts et d\'ateliers autour des musiques traditionnelles tunisiennes et méditerranéennes.',                                                'Amphithéâtre Carthage, Tunis',        300, 10, 99, 15.00, '-5 months -15 days', 0.80, 170],
            ['Dîner-Spectacle Flamenco',              'Soirée flamenco avec dîner gastronomique. Spectacle de danse et guitare espagnole. Places très limitées.',                                                 'Restaurant El Andalus, Sidi Bou Saïd', 30, 18, 99, 85.00, '-3 months -20 days', 0.77, 120],
            // "Populaire" tier (fill 50–74 %)
            ['Journée Portes Ouvertes Ateliers',      'Découvrez tous nos ateliers en une journée : démonstrations, essais gratuits et rencontres avec les artistes.',                                             'Centre Art Connect, Tunis',           200,  3, 99,  null, '-2 months -5 days',  0.65, 80],
            ['Gala de Fin de Saison',                 'Soirée de clôture de la saison artistique : remise de diplômes, performances et cocktail dînatoire.',                                                      'Hôtel Africa, Tunis',                 120, 16, 99, 30.00, '-7 weeks',           0.58, 60],
            ['Stage Cirque & Acrobatie',              'Stage de 3 jours : jonglage, équilibre et acrobatie pour les enfants. Encadrement par des artistes professionnels.',                                       'Chapiteau Cirque Soleil, Ariana',      40,  7, 14, 55.00, '-4 months -10 days', 0.52, 145],
            // "Tiède" tier (fill 25–49 %)
            ['Exposition Peinture Enfants 2025',      'Vernissage de fin d\'année : les enfants exposent leurs œuvres réalisées lors des ateliers de l\'année.',                                                   'Galerie Municipale, Tunis',            50,  5, 14,  null, '-5 months',          0.40, 175],
            ['Atelier Céramique Famille',             'Atelier parent-enfant : créez ensemble un objet en céramique. Cuisson et livraison sous 2 semaines.',                                                      'Atelier La Terre, La Marsa',           16,  5, 99, 35.00, '-2 months',          0.35, 75],
            ['Atelier BD & Illustration',             'Apprenez à construire une planche de bande dessinée : scénario, découpage et dessin. Niveau débutant.',                                                    'Médiathèque Ennasr, Tunis',            20, 10, 18, 18.00, '-1 month',           0.30, 40],
            // "Calme" tier (fill < 25 %)
            ['Atelier Sculpture sur Bois',            'Initiation à la sculpture sur bois avec des outils adaptés. Création d\'un petit objet à emporter.',                                                       'Maison des Artisans, Sfax',            12, 12, 50, 30.00, '-4 months',          0.20, 150],
            ['Atelier Slam & Poésie',                 'Initiation au slam poétique : écriture, mise en voix et performance. Ouvert à tous les niveaux.',                                                          'Café Littéraire, Tunis',               20, 14, 40, 12.00, '-1 month -15 days',  0.15, 55],
        ];

        $events      = [];
        $hotEvents   = [];
        $fillPctMap  = []; // spl_object_id($ev) => float fill rate for expired events
        $imgCount    = count($images);

        // --- Upcoming events ---
        foreach ($upcomingData as $i => [$titre, $desc, $lieu, $places, $ageMin, $ageMax, $prix, $hot, $createdAgo]) {
            $ev = new Evenement();
            $ev->setTitre($titre);
            $ev->setDescription($desc);
            $ev->setLieu($lieu);
            $ev->setAgeMin($ageMin);
            $ev->setAgeMax($ageMax);
            $ev->setPrix($prix);
            $ev->setCreatedAt(new \DateTimeImmutable("-{$createdAgo} days"));

            $start = new \DateTime('+' . ($i * 5 + 3) . ' days');
            $start->setTime(random_int(9, 15), 0);
            $ev->setDateDebut($start);
            $ev->setDateFin((clone $start)->modify('+2 hours'));

            $ev->setOrganisateur(in_array($i, [0, 1, 5, 9, 12]) ? $yasmine : $artists[$i % count($artists)]);
            $ev->setImage($images[$i % $imgCount]);

            [$layoutType, $rows, $cols] = $layouts[$i % 3];
            if ($layoutType !== null) {
                $ev->setLayoutType($layoutType);
                $ev->setLayoutRows($rows);
                $ev->setLayoutCols($cols);
                $ev->setNbPlaces($rows * $cols);
            } else {
                $ev->setNbPlaces($places);
            }

            $this->em->persist($ev);
            $events[] = $ev;
            if ($hot) {
                $hotEvents[] = $ev;
            }
            $io->text(sprintf(' + [UPCOMING%s] %s (layout: %s, places: %d)',
                $hot ? ' 🔥 80%' : '', $titre, $layoutType ?? 'none', $ev->getNbPlaces()));
        }

        // --- Expired events ---
        foreach ($expiredData as $j => [$titre, $desc, $lieu, $places, $ageMin, $ageMax, $prix, $pastOffset, $fillPct, $createdAgo]) {
            $ev = new Evenement();
            $ev->setTitre($titre);
            $ev->setDescription($desc);
            $ev->setLieu($lieu);
            $ev->setAgeMin($ageMin);
            $ev->setAgeMax($ageMax);
            $ev->setPrix($prix);
            $ev->setCreatedAt(new \DateTimeImmutable("-{$createdAgo} days"));

            $start = new \DateTime($pastOffset);
            $start->setTime(random_int(9, 15), 0);
            $ev->setDateDebut($start);
            $ev->setDateFin((clone $start)->modify('+3 hours'));

            $ev->setOrganisateur($artists[$j % count($artists)]);
            $ev->setImage($images[($j + count($upcomingData)) % $imgCount]);

            [$layoutType, $rows, $cols] = $layouts[$j % 3];
            if ($layoutType !== null) {
                $ev->setLayoutType($layoutType);
                $ev->setLayoutRows($rows);
                $ev->setLayoutCols($cols);
                $ev->setNbPlaces($rows * $cols);
            } else {
                $ev->setNbPlaces($places);
            }

            $this->em->persist($ev);
            $events[]                         = $ev;
            $fillPctMap[spl_object_id($ev)]   = $fillPct;
            $io->text(sprintf(' + [EXPIRED %.0f%%]  %s (layout: %s, places: %d)',
                $fillPct * 100, $titre, $layoutType ?? 'none', $ev->getNbPlaces()));
        }

        $this->em->flush();

        return [$events, $hotEvents, $fillPctMap];
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

    private function createReservations(SymfonyStyle $io, array $events, array $hotEvents, array $fillPctMap, array $allParticipants): void
    {
        $count       = 0;
        $hotEventIds = array_map(fn($e) => spl_object_id($e), $hotEvents);
        $now         = new \DateTimeImmutable();

        foreach ($events as $event) {
            $isHot      = in_array(spl_object_id($event), $hotEventIds, true);
            $isPast     = isset($fillPctMap[spl_object_id($event)]);
            $capacity   = $event->getNbPlaces();

            if ($isPast) {
                // Expired events: use the explicit fill rate so the ML model trains on varied data.
                // All reservations are CONFIRMED so the ML query (which filters on status='CONFIRMED')
                // sees the exact fill rate we intend.
                $fillPct = $fillPctMap[spl_object_id($event)];
                $target  = max(1, (int) round($capacity * $fillPct));
                $label   = sprintf('%.0f%% fill (ML training)', $fillPct * 100);
            } elseif ($isHot) {
                $target = (int) floor($capacity * 0.80);
                $label  = '🔥 80 % FULL';
            } else {
                $pct    = random_int(15, 45) / 100;
                $target = max(2, (int) round($capacity * $pct));
                $label  = 'normal';
            }

            $target = min($target, count($allParticipants));

            $shuffled = $allParticipants;
            shuffle($shuffled);
            $pick = array_slice($shuffled, 0, $target);

            // Reservation dates: between event createdAt and event start (or today for future events)
            $eventCreated   = $event->getCreatedAt();
            $eventStart     = $event->getDateDebut();
            $bookingCeiling = $eventStart < $now ? \DateTimeImmutable::createFromMutable($eventStart) : $now;
            $bookingFloor   = $eventCreated;
            if ($bookingFloor >= $bookingCeiling) {
                $bookingFloor = $bookingCeiling->modify('-7 days');
            }
            $windowSeconds = max(1, $bookingCeiling->getTimestamp() - $bookingFloor->getTimestamp());

            $seatIndex = 0;
            foreach ($pick as $participant) {
                $r = new Reservation();
                $r->setEvenement($event);
                $r->setParticipant($participant);

                $offsetSec = random_int(0, $windowSeconds);
                $r->setDateReservation($bookingFloor->modify("+{$offsetSec} seconds"));

                if ($isPast) {
                    // All CONFIRMED so the ML model sees clean fill-rate data
                    $r->setStatus(Reservation::STATUS_CONFIRMED);
                } else {
                    // Upcoming events: realistic mix — 75 % CONFIRMED, 15 % PENDING, 10 % CANCELLED
                    $roll = random_int(1, 20);
                    if ($roll <= 15) {
                        $r->setStatus(Reservation::STATUS_CONFIRMED);
                    } elseif ($roll <= 18) {
                        $r->setStatus(Reservation::STATUS_PENDING);
                    } else {
                        $r->setStatus(Reservation::STATUS_CANCELLED);
                    }
                }

                if ($r->getStatus() === Reservation::STATUS_CONFIRMED && $event->getPrix() !== null) {
                    $r->setAmountPaid((int) round($event->getPrix() * 1000));
                }

                if ($event->getLayoutType() && $r->getStatus() !== Reservation::STATUS_CANCELLED) {
                    $seatIndex++;
                    if ($seatIndex > $capacity) {
                        break;
                    }
                    if ($event->getLayoutType() === 'theatre') {
                        $row = (int) ceil($seatIndex / $event->getLayoutCols());
                        $col = $seatIndex - ($row - 1) * $event->getLayoutCols();
                        $r->setSeatLabel('R' . $row . '-S' . $col);
                    } else {
                        $table = (int) ceil($seatIndex / $event->getLayoutCols());
                        $seat  = $seatIndex - ($table - 1) * $event->getLayoutCols();
                        $r->setSeatLabel('T' . $table . '-S' . $seat);
                    }
                }

                $this->em->persist($r);
                $count++;
            }

            $io->text(sprintf('   → %-50s %3d / %3d seats  (%s)',
                mb_substr($event->getTitre(), 0, 50),
                $target,
                $capacity,
                $label));
        }

        $this->em->flush();
        $io->text(" + $count total reservations created");
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
