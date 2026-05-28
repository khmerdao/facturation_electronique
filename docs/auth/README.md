# Section AUTH — Pages d'authentification

Pages publiques (hors tenant, hors authentification).  
Aucun `TenantFilter` actif — ces routes sont en dehors du firewall principal.

---

## Sommaire

| Page | URL | Accès |
|---|---|---|
| Connexion | `/login` | Public |
| Inscription | `/register` | Public |
| Mot de passe oublié | `/forgot-password` | Public |
| Réinitialisation | `/reset-password/{token}` | Public (lien signé) |

---

## 1. `/login` — Connexion

### Rôles autorisés
Public (redirection vers `/dashboard` si déjà authentifié).

### Objectif
Authentifier un utilisateur existant et initialiser son contexte tenant.

### Features / fonctionnalités

**Formulaire principal**
- Champ email (type email, autocomplete="username")
- Champ mot de passe (type password, autocomplete="current-password", toggle show/hide)
- Case "Se souvenir de moi" (prolonge la session à 30 jours via `remember_me`)
- Bouton "Se connecter" (submit)
- Lien "Mot de passe oublié ?" → `/forgot-password`
- Lien "Créer un compte" → `/register`

**Authentification 2FA (TOTP)**
- Si l'utilisateur a activé le 2FA : après validation email/mdp, redirection vers un écran intermédiaire de saisie du code TOTP (6 chiffres)
- Champ code TOTP avec autosubmit à 6 caractères
- Lien "Utiliser un code de récupération"
- Timeout 5 minutes sur l'étape 2FA (session partielle expirée)

**Gestion multi-tenant post-login**
- Si l'utilisateur appartient à **1 seul tenant** → redirect direct `/dashboard`
- Si l'utilisateur appartient à **plusieurs tenants** → écran de sélection d'organisation (liste des tenants avec nom, logo, rôle)
- Le tenant sélectionné est stocké dans la session + encodé dans le JWT

**Edge cases UX**
- Erreur credentials invalides : message générique "Email ou mot de passe incorrect" (pas de distinction pour éviter l'énumération d'emails)
- Compte non vérifié : message spécifique + lien "Renvoyer l'email de vérification"
- Compte désactivé (tenant suspendu) : message explicatif avec contact support
- Rate limiting : après 5 tentatives échouées en 15 min → CAPTCHA (hCaptcha) obligatoire
- Après 10 tentatives → blocage temporaire 30 min + notification email
- Redirection post-login : si `?redirect=` en query string, redirection vers l'URL demandée (whitelist interne uniquement)

### Composants UI
- Card centré (max-width 440px), layout full-height centré
- Logo de l'application en haut
- Formulaire Symfony `LoginFormType` rendu en Twig
- Alert Bootstrap pour les flash messages (erreur/succès)
- Spinner sur le bouton submit pendant la requête
- Badge "Connexion sécurisée" (HTTPS indicator)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `SecurityController::login()` | Affichage du formulaire, récupération de l'erreur |
| `AppAuthenticator` (Guard) | Vérification credentials, génération JWT, init session |
| `TotpAuthenticator` | Validation code TOTP si 2FA activé |
| `LoginThrottlingService` | Comptage tentatives Redis, déclenchement CAPTCHA |
| `TenantResolver` | Résolution du tenant depuis l'email + sélection si multi-tenant |
| `LexikJWT\TokenGenerator` | Génération du JWT avec claims `tenant_id`, `roles` |

### Entités Doctrine

| Entité | Usage |
|---|---|
| `User` | Lookup par email, vérification password hash |
| `TenantMembership` | Récupération des tenants associés + rôles |
| `Tenant` | Vérification statut (actif/suspendu) |

### Dépendances
- Post-login → `/dashboard` ou sélecteur de tenant
- Lien vers `/forgot-password`
- Lien vers `/register`

---

## 2. `/register` — Inscription + création d'organisation

### Rôles autorisés
Public (redirection vers `/dashboard` si déjà authentifié).

### Objectif
Créer simultanément un compte utilisateur et une organisation (tenant), puis démarrer l'onboarding.

### Features / fonctionnalités

**Étape 1 — Informations personnelles**
- Prénom + Nom
- Email professionnel (validation format + vérification domaine MX en async)
- Mot de passe (min 12 caractères, indicateur de force en temps réel)
- Confirmation mot de passe
- Acceptation CGU + Politique de confidentialité (checkbox obligatoire, lien vers les docs)
- Acceptation traitement données fiscales (obligatoire, cadre RGPD)

