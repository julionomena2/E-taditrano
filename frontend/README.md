# Système de recherche de logement — Frontend React

Frontend (interface) de l'application, écrit en React avec l'outil **Vite**.
Les données du catalogue (villes, quartiers, établissements et logements)
sont chargées depuis l'API Laravel. Aucune donnée de remplacement n'est
utilisée si l'API est indisponible.

## 1. Prérequis

Il vous faut **Node.js** installé sur votre ordinateur (version 18 ou plus récente).
Pour vérifier si vous l'avez déjà :

```bash
node -v
npm -v
```

Si ces commandes ne fonctionnent pas, téléchargez Node.js ici :
https://nodejs.org (choisissez la version "LTS").

## 2. Installation

1. Ouvrez un terminal dans le dossier `frontend` :

   ```bash
   cd frontend
   ```

3. Installez les dépendances (React, Vite, etc.) :

   ```bash
   npm install
   ```

   Cette commande lit le fichier `package.json` et télécharge tout ce
   qui est nécessaire dans un dossier `node_modules` (créé automatiquement,
   il ne faut jamais le modifier à la main).

4. Créez votre fichier de configuration local à partir du modèle fourni :

   ```bash
   cp .env.example .env
   ```

   (Sous Windows, avec l'invite de commandes classique, utilisez plutôt
   `copy .env.example .env`.)

5. Pour les annonces et les itinéraires, créez une clé navigateur dans
   Google Cloud Console, activez **Maps JavaScript API**, **Geocoding API**
   et **Directions API (Legacy)**, puis renseignez `VITE_GOOGLE_MAPS_API_KEY`
   dans `frontend/.env`. La clé
   est visible dans le navigateur : limitez-la aux domaines de l'application
   et à ces deux API. Ne placez pas une clé privée ou non restreinte dans ce
   fichier.

      La géolocalisation de l’utilisateur nécessite son autorisation et un
      contexte sécurisé : HTTPS en ligne, ou `localhost` en développement.
      L’itinéraire est calculé en voiture et affiché dans la fiche du logement.

## 3. Lancer l'application en développement

```bash
npm run dev
```

Le terminal affiche une adresse locale, en général :

```
Local:   http://localhost:5173/
```

Ouvrez cette adresse dans votre navigateur : l'application s'affiche.
Tant que le terminal tourne, chaque modification que vous faites dans le
code est automatiquement répercutée dans le navigateur (rechargement à
chaud, "Hot Module Replacement").

Pour arrêter le serveur : `Ctrl + C` dans le terminal.

## 4. Structure du projet

```
logement-frontend/
├── index.html              -> page HTML unique (le "squelette")
├── .env.example             -> modèle de configuration (adresse de l'API)
├── src/
│   ├── main.jsx              -> point d'entrée, démarre React
│   ├── App.jsx                -> composant principal, gère la navigation
│   ├── index.css               -> tous les styles de l'application
│   ├── components/              -> un fichier par page/élément d'interface
│   │   ├── Header.jsx
│   │   ├── Accueil.jsx
│   │   ├── ListeEtablissements.jsx
│   │   ├── ListeLogements.jsx
│   │   ├── DetailLogement.jsx
│   │   ├── FormulaireConnexion.jsx
│   │   ├── FormulaireInscription.jsx
│   │   ├── MonCompte.jsx
│   │   ├── PublierAnnonce.jsx
│   │   ├── MesAnnonces.jsx
│   │   └── Messagerie.jsx
│   └── services/
│       └── api.js               -> requêtes vers l'API Laravel
```

## 5. Connexion au backend Laravel

En développement, Vite transmet les requêtes `/api` vers Laravel à
`VITE_BACKEND_URL` (par défaut `http://localhost:8000`). Lancez les deux
serveurs :

```bash
# Depuis la racine Laravel
php artisan serve

# Dans un autre terminal, depuis frontend/
npm run dev
```

L'API expose les routes de consultation suivantes :

- `GET /api/villes`
- `GET /api/quartiers?id_ville=...`
- `GET /api/etablissements?id_ville=...` ou `?id_quartier=...`
- `GET /api/logements?id_quartier=...`
- `GET /api/logements/proches?latitude=...&longitude=...&id_quartier=...`

L'authentification utilise une session Laravel et un cookie de session
HttpOnly : le navigateur ne conserve aucun mot de passe ni jeton
d'authentification dans le stockage local. Les routes disponibles sont :

- `GET /api/auth/session` pour restaurer la session et initialiser la protection CSRF.
- `POST /api/auth/login` avec `email` et `mot_de_passe`.
- `POST /api/auth/logout` pour fermer la session.
- `PATCH /api/auth/profile` pour modifier les informations du compte connecté, sa photo et éventuellement son mot de passe.
- `POST /api/register` pour créer un compte et ouvrir sa session.
- `GET /api/messagerie/conversations` pour récupérer les conversations récentes.
- `GET /api/messagerie/{email}/messages` pour consulter les 100 derniers messages échangés avec un utilisateur.
- `POST /api/messagerie/{email}/messages` avec `contenu` pour envoyer un message (2 000 caractères maximum).
- `GET /api/mes-logements` pour consulter les annonces du compte connecté.
- `POST /api/mes-logements` pour publier une annonce, avec coordonnées et jusqu'à 10 photos.
- `POST /api/mes-logements/{id}` avec `_method=PATCH` pour modifier l'annonce et ses photos.
- `DELETE /api/mes-logements/{id}` pour supprimer une annonce et ses photos.

Le frontend transmet le jeton CSRF dans l'en-tête `X-CSRF-TOKEN`. En
développement, le proxy Vite garde le frontend et Laravel sur une même
origine ; en production, servez de préférence le frontend depuis la même
origine que l'API Laravel. La vérification de l'adresse e-mail est reportée.
Les photos de profil sont servies depuis le disque public Laravel : exécutez
`php artisan storage:link` une fois pour rendre le dossier public accessible.
La messagerie nécessite une session utilisateur et stocke les messages dans la
base de données. La liste n’affiche que les profils avec lesquels un message a
déjà été échangé ; elle conserve ces profils et s’actualise périodiquement pour
les nouveaux messages. Pour démarrer une conversation, contactez le propriétaire
d’une annonce. Le temps réel n'est pas encore activé. L'intégration Google et
la publication d'annonces nécessitent la configuration Google Maps ci-dessus.
Les coordonnées sont choisies en cliquant sur la carte ; le centrage du
quartier utilise le géocodage Google et n'impose pas de limite géographique.
Les annonces sont créées avec la date du jour et le statut « Disponible ».

## 6. Construire une version prête pour la mise en ligne

Quand le projet sera terminé, cette commande générera les fichiers
optimisés (HTML/CSS/JS) dans un dossier `dist/` :

```bash
npm run build
```

## 7. Pistes pour la suite

- Ajouter l'intégration Google Maps dans `DetailLogement.jsx`
  (l'endroit exact est indiqué par un commentaire dans le fichier).
- Ajouter l'espace Admin (« Gérer les comptes », « Gérer les données »).
- Remplacer la navigation "à la main" (state `page` dans `App.jsx`) par
  la librairie `react-router-dom`, si vous voulez des adresses (URL)
  différentes pour chaque page (ex: `/logements/3`).
