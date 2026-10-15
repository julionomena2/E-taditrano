import { useEffect, useRef, useState } from "react";
import {
  connecterAvecGoogle,
  inscrire,
} from "../services/api.js";
import PasswordInput from "./PasswordInput.jsx";

const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID;
const TAILLE_MAX_PHOTO = 5 * 1024 * 1024;
const TYPES_PHOTO = ["image/jpeg", "image/png", "image/webp"];
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const TELEPHONE_REGEX = /^\d{10}$/;

export default function FormulaireInscription({ onConnecte, allerA }) {
  const [nom, setNom] = useState("");
  const [prenom, setPrenom] = useState("");
  const [email, setEmail] = useState("");
  const [telephone, setTelephone] = useState("");
  const [motDePasse, setMotDePasse] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [photo, setPhoto] = useState(null);
  const [erreur, setErreur] = useState("");
  const [envoiEnCours, setEnvoiEnCours] = useState(false);
  const [urlPhoto, setUrlPhoto] = useState("");
  const [erreurGoogle, setErreurGoogle] = useState("");
  const [googlePret, setGooglePret] = useState(false);
  const conteneurGoogle = useRef(null);
  const urlPhotoCourante = useRef("");
  const onConnecteRef = useRef(onConnecte);

  useEffect(() => {
    onConnecteRef.current = onConnecte;
  }, [onConnecte]);

  useEffect(() => () => {
    if (urlPhotoCourante.current) URL.revokeObjectURL(urlPhotoCourante.current);
  }, []);

  useEffect(() => {
    if (!GOOGLE_CLIENT_ID || !conteneurGoogle.current) return undefined;

    let annule = false;
    const initialiserGoogle = () => {
      if (annule || !window.google?.accounts?.id || !conteneurGoogle.current) return;

      window.google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: async ({ credential }) => {
          setErreurGoogle("");
          setEnvoiEnCours(true);
          try {
            const utilisateur = await connecterAvecGoogle(credential);
            onConnecteRef.current(utilisateur);
          } catch (e) {
            setErreurGoogle(e.message || "Connexion Google impossible, veuillez réessayer.");
          } finally {
            setEnvoiEnCours(false);
          }
        },
      });
      window.google.accounts.id.renderButton(conteneurGoogle.current, {
        type: "standard",
        theme: "outline",
        size: "large",
        text: "continue_with",
        shape: "rect",
        width: 360,
      });
      setGooglePret(true);
    };

    const scriptExistant = document.querySelector(
      'script[src="https://accounts.google.com/gsi/client"]',
    );
    if (window.google?.accounts?.id) {
      initialiserGoogle();
    } else if (scriptExistant) {
      scriptExistant.addEventListener("load", initialiserGoogle);
      return () => {
        annule = true;
        scriptExistant.removeEventListener("load", initialiserGoogle);
      };
    } else {
      const script = document.createElement("script");
      script.src = "https://accounts.google.com/gsi/client";
      script.async = true;
      script.defer = true;
      script.onload = initialiserGoogle;
      script.onerror = () => setErreurGoogle("Le service Google n'a pas pu être chargé.");
      document.head.appendChild(script);
      return () => {
        annule = true;
        script.onload = null;
        script.onerror = null;
      };
    }

    return () => {
      annule = true;
    };
  }, []);

  async function gererEnvoi(evenement) {
    evenement.preventDefault();
    setErreur("");

    const emailNormalise = email.trim().toLowerCase();
    if (!EMAIL_REGEX.test(emailNormalise) || emailNormalise.length > 50) {
      setErreur("Saisissez une adresse e-mail valide de 50 caractères maximum.");
      return;
    }

    if (!TELEPHONE_REGEX.test(telephone)) {
      setErreur("Le numéro de téléphone doit contenir exactement 10 chiffres, sans espace.");
      return;
    }

    if (motDePasse !== confirmation) {
      setErreur("Les mots de passe ne correspondent pas.");
      return;
    }
    if (!/[A-Z]/.test(motDePasse) || !/[a-z]/.test(motDePasse) || !/\d/.test(motDePasse)) {
      setErreur("Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.");
      return;
    }

    setEnvoiEnCours(true);
    try {
      // Envoie au backend les champs contrôlés et le fichier ; Laravel applique la validation finale et renvoie le compte créé.
      const utilisateur = await inscrire({
        nom: nom.trim(),
        prenom: prenom.trim(),
        email: emailNormalise,
        telephone,
        motDePasse,
        confirmation,
        photo,
      });
      onConnecte(utilisateur);
    } catch (e) {
      setErreur(e.message || "La création du compte a échoué, veuillez réessayer.");
    } finally {
      setEnvoiEnCours(false);
    }
  }

  function gererSelectionPhoto(evenement) {
    const fichier = evenement.target.files?.[0] || null;
    setErreur("");

    if (fichier && !TYPES_PHOTO.includes(fichier.type)) {
      setErreur("Choisissez une image au format JPEG, PNG ou WebP.");
      evenement.target.value = "";
      setPhoto(null);
      actualiserApercuPhoto(null);
      return;
    }
    if (fichier && fichier.size > TAILLE_MAX_PHOTO) {
      setErreur("La photo ne doit pas dépasser 5 Mo.");
      evenement.target.value = "";
      setPhoto(null);
      actualiserApercuPhoto(null);
      return;
    }
    setPhoto(fichier);
    actualiserApercuPhoto(fichier);
  }

  function actualiserApercuPhoto(fichier) {
    if (urlPhotoCourante.current) URL.revokeObjectURL(urlPhotoCourante.current);
    urlPhotoCourante.current = fichier ? URL.createObjectURL(fichier) : "";
    setUrlPhoto(urlPhotoCourante.current);
  }

  return (
    <section className="formulaire-page formulaire-inscription">
      <h1>Créer un compte</h1>
      <p className="aide-formulaire">Renseignez vos informations pour créer votre compte.</p>

      <form onSubmit={gererEnvoi} className="formulaire">
        <label>
          Nom
          <input
            type="text"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            autoComplete="family-name"
            maxLength={100}
            required
          />
        </label>

        <label>
          Prénom
          <input
            type="text"
            value={prenom}
            onChange={(e) => setPrenom(e.target.value)}
            autoComplete="given-name"
            maxLength={100}
            required
          />
        </label>

        <label>
          E-mail
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
            maxLength={50}
            pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
            title="Saisissez une adresse e-mail valide de 50 caractères maximum."
            required
          />
        </label>

        <label>
          Numéro de téléphone
          <input
            type="tel"
            value={telephone}
            onChange={(e) => setTelephone(e.target.value)}
            autoComplete="tel"
            inputMode="numeric"
            pattern="[0-9]{10}"
            title="Saisissez exactement 10 chiffres, sans espace."
            maxLength={10}
            required
          />
          <span className="aide-formulaire">10 chiffres exactement, sans espace.</span>
        </label>

        <label>
          Photo de profil <span className="champ-facultatif">(facultatif)</span>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={gererSelectionPhoto}
            aria-describedby="aide-photo"
          />
        </label>
        <p id="aide-photo" className="aide-formulaire">
          JPEG, PNG ou WebP, 5 Mo maximum.
        </p>
        {urlPhoto && (
          <img className="apercu-photo-profil" src={urlPhoto} alt="Aperçu de la photo de profil" />
        )}

        <PasswordInput
          id="inscription-mot-de-passe"
          label="Mot de passe"
          value={motDePasse}
          onChange={(e) => setMotDePasse(e.target.value)}
          autoComplete="new-password"
          required
          minLength={8}
          maxLength={128}
        />
        <p className="aide-formulaire">8 caractères minimum, avec une majuscule, une minuscule et un chiffre.</p>

        <PasswordInput
          id="inscription-confirmation"
          label="Confirmation du mot de passe"
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
          autoComplete="new-password"
          required
          minLength={8}
          maxLength={128}
        />

        {erreur && <p className="message-erreur" role="alert">{erreur}</p>}

        <button type="submit" disabled={envoiEnCours}>
          {envoiEnCours ? "Veuillez patienter..." : "Créer mon compte"}
        </button>
      </form>

      <div className="separateur-auth" aria-hidden="true"><span>ou</span></div>
      {GOOGLE_CLIENT_ID ? (
        <>
          <div className="bouton-google" ref={conteneurGoogle} />
          {!googlePret && !erreurGoogle && <p className="aide-formulaire">Chargement de Google...</p>}
          {erreurGoogle && <p className="message-erreur" role="alert">{erreurGoogle}</p>}
        </>
      ) : (
        <p className="aide-formulaire">
          La connexion Google sera disponible après configuration de VITE_GOOGLE_CLIENT_ID.
        </p>
      )}
      <p>
        Déjà un compte ?{" "}
        <button className="lien" type="button" onClick={() => allerA("connexion")}>
          Se connecter
        </button>
      </p>
    </section>
  );
}