**Étape 2 — Informations de l'organisation**
- Nom de l'organisation
- SIRET (14 chiffres, validation format + appel API Sirene pour vérification existence)
  - Auto-complétion du nom légal, adresse, code NAF depuis l'API Sirene
  - Indicateur "SIRET vérifié ✓" ou message d'erreur si introuvable
- Numéro de TVA intracommunautaire (optionnel, validation checksum)
- Choix du plan (FREE / PRO / ENTERPRISE) avec tableau comparatif
- Code de parrainage / coupon (optionnel)

**Confirmation & vérification email**
- Envoi email de vérification (token signé, expiry 24h)
- Page d'attente "Vérifiez votre boîte mail"
- Bouton "Renvoyer l'email" (rate-limited : 1 renvoi/min)
- Lien de vérification → activation compte + redirect `/onboarding/organisation`

**Edge cases UX**
- Email déjà utilisé : message "Un compte existe déjà avec cet email" + lien vers `/login`
- SIRET déjà enregistré sur un tenant existant : warning "Cette organisation existe déjà — demandez une invitation à son administrateur"
- API Sirene indisponible : champ SIRET reste éditable, vérification différée, warning affiché
- Mot de passe trop faible : blocage du submit avec message explicatif
- Formulaire multi-étapes : état sauvegardé en session (perte de données si fermeture navigateur évitée)
- Timeout session inscription : 30 minutes, warning à 5 min de l'expiration

### Composants UI
- Stepper 2 étapes (Step 1 : Compte / Step 2 : Organisation) en haut de la card
- Indicateur de force du mot de passe (barre de progression colorée : rouge/orange/vert)
- Badge "SIRET vérifié" avec spinner pendant la vérification async
- Tableau comparatif des plans (modal ou inline)
- Composant Stimulus `SiretLookupController` pour l'appel API Sirene
- Progress indicator entre les étapes

### Appels API / services Symfony

| Service | Action |
|---|---|
| `RegistrationController` | Orchestration du flux multi-étapes |
| `UserRegistrationService` | Création User + hachage Argon2id |
| `TenantCreationService` | Création Tenant + TenantMembership (rôle OWNER) |
| `SireneApiClient` | Vérification SIRET via API Annuaire des Entreprises |
| `EmailVerificationService` | Génération token signé + envoi Symfony Mailer |
| `PasswordStrengthEstimator` | Score zxcvbn côté serveur |
| `PlanSubscriptionService` | Init abonnement selon plan choisi |
| `AuditLogger` | `tenant.created`, `user.registered` |

### Entités Doctrine

| Entité | Usage |
|---|---|
| `User` | Création |
| `Tenant` | Création avec SIRET, plan, settings par défaut |
| `TenantMembership` | Création avec rôle `OWNER` |
| `EmailVerificationToken` | Stockage token + expiry |

### Dépendances
- Post-vérification email → `/onboarding/organisation`
- Lien vers `/login`
- Lien vers CGU (page statique)

---

## 3. `/forgot-password` — Mot de passe oublié

### Rôles autorisés
Public (redirection vers `/dashboard` si déjà authentifié).

### Objectif
Permettre à un utilisateur de déclencher la procédure de réinitialisation de son mot de passe par email.

### Features / fonctionnalités

**Formulaire**
- Champ email unique
- Bouton "Envoyer le lien de réinitialisation"
- Lien retour vers `/login`

**Comportement post-soumission**
- **Toujours** afficher le message de confirmation générique "Si un compte existe avec cet email, vous recevrez un lien dans quelques minutes" — que l'email existe ou non (anti-énumération)
- Envoi email avec lien signé contenant token HMAC (expiry 1 heure)
- Le lien est à usage unique (invalidé après utilisation)
- Rate limiting : 3 demandes max par email sur 1 heure (Redis)
- Log de la demande (IP, user-agent, timestamp) dans `AuditLog`

**Edge cases UX**
- Email invalide (format) : erreur de validation immédiate côté client
- Rate limit atteint : message "Vous avez déjà demandé un lien récemment. Vérifiez vos spams ou réessayez dans X minutes"
- Email dans les spams : mention explicite dans le message de confirmation

