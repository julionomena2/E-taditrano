// components/Header.jsx
// -----------------------------------------------------------------------
// Barre de navigation affichée en haut de toutes les pages.
// Elle reçoit des "props" (des informations données par le composant
// parent App.jsx) :
//   - utilisateur : l'utilisateur connecté (ou null si personne connecté)
//   - allerA      : une fonction qui permet de changer de page
//   - seDeconnecter : une fonction à appeler quand on clique sur "Se déconnecter"
// -----------------------------------------------------------------------

export default function Header({ utilisateur, allerA, seDeconnecter }) {
  return (
    <header className="header">
      <button className="logo" onClick={() => allerA("accueil")}>
        <span className="logo-marque" aria-hidden="true">e-</span>
        <span className="logo-texte">taditrano<span>.</span></span>
      </button>

      <nav className="nav">
        {utilisateur ? (
          <>
            <button className="nav-accueil" onClick={() => allerA("accueil")}>Explorer</button>
            <button onClick={() => allerA("mesAnnonces")}>Mes annonces</button>
            <button onClick={() => allerA("messagerie")}>Messagerie</button>
            <button onClick={() => allerA("monCompte")}>Mon compte</button>
            <button onClick={seDeconnecter}>Se déconnecter</button>
          </>
        ) : (
          <>
            <button className="nav-accueil" onClick={() => allerA("accueil")}>Explorer</button>
            <button onClick={() => allerA("connexion")}>Se connecter</button>
            <button className="nav-inscription" onClick={() => allerA("inscription")}>Créer un compte</button>
          </>
        )}
      </nav>
    </header>
  );
}
