# Rapport de diagnostic du système de mailing

## Problème identifié
Le système de mailing ne fonctionne pas correctement. Après analyse complète, voici les constats :

## Configuration actuelle
- **Service principal**: `App\Service\EmailService` 
- **Transport**: Symfony Mailer avec Brevo SMTP
- **Templates**: Présents dans `templates/emails/`
- **DSN**: `smtp://a2feae001%40smtp-brevo.com:***@smtp-relay.brevo.com:587`

## Tests effectués

### 1. Test de connexion SMTP direct
- **Résultat**: Échec d'authentification (code 535)
- **Message**: "Authentication failed"
- **Cause**: Identifiants Brevo invalides ou configuration incorrecte

### 2. Test avec EmailService
- **Résultat**: Échec de connexion socket
- **Cause**: Problème de configuration SMTP

## Problèmes identifiés

### 1. Identifiants Brevo
Les identifiants SMTP Brevo semblent incorrects :
- Username: `a2feae001@smtp-brevo.com`
- Password: Clé API commençant par `xsmtpsib-`

### 2. Configuration DSN
Le format du DSN pourrait être incorrect pour Brevo.

## Solutions recommandées

### Option 1: Corriger la configuration Brevo
1. Vérifier les identifiants dans le dashboard Brevo
2. Utiliser le bon format de DSN pour Brevo
3. S'assurer que la clé API est active et valide

### Option 2: Utiliser un autre provider
1. Configurer Gmail SMTP (nécessite un mot de passe d'application)
2. Utiliser SendGrid, Mailgun ou un autre service

### Option 3: Configuration alternative Brevo
Essayer le DSN suivant :
```
MAILER_DSN=smtp://apikey:votre-clé-api@smtp-relay.brevo.com:587
```

## Actions immédiates
1. Vérifier le compte Brevo et régénérer les clés API si nécessaire
2. Tester avec une configuration SMTP simple (Gmail)
3. Mettre à jour le .env avec les bons identifiants

## Services créés pour le diagnostic
- `TestEmailCommand` - Test avec EmailService
- `TestEmailSimpleCommand` - Test direct avec mailer
- `TestConnectionCommand` - Analyse DSN
- `TestSmtpConnectionCommand` - Test connexion SMTP brute

## Conclusion
Le problème principal vient de la configuration SMTP Brevo. Une fois les identifiants corrigés, le système devrait fonctionner correctement.