### Composants UI
- Card centré (max-width 440px)
- État "succès" avec icône enveloppe et instructions claires
- Spinner pendant la soumission
- Countdown si rate limit atteint

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ResetPasswordController` | Affichage formulaire, traitement soumission |
| `SymfonyCasts\ResetPassword` | Génération token, envoi email (bundle reset-password) |
| `RateLimiterFactory` | Limite 3 req/heure/email (composant Rate Limiter Symfony) |
| `AuditLogger` | `user.password_reset_requested` |

### Entités Doctrine

| Entité | Usage |
|---|---|
| `User` | Lookup par email (sans révéler l'existence) |
| `ResetPasswordRequest` | Stockage token haché + expiry (géré par le bundle) |

### Dépendances
- Lien vers `/login`
- Post-soumission → même page (état "email envoyé")

---

## 4. `/reset-password/{token}` — Réinitialisation du mot de passe

### Rôles autorisés
Public — accès uniquement via lien signé reçu par email.

### Objectif
Permettre à l'utilisateur de définir un nouveau mot de passe après validation du token.

### Features / fonctionnalités

**Validation du token (avant affichage formulaire)**
- Vérification existence + non-expiration du token
- Vérification usage unique (non déjà consommé)
- Si token invalide/expiré : page d'erreur avec lien vers `/forgot-password`

**Formulaire de nouveau mot de passe**
- Champ "Nouveau mot de passe" (min 12 caractères, indicateur de force)
- Champ "Confirmer le nouveau mot de passe"
- Bouton "Réinitialiser mon mot de passe"

**Post-réinitialisation**
- Mise à jour du hash Argon2id en base
- Invalidation de **toutes** les sessions actives de l'utilisateur (sécurité)
- Invalidation de **tous** les JWT en cours (blacklist Redis)
- Invalidation du token de réinitialisation
- Déconnexion de tous les appareils
- Envoi email de confirmation "Votre mot de passe a été modifié"
- Redirect vers `/login` avec flash success "Mot de passe modifié, vous pouvez vous connecter"
- Log `user.password_reset_completed` dans `AuditLog`

**Edge cases UX**
- Token expiré (> 1h) : page dédiée "Ce lien a expiré" + lien vers `/forgot-password`
- Token déjà utilisé : même page d'erreur (pas de distinction pour éviter les attaques)
- Token malformé (URL altérée) : redirect `/forgot-password` sans message d'erreur détaillé
- Mot de passe identique à l'ancien : warning (non bloquant) "Ce mot de passe a déjà été utilisé"
- Perte de connexion pendant la soumission : le token reste valide, l'utilisateur peut réessayer

### Composants UI
- Card centré (max-width 440px)
- État "token invalide" : illustration d'erreur + bouton CTA vers `/forgot-password`
- Indicateur de force du mot de passe (identique à `/register`)
- État "succès" avec countdown avant redirect automatique vers `/login` (5 secondes)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ResetPasswordController` | Validation token, traitement formulaire |
| `SymfonyCasts\ResetPassword` | Validation + consommation token |
| `UserPasswordHasherInterface` | Hash Argon2id du nouveau mot de passe |
| `SessionInvalidationService` | Invalidation toutes sessions actives |
| `JwtBlacklistService` | Blacklist Redis de tous les JWT actifs |
| `AuditLogger` | `user.password_reset_completed` |
| `Symfony Mailer` | Email de confirmation de changement |

### Entités Doctrine

| Entité | Usage |
|---|---|
| `User` | Mise à jour `password` |
| `ResetPasswordRequest` | Validation + suppression après usage |

### Dépendances
- Accès uniquement depuis le lien email (token en paramètre de route)
- Post-succès → `/login`
- Token invalide → `/forgot-password`

---

## Notes transversales section AUTH

### Sécurité
- Toutes les pages AUTH sont servies en **HTTPS uniquement** (HSTS)
- Headers de sécurité sur toutes les réponses : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin`
- Le cookie de session est `Secure; HttpOnly; SameSite=Strict`
- Aucune information de débogage exposée en production (Symfony `APP_ENV=prod`)

### Performance
- Ces pages ne chargent **pas** le bundle JS complet de l'application
- CSS Bootstrap + JS minimal uniquement (pas de Turbo Drive sur les pages AUTH pour éviter les conflits avec les formulaires de sécurité Symfony)
- Symfony CSRF tokens sur tous les formulaires

### Internationalisation
- Toutes les pages AUTH disponibles en FR (défaut) et EN
- Détection automatique depuis `Accept-Language` header
- Préférence stockée dans la session puis en `User.locale` après login
