# Orilyt Security Hardening

> 🇬🇧 [English version](README.md)

Plugin de durcissement WordPress sans configuration : il empêche un attaquant de découvrir vos identifiants de connexion — l'étape de reconnaissance derrière la plupart des campagnes de force brute.

Né d'une vraie campagne d'attaques ciblée (avril 2026) contre un parc de sites WordPress en production. Déployé depuis sur plus de 30 sites chez trois hébergeurs.

## Ce qu'il bloque

| Protection | Faiblesse WordPress | Comportement |
|---|---|---|
| **Énumération d'auteurs** | `/?author=N` redirige vers `/author/identifiant/` : en itérant N, on récolte tous les logins | 404 sec |
| **Liste des utilisateurs (API REST)** | `/wp-json/wp/v2/users` liste publiquement les comptes (login, slug) | 401 pour les visiteurs anonymes |
| **Abus du mot de passe oublié** | Le formulaire de réinitialisation confirme l'existence d'un compte et peut être harcelé (il envoie de vrais emails) | Max 5 tentatives / 15 min / IP, et réponse identique que le compte existe ou non |
| **Fuites des erreurs de connexion** | « Identifiant inconnu » vs « mot de passe incorrect » confirme quels logins existent | Message générique unique « Invalid credentials » |

Le fil rouge : une attaque par force brute a besoin d'un **login** et d'un mot de passe. Ce plugin rend le login indécouvrable, et ralentit tout le reste.

## Installation

**Option A — depuis l'admin WordPress (recommandé)**
1. Téléchargez le ZIP depuis la [dernière release](../../releases/latest) (ou *Code → Download ZIP*).
2. *Extensions → Ajouter → Téléverser une extension* → activer.
3. À l'activation, la protection s'installe en **mu-plugin** (`wp-content/mu-plugins/`) — indésactivable depuis l'admin, même par un compte compromis. Le plugin visible sert d'interrupteur et affiche un indicateur d'état.

**Option B — manuelle (mu-plugin seul)**
Copiez `mu/0-orilyt-hardening.php` dans `wp-content/mu-plugins/` (créez le dossier au besoin). Aucune activation nécessaire.

## Prérequis

WordPress 5.x+, PHP 7.4+. Mono-site et multisite. Aucun écran de réglages, aucune table en base (seulement des transients de 15 minutes pour le rate limit), impact performance non mesurable.

## Points de vigilance

- **Derrière un proxy/CDN** (Cloudflare…) : `REMOTE_ADDR` est l'IP du proxy, le rate limit devient global au lieu de par-visiteur. Restaurez d'abord l'IP réelle côté serveur.
- **Sites headless** ou extensions exigeant l'accès anonyme à l'endpoint REST des utilisateurs : bloqués par la protection n° 2.
- Les messages d'erreur de connexion sont préservés en français et en anglais ; dans les autres langues, le lien « mot de passe oublié » des messages est aussi généricisé (cosmétique).
- Ce plugin ne rate-limite **pas** `wp-login.php` lui-même — le brute force massif se traite au niveau serveur (fail2ban, WAF).

## Licence

[MIT](LICENSE) — © 2026 Jean-Benoît Kauffmann ([orilyt.com](https://orilyt.com))
